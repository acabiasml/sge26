<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Support\OfficialDocumentCompliance;
use App\Support\CredentialRegistry;
use App\Support\HighSchoolCompletion;
use App\Support\PdfLetterhead;
use App\Support\PdfMetadata;
use App\Support\SchoolRecordPdf;
use App\Support\StudentAttendanceCertificateBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            return $this->blocked($enrollment, __('Declaração de matrícula bloqueada: a matrícula precisa estar ativa.'));
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
            return $this->blocked($enrollment, __('Declaração de conclusão bloqueada: o resultado final da matrícula precisa estar aprovado.'));
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
            'letterhead' => PdfLetterhead::forEnrollment($enrollment),
        ])->setPaper('a4', 'portrait');

        return PdfMetadata::stream($pdf, 'beaba-atestado-frequencia-'.$scope['filename'].'-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function transfer(Request $request, StudentEnrollment $enrollment): Response|RedirectResponse
    {
        [$academicYear, $class] = $this->context($request, $enrollment);
        if (! $request->boolean('confirm_missing_student_cpf')
            && OfficialDocumentCompliance::studentHasNoCpf($enrollment->student)
            && OfficialDocumentCompliance::studentMessage($enrollment->student, true) === null) {
            return response()->view('student-enrollments.confirm-transfer-certificate', ['enrollment' => $enrollment]);
        }

        if ($redirect = $this->complianceRedirect($request, $academicYear, $enrollment)) {
            return $redirect;
        }

        $authorization = OfficialDocumentCompliance::courseAuthorizationDetails($academicYear->school, $enrollment->courses, $academicYear->reference_year);
        if ($message = $authorization['message']) {
            return $this->blocked($enrollment, $message);
        }

        $payload = [
            'authorization_renewal_notices' => $authorization['renewal_notices'],
            'title' => 'Atestado de transferência', 'enrollment_id' => $enrollment->id,
            'student' => ['full_name' => $enrollment->student->full_name],
            'enrollment_status' => $enrollment->status,
            'transferred_at' => $enrollment->transferred_at?->toDateString(),
        ];
        return DB::transaction(function () use ($request, $academicYear, $class, $enrollment, $payload): Response {
            $document = $this->issuedDocument($request, $academicYear, $enrollment, 'student-transfer-certificate', $payload);
            $pdf = Pdf::loadView('reports.certificates.transfer', [
                'academicYear' => $academicYear, 'class' => $class, 'enrollment' => $enrollment,
                'issuedDocument' => $document, 'letterhead' => PdfLetterhead::forEnrollment($enrollment),
            ])->setPaper('a4', 'portrait');

            return PdfMetadata::stream($pdf, 'beaba-atestado-transferencia-'.$enrollment->id.'.pdf');
        });
    }

    public function highSchoolCertificate(Request $request, StudentEnrollment $enrollment, HighSchoolCompletion $completion, SchoolRecordPdf $records): View|Response
    {
        [$year, $class] = $this->context($request, $enrollment);
        $course = $completion->course($enrollment);
        if (! $course) {
            throw ValidationException::withMessages(['document' => 'A certificação exige a última série do Ensino Médio com resultado aprovado, em ano letivo encerrado.']);
        }
        $scope = $course->modality ?: AcademicCourse::MODALITY_REGULAR;
        $type = 'high-school-completion-certificate';
        $title = 'Certificado de conclusão do Ensino Médio';
        $authorization = OfficialDocumentCompliance::courseAuthorizationDetails($year->school, [$course], $year->reference_year);
        $payload = [
            'authorization_renewal_notices' => $authorization['renewal_notices'],
            'title' => $title, 'enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'modality' => $scope,
            'student' => ['full_name' => $enrollment->student->full_name],
        ];
        $render = function (IssuedDocument $document) use ($year, $class, $enrollment, $course, $title, $authorization) {
            $pdf = Pdf::loadView('reports.certificates.declaration', [
                'academicYear' => $year, 'class' => $class, 'enrollment' => $enrollment,
                'issuedDocument' => $document, 'letterhead' => PdfLetterhead::make($year->school, AcademicCourse::STAGE_HIGH_SCHOOL, $authorization),
                'title' => $title, 'statement' => 'Certificamos a conclusão do Ensino Médio, modalidade '.$course->modalityLabel().', com resultado final aprovado, nos termos do art. 24, VII, da Lei nº 9.394/1996.',
            ])->setPaper('a4', 'portrait');

            return PdfMetadata::stream($pdf, 'beaba-certificado-ensino-medio-'.$enrollment->id.'.pdf');
        };
        if ($request->isMethod('get') && $request->boolean('preview')) {
            return $render($records->draft($request->user(), $year->school, $type, $enrollment->person_id, $payload));
        }
        if ($request->isMethod('get')) {
            return view('credential-registry.prepare-record', [
                'school' => $year->school, 'title' => $title, 'context' => $enrollment->student->full_name.' · '.$course->modalityLabel(),
                'notice' => 'Selecione o livro da modalidade e o histórico que comprova a conclusão.',
                'preview' => route('enrollments.high-school-certificate.prepare', ['enrollment' => $enrollment, 'preview' => 1]),
                'action' => route('enrollments.high-school-certificate.register', $enrollment),
                'books' => app(CredentialRegistry::class)->digitalBooks($request->user(), $year->school, 'high-school')
                    ->where('scope', $scope)->get(),
                'confirmMissingCpf' => OfficialDocumentCompliance::studentHasNoCpf($enrollment->student),
                'requireHistory' => true,
                'digitalOnly' => true,
            ]);
        }
        if ($redirect = $this->complianceRedirect($request, $year, $enrollment)) {
            return $redirect;
        }
        if ($message = $authorization['message']) {
            throw ValidationException::withMessages(['document' => $message]);
        }
        $data = $request->validate(['signature_mode' => ['required', 'in:digital']] + SchoolRecordPdf::rules($year->school) +
            ['history_code' => [$year->school->usesPhysicalRegistry() ? 'required_without:paper_history_pdf' : 'required', 'nullable', 'string', 'max:60']]);
        $evidence = [];
        if (filled($data['history_code'] ?? null)) {
            $history = $records->signedHistory($data['history_code'], $enrollment->person_id, $year->school_id, AcademicCourse::STAGE_HIGH_SCHOOL);
            if (empty($history->snapshot['high_school_completion']) || ! in_array($enrollment->id, $history->snapshot['completed_enrollment_ids'] ?? [], true)) {
                throw ValidationException::withMessages(['history_code' => 'O histórico assinado precisa comprovar a conclusão desta matrícula e conter os dados de ingresso e a data de conclusão.']);
            }
            $payload['history_registration_id'] = $history->id;
        } elseif ($year->school->usesPhysicalRegistry()) {
            $paperHistory = $request->validate([
                'paper_history_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
                'paper_history_reference' => ['required', 'string', 'max:250'],
                'paper_history_reviewed' => ['accepted'],
            ]);
            $payload['paper_history_reference'] = $paperHistory['paper_history_reference'];
            $evidence['signed_high_school_history'] = $request->file('paper_history_pdf')->get();
        }
        $staff = $records->signers($year->school);
        $payload['signers'] = ['director' => $staff['diretor']->full_name, 'secretary' => $staff['secretário']->full_name];
        $document = $records->issue($request->user(), $year->school, $type, $enrollment->person_id, $payload, $data, $render, $scope, $evidence);

        return redirect()->route('credential-registry.entry', $document->registration);
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
            'letterhead' => PdfLetterhead::forEnrollment($enrollment),
            'title' => $title,
            'statement' => $statement,
        ])->setPaper('a4', 'portrait');

        return PdfMetadata::stream($pdf, 'beaba-'.Str::slug($title).'-'.$enrollment->id.'-'.now()->format('Ymd-His').'.pdf', $title.' - '.$enrollment->student->full_name.' - Beabá');
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
                    'academic_period_id' => __('Selecione o período avaliativo do atestado.'),
                ]);
            }

            $period = $academicYear->periods()->find($data['academic_period_id']);

            if (! $period) {
                throw ValidationException::withMessages([
                    'academic_period_id' => __('O período selecionado não pertence a este ano letivo.'),
                ]);
            }

            return [
                'type' => $type,
                'label' => __('Período avaliativo: ').$period->name,
                'filename' => 'periodo-'.$period->id,
                'starts_at' => $period->starts_at->toImmutable(),
                'ends_at' => $period->ends_at->toImmutable(),
                'period' => $period,
            ];
        }

        if ($type === 'month') {
            if (empty($data['attendance_month'])) {
                throw ValidationException::withMessages([
                    'attendance_month' => __('Selecione o mês do atestado.'),
                ]);
            }

            $monthStartsAt = CarbonImmutable::createFromFormat('Y-m-d', $data['attendance_month'].'-01')->startOfMonth();
            $monthEndsAt = $monthStartsAt->endOfMonth();

            if ($monthEndsAt->isBefore($yearStartsAt) || $monthStartsAt->isAfter($yearEndsAt)) {
                throw ValidationException::withMessages([
                    'attendance_month' => __('O mês selecionado está fora da duração deste ano letivo.'),
                ]);
            }

            return [
                'type' => $type,
                'label' => __('Mensal: ').Str::ucfirst($monthStartsAt->locale('pt_BR')->translatedFormat('F \d\e Y')),
                'filename' => 'mes-'.$data['attendance_month'],
                'starts_at' => $monthStartsAt->isAfter($yearStartsAt) ? $monthStartsAt : $yearStartsAt,
                'ends_at' => $monthEndsAt->isBefore($yearEndsAt) ? $monthEndsAt : $yearEndsAt,
                'period' => null,
            ];
        }

        return [
            'type' => 'annual',
            'label' => __('Ano letivo completo'),
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
                'label' => __('Cadastro do estudante'),
                'ok' => $personMessage === null,
                'message' => $personMessage
                    ?? ($studentHasNoCpf
                        ? __('Estudante sem CPF. A emissão será permitida mediante confirmação, pois há CPF da mãe ou do pai cadastrado.')
                        : __('Dados civis mínimos preenchidos para emissão oficial.')),
                'severity' => $personMessage ? 'danger' : ($studentHasNoCpf ? 'warning' : 'success'),
            ],
            [
                'label' => __('Papel timbrado da escola'),
                'ok' => $schoolMessage === null,
                'message' => $schoolMessage ?? __('Dados oficiais da escola preenchidos.'),
                'severity' => $schoolMessage ? 'danger' : 'success',
            ],
            [
                'label' => __('Matriz vinculada'),
                'ok' => $enrollment->courses->isNotEmpty(),
                'message' => $enrollment->courses->isNotEmpty()
                    ? __('Matrícula ligada a ').$enrollment->courses->pluck('name')->join(' + ').'.'
                    : __('Vincule ao menos uma matriz/curso à matrícula.'),
                'severity' => $enrollment->courses->isNotEmpty() ? 'success' : 'danger',
            ],
            [
                'label' => 'Resultado final',
                'ok' => $hasFinalResult,
                'message' => $hasFinalResult
                    ? __('Resultado final: ').__($enrollment->finalResultLabel()).'.'
                    : __('Ainda sem resultado final calculado; documentos finais ficam bloqueados.'),
                'severity' => $hasFinalResult ? 'success' : 'warning',
            ],
            [
                'label' => __('Situação da matrícula'),
                'ok' => true,
                'message' => __($enrollment->statusLabel()).__(' desde ').($enrollment->enrolled_at?->format('d/m/Y') ?? 'data não informada').'.',
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
                'description' => __('Comprova matrícula ativa do estudante.'),
                'route' => route('enrollments.enrollment-declaration.pdf', $enrollment),
                'icon' => 'fa-id-card',
                'enabled' => $enrollment->isActive(),
                'reason' => $enrollment->isActive() ? null : __('Disponível apenas para matrícula ativa.'),
            ],
            [
                'title' => 'Declaração de escolaridade',
                'description' => __('Comprova vínculo escolar atual ou histórico na matrícula.'),
                'route' => route('enrollments.schooling-declaration.pdf', $enrollment),
                'icon' => 'fa-school',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Declaração de conclusão',
                'description' => __('Comprova conclusão aprovada da etapa/matriz.'),
                'route' => route('enrollments.completion-declaration.pdf', $enrollment),
                'icon' => 'fa-award',
                'enabled' => $enrollment->final_result_status === StudentEnrollment::FINAL_APPROVED,
                'reason' => $enrollment->final_result_status === StudentEnrollment::FINAL_APPROVED ? null : __('Exige resultado final aprovado.'),
            ],
            [
                'title' => 'Certificado de conclusão do Ensino Médio',
                'description' => __('Registra a conclusão no livro da modalidade com o histórico assinado.'),
                'route' => route('enrollments.high-school-certificate.prepare', $enrollment),
                'icon' => 'fa-certificate',
                'enabled' => (bool) app(HighSchoolCompletion::class)->course($enrollment),
                'reason' => __('Exige conclusão da última série em ano letivo encerrado.'),
            ],
            [
                'title' => 'Atestado de frequência',
                'description' => __('Resume a frequência anual. Os recortes mensal e por período estão disponíveis na Central de emissão.'),
                'route' => route('enrollments.attendance-certificate.pdf', $enrollment),
                'icon' => 'fa-user-check',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Atestado de transferência',
                'description' => __('Pode ser emitido antes ou depois da transferência, sem alterar a matrícula.'),
                'route' => route('enrollments.transfer-certificate.pdf', $enrollment),
                'icon' => 'fa-exchange-alt',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Transferência recebida',
                'description' => __('Registra a documentação da escola de origem.'),
                'route' => route('enrollments.received-transfer', $enrollment),
                'icon' => 'fa-file-import',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Processo especial',
                'description' => __('Arquiva os atos de classificação, reclassificação e enturmação.'),
                'route' => route('enrollments.special-process', $enrollment),
                'icon' => 'fa-folder',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Ficha de matrícula',
                'description' => __('Ficha física de matrícula com dados cadastrais e responsáveis.'),
                'route' => route('enrollments.pdf', $enrollment),
                'icon' => 'fa-file-signature',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Boletim escolar',
                'description' => __('Resultados por período, frequência e comportamento.'),
                'route' => route('enrollments.report-card.pdf', $enrollment),
                'icon' => 'fa-chart-line',
                'enabled' => true,
                'reason' => null,
            ],
            [
                'title' => 'Ficha individual',
                'description' => __('Documento acadêmico individual completo.'),
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
