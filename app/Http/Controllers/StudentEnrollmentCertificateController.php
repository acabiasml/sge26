<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\StudentAttendanceCertificateBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StudentEnrollmentCertificateController extends Controller
{
    public function __construct(private readonly StudentAttendanceCertificateBuilder $attendanceCertificateBuilder) {}

    public function documents(Request $request, StudentEnrollment $enrollment): View
    {
        [$academicYear, $class] = $this->context($request, $enrollment);

        return view('student-enrollments.documents', [
            'academicYear' => $academicYear,
            'class' => $class,
            'enrollment' => $enrollment,
            'checks' => $this->documentChecks($academicYear, $enrollment),
            'documents' => $this->documentOptions($enrollment),
        ]);
    }

    public function enrollmentDeclaration(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        [$academicYear, $class] = $this->context($request, $enrollment);

        if (! $enrollment->isActive()) {
            return $this->blocked($enrollment, 'Declaração de matrícula bloqueada: a matrícula precisa estar ativa.');
        }

        return $this->declarationPdf(
            $request,
            $academicYear,
            $class,
            $enrollment,
            'student-enrollment-declaration',
            'Declaração de matrícula',
            'Declaramos, para os devidos fins, que o(a) estudante encontra-se regularmente matriculado(a) nesta instituição.'
        );
    }

    public function schoolingDeclaration(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        [$academicYear, $class] = $this->context($request, $enrollment);

        return $this->declarationPdf(
            $request,
            $academicYear,
            $class,
            $enrollment,
            'student-schooling-declaration',
            'Declaração de escolaridade',
            $enrollment->isActive()
                ? 'Declaramos que o(a) estudante encontra-se vinculado(a) a esta instituição no ano letivo informado.'
                : 'Declaramos que o(a) estudante manteve vínculo escolar com esta instituição no ano letivo informado.'
        );
    }

    public function completionDeclaration(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        [$academicYear, $class] = $this->context($request, $enrollment);

        if ($enrollment->final_result_status !== StudentEnrollment::FINAL_APPROVED) {
            return $this->blocked($enrollment, 'Declaração de conclusão bloqueada: o resultado final da matrícula precisa estar aprovado.');
        }

        return $this->declarationPdf(
            $request,
            $academicYear,
            $class,
            $enrollment,
            'student-completion-declaration',
            'Declaração de conclusão',
            'Declaramos que o(a) estudante concluiu com aprovação a etapa/matriz indicada nesta matrícula.'
        );
    }

    public function attendance(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        [$academicYear, $class] = $this->context($request, $enrollment);

        if ($redirect = $this->complianceRedirect($request, $academicYear, $enrollment)) {
            return $redirect;
        }

        $scope = $this->attendanceScope($request, $academicYear);
        $report = $this->attendanceCertificateBuilder->build(
            $enrollment,
            $scope['starts_at'],
            $scope['ends_at'],
            $scope['period'],
        );
        $attendance = $report['attendance'];
        $issuedDocument = $this->issuedDocument($request, $academicYear, $enrollment, 'student-attendance-certificate', [
            'title' => 'Atestado de frequência',
            'scope' => $scope['type'],
            'scope_label' => $scope['label'],
            'starts_at' => $scope['starts_at']->toDateString(),
            'ends_at' => $scope['ends_at']->toDateString(),
            'attendance_percentage' => $attendance['percentage'],
            'lessons' => $attendance['lessons'],
            'matrices' => $report['matrices']->map(fn (array $matrix): array => [
                'course_id' => $matrix['course']->id,
                'course' => $matrix['course']->name,
                'stage' => $matrix['stage'],
                'attendance_percentage' => $matrix['attendance']['percentage'],
            ])->all(),
        ]);

        $pdf = Pdf::loadView('reports.certificates.attendance', [
            'academicYear' => $academicYear,
            'class' => $class,
            'enrollment' => $enrollment,
            'attendance' => $attendance,
            'matrixAttendance' => $report['matrices'],
            'scope' => $scope,
            'issuedDocument' => $issuedDocument,
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('beaba-atestado-frequencia-'.$scope['filename'].'-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function transfer(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        [$academicYear, $class] = $this->context($request, $enrollment);

        if ($enrollment->status !== StudentEnrollment::STATUS_TRANSFERRED || ! $enrollment->transferred_at) {
            return $this->blocked($enrollment, 'Atestado de transferência bloqueado: registre a transferência da matrícula antes de emitir o documento.');
        }

        if ($redirect = $this->complianceRedirect($request, $academicYear, $enrollment)) {
            return $redirect;
        }

        $issuedDocument = $this->issuedDocument($request, $academicYear, $enrollment, 'student-transfer-certificate', [
            'title' => 'Atestado de transferência',
            'transferred_at' => $enrollment->transferred_at?->toDateString(),
        ]);

        $pdf = Pdf::loadView('reports.certificates.transfer', [
            'academicYear' => $academicYear,
            'class' => $class,
            'enrollment' => $enrollment,
            'issuedDocument' => $issuedDocument,
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('beaba-atestado-transferencia-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function declarationPdf(
        Request $request,
        AcademicYear $academicYear,
        SchoolClass $class,
        StudentEnrollment $enrollment,
        string $type,
        string $title,
        string $statement
    ): Response|RedirectResponse {
        if ($redirect = $this->complianceRedirect($request, $academicYear, $enrollment)) {
            return $redirect;
        }

        $issuedDocument = $this->issuedDocument($request, $academicYear, $enrollment, $type, [
            'title' => $title,
        ]);

        $pdf = Pdf::loadView('reports.certificates.declaration', [
            'academicYear' => $academicYear,
            'class' => $class,
            'enrollment' => $enrollment,
            'issuedDocument' => $issuedDocument,
            'letterhead' => PdfLetterhead::make($academicYear->school),
            'title' => $title,
            'statement' => $statement,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('beaba-'.Str::slug($title).'-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array{0: AcademicYear, 1: SchoolClass}
     */
    private function context(Request $request, StudentEnrollment $enrollment): array
    {
        $enrollment->load([
            'student.contacts',
            'courses',
            'schoolClass.academicYear.school',
            'enrolledBy',
            'transferredBy',
        ]);

        $class = $enrollment->schoolClass;
        $academicYear = $class->academicYear;

        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return [$academicYear, $class];
    }

    private function complianceRedirect(Request $request, AcademicYear $academicYear, StudentEnrollment $enrollment): ?RedirectResponse
    {
        if ($message = OfficialDocumentCompliance::studentMessage($enrollment->student, $request->boolean('confirm_missing_student_cpf'))) {
            return redirect()->route('people.show', $enrollment->student)->with('status', $message);
        }

        if ($message = OfficialDocumentCompliance::schoolMessage($academicYear->school)) {
            return redirect()->route('schools.edit', $academicYear->school)->with('status', $message);
        }

        return null;
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     filename: string,
     *     starts_at: CarbonInterface,
     *     ends_at: CarbonInterface,
     *     period: AcademicPeriod|null
     * }
     */
    private function attendanceScope(Request $request, AcademicYear $academicYear): array
    {
        $data = $request->validate([
            'attendance_scope' => ['nullable', Rule::in(['annual', 'period', 'month'])],
            'academic_period_id' => ['nullable', 'integer'],
            'attendance_month' => ['nullable', 'date_format:Y-m'],
        ]);
        $type = $data['attendance_scope'] ?? 'annual';
        $yearStartsAt = $academicYear->starts_at?->toImmutable()
            ?? CarbonImmutable::create((int) $academicYear->reference_year, 1, 1);
        $yearEndsAt = $academicYear->ends_at?->toImmutable()
            ?? CarbonImmutable::create((int) $academicYear->reference_year, 12, 31);

        if ($type === 'period') {
            if (empty($data['academic_period_id'])) {
                throw ValidationException::withMessages([
                    'academic_period_id' => 'Selecione o período avaliativo do atestado.',
                ]);
            }

            $period = $academicYear->periods()->find($data['academic_period_id']);

            if (! $period) {
                throw ValidationException::withMessages([
                    'academic_period_id' => 'O período selecionado não pertence a este ano letivo.',
                ]);
            }

            return [
                'type' => $type,
                'label' => 'Período avaliativo: '.$period->name,
                'filename' => 'periodo-'.$period->id,
                'starts_at' => $period->starts_at->toImmutable(),
                'ends_at' => $period->ends_at->toImmutable(),
                'period' => $period,
            ];
        }

        if ($type === 'month') {
            if (empty($data['attendance_month'])) {
                throw ValidationException::withMessages([
                    'attendance_month' => 'Selecione o mês do atestado.',
                ]);
            }

            $monthStartsAt = CarbonImmutable::createFromFormat('Y-m-d', $data['attendance_month'].'-01')->startOfMonth();
            $monthEndsAt = $monthStartsAt->endOfMonth();

            if ($monthEndsAt->isBefore($yearStartsAt) || $monthStartsAt->isAfter($yearEndsAt)) {
                throw ValidationException::withMessages([
                    'attendance_month' => 'O mês selecionado está fora da duração deste ano letivo.',
                ]);
            }

            return [
                'type' => $type,
                'label' => 'Mensal: '.Str::ucfirst($monthStartsAt->locale('pt_BR')->translatedFormat('F \d\e Y')),
                'filename' => 'mes-'.$data['attendance_month'],
                'starts_at' => $monthStartsAt->isAfter($yearStartsAt) ? $monthStartsAt : $yearStartsAt,
                'ends_at' => $monthEndsAt->isBefore($yearEndsAt) ? $monthEndsAt : $yearEndsAt,
                'period' => null,
            ];
        }

        return [
            'type' => 'annual',
            'label' => 'Ano letivo completo',
            'filename' => 'anual',
            'starts_at' => $yearStartsAt,
            'ends_at' => $yearEndsAt,
            'period' => null,
        ];
    }

    private function blocked(StudentEnrollment $enrollment, string $message): RedirectResponse
    {
        return redirect()->route('enrollments.documents', $enrollment)->with('status', $message);
    }

    /**
     * @return array<int, array{label: string, ok: bool, message: string, severity: string}>
     */
    private function documentChecks(AcademicYear $academicYear, StudentEnrollment $enrollment): array
    {
        $personMessage = OfficialDocumentCompliance::studentMessage($enrollment->student, true);
        $studentHasNoCpf = OfficialDocumentCompliance::studentHasNoCpf($enrollment->student);
        $schoolMessage = OfficialDocumentCompliance::schoolMessage($academicYear->school);
        $hasFinalResult = $enrollment->final_result_status !== null
            && $enrollment->final_result_status !== StudentEnrollment::FINAL_PENDING;

        return [
            [
                'label' => 'Cadastro do estudante',
                'ok' => $personMessage === null,
                'message' => $personMessage
                    ?? ($studentHasNoCpf
                        ? 'Estudante sem CPF. A emissão será permitida mediante confirmação, pois há CPF da mãe ou do pai cadastrado.'
                        : 'Dados civis mínimos preenchidos para emissão oficial.'),
                'severity' => $personMessage ? 'danger' : ($studentHasNoCpf ? 'warning' : 'success'),
            ],
            [
                'label' => 'Papel timbrado da escola',
                'ok' => $schoolMessage === null,
                'message' => $schoolMessage ?? 'Dados oficiais da escola preenchidos.',
                'severity' => $schoolMessage ? 'danger' : 'success',
            ],
            [
                'label' => 'Matriz vinculada',
                'ok' => $enrollment->courses->isNotEmpty(),
                'message' => $enrollment->courses->isNotEmpty()
                    ? 'Matrícula ligada a '.$enrollment->courses->pluck('name')->join(' + ').'.'
                    : 'Vincule ao menos uma matriz/curso à matrícula.',
                'severity' => $enrollment->courses->isNotEmpty() ? 'success' : 'danger',
            ],
            [
                'label' => 'Resultado final',
                'ok' => $hasFinalResult,
                'message' => $hasFinalResult
                    ? 'Resultado final: '.$enrollment->finalResultLabel().'.'
                    : 'Ainda sem resultado final calculado; documentos finais ficam bloqueados.',
                'severity' => $hasFinalResult ? 'success' : 'warning',
            ],
            [
                'label' => 'Situação da matrícula',
                'ok' => true,
                'message' => $enrollment->statusLabel().' desde '.($enrollment->enrolled_at?->format('d/m/Y') ?? 'data não informada').'.',
                'severity' => $enrollment->isActive() ? 'success' : 'info',
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, description: string, route: string, icon: string, enabled: bool, reason: string|null}>
     */
    private function documentOptions(StudentEnrollment $enrollment): array
    {
        return [
            [
                'title' => 'Declaração de matrícula',
                'description' => 'Comprova matrícula ativa do estudante.',
                'route' => route('enrollments.enrollment-declaration.pdf', $enrollment),
                'icon' => 'fa-id-card',
                'enabled' => $enrollment->isActive(),
                'reason' => $enrollment->isActive() ? null : 'Disponível apenas para matrícula ativa.',
            ],
            [
                'title' => 'Declaração de escolaridade',
                'description' => 'Comprova vínculo escolar atual ou histórico na matrícula.',
                'route' => route('enrollments.schooling-declaration.pdf', $enrollment),
                'icon' => 'fa-school',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Declaração de conclusão',
                'description' => 'Comprova conclusão aprovada da etapa/matriz.',
                'route' => route('enrollments.completion-declaration.pdf', $enrollment),
                'icon' => 'fa-award',
                'enabled' => $enrollment->final_result_status === StudentEnrollment::FINAL_APPROVED,
                'reason' => $enrollment->final_result_status === StudentEnrollment::FINAL_APPROVED ? null : 'Exige resultado final aprovado.',
            ],
            [
                'title' => 'Atestado de frequência',
                'description' => 'Resume a frequência anual. Os recortes mensal e por período estão disponíveis na Central de emissão.',
                'route' => route('enrollments.attendance-certificate.pdf', $enrollment),
                'icon' => 'fa-user-check',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Atestado de transferência',
                'description' => 'Emitido após registro formal de transferência.',
                'route' => route('enrollments.transfer-certificate.pdf', $enrollment),
                'icon' => 'fa-exchange-alt',
                'enabled' => $enrollment->status === StudentEnrollment::STATUS_TRANSFERRED && $enrollment->transferred_at !== null,
                'reason' => $enrollment->status === StudentEnrollment::STATUS_TRANSFERRED && $enrollment->transferred_at !== null ? null : 'Registre a transferência antes de emitir.',
            ],
            [
                'title' => 'Ficha de matrícula',
                'description' => 'Ficha física de matrícula com dados cadastrais e responsáveis.',
                'route' => route('enrollments.pdf', $enrollment),
                'icon' => 'fa-file-signature',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Boletim escolar',
                'description' => 'Resultados por período, frequência e comportamento.',
                'route' => route('enrollments.report-card.pdf', $enrollment),
                'icon' => 'fa-chart-line',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Ficha individual',
                'description' => 'Documento acadêmico individual completo.',
                'route' => route('enrollments.individual-record.pdf', $enrollment),
                'icon' => 'fa-file-alt',
                'enabled' => true,
                'reason' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function issuedDocument(Request $request, AcademicYear $academicYear, StudentEnrollment $enrollment, string $type, array $payload): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => $type,
            'person_id' => $enrollment->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => $payload + [
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $enrollment->school_class_id,
                'student_enrollment_id' => $enrollment->id,
            ],
            'issued_at' => now(),
        ]);
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
