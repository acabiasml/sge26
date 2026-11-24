<?php

namespace App\Http\Controllers;

use App\Models\StudentEnrollment;
use App\Support\CredentialRegistry;
use App\Support\ReclassificationProcess;
use App\Support\SchoolRecordPdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolRecordEvidenceController extends Controller
{
    private const PROCESS_FIELDS = [
        'evaluation_result' => 'Resultado da avaliação e decisão',
        'justification' => 'Justificativa fundamentada',
        'procedures' => 'Procedimentos e critérios de avaliação',
    ];

    private const RECLASSIFICATION_SIGNERS = [
        'management_signers' => 'Equipe gestora que assinou a súmula',
        'council_signers' => 'Conselho de Classe ou equivalente que assinou a súmula',
        'teacher_signers' => 'Professores envolvidos que assinaram a súmula',
    ];

    public function receivedTransfer(Request $request, StudentEnrollment $enrollment, CredentialRegistry $registry, SchoolRecordPdf $records)
    {
        return $this->record($request, $enrollment, $registry, $records, 'received-transfer');
    }

    public function specialProcess(Request $request, StudentEnrollment $enrollment, CredentialRegistry $registry, SchoolRecordPdf $records)
    {
        return $this->record($request, $enrollment, $registry, $records, 'school-special-process');
    }

    private function record(Request $request, StudentEnrollment $enrollment, CredentialRegistry $registry, SchoolRecordPdf $records, string $type)
    {
        $enrollment->loadMissing('schoolClass.academicYear.school', 'student');
        $year = $enrollment->schoolClass->academicYear;
        $school = $year->school;
        $registry->authorize($request->user(), $school->id);
        $transfer = $type === 'received-transfer';
        $kind = CredentialRegistry::TYPES[$type];
        $title = $transfer ? 'Transferência recebida' : 'Processo especial';
        if ($request->isMethod('get')) {
            return view('credential-registry.prepare-record', [
                'school' => $school, 'title' => $title, 'context' => $enrollment->student->full_name.' · '.$enrollment->schoolClass->name,
                'notice' => $transfer
                    ? 'Arquive o histórico ou atestado recebido. Quando a transferência ocorrer no meio do período letivo, anexe também a ficha individual.'
                    : 'Arquive a ata individual, a súmula assinada e os documentos que fundamentam o processo. Registre o fato no histórico do estudante.',
                'action' => $request->url(),
                'books' => $registry->digitalBooks($request->user(), $school, $kind)->get(),
                'evidenceType' => $type,
                'processFields' => self::PROCESS_FIELDS,
                'summarySigners' => self::RECLASSIFICATION_SIGNERS,
                'reclassificationCourses' => $transfer ? collect() : $year->courses()
                    ->whereIn('stage', ReclassificationProcess::BASIC_STAGES)->orderBy('name')->get(),
            ]);
        }
        $rules = SchoolRecordPdf::rules($school) + [
            'origin' => ['required', 'string', 'max:250'], 'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'process_kind' => [$transfer ? 'exclude' : 'required', 'in:classification,reclassification,placement'],
            'transfer_during_period' => [$transfer ? 'required' : 'exclude', 'boolean'],
            'original_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'supporting_pdf' => [$transfer ? 'required_if:transfer_during_period,1' : 'required', 'nullable', 'file', 'mimes:pdf', 'max:20480'],
            'reviewed' => ['accepted'],
        ];
        if (! $transfer) {
            foreach (self::PROCESS_FIELDS as $field => $label) {
                $rules[$field] = ['required', 'string', 'max:6000'];
            }
            foreach (self::RECLASSIFICATION_SIGNERS as $field => $label) {
                $rules[$field] = ['exclude_unless:process_kind,reclassification', 'required', 'string', 'max:2000'];
            }
            $rules['summary_pdf'] = ['exclude_unless:process_kind,reclassification', 'required', 'file', 'mimes:pdf', 'max:20480'];
            $rules['reclassification_decision'] = ['exclude_unless:process_kind,reclassification', 'required', 'in:approved,denied'];
            $rules['reclassification_ground'] = ['exclude_unless:process_kind,reclassification', 'required', Rule::in(array_keys(ReclassificationProcess::GROUNDS))];
            $rules['reclassification_target_course_id'] = ['exclude_unless:reclassification_decision,approved', 'exclude_unless:process_kind,reclassification',
                'required', 'integer', Rule::exists('academic_courses', 'id')->where(fn ($query) => $query
                    ->where('academic_year_id', $year->id)->whereIn('stage', ReclassificationProcess::BASIC_STAGES))];
            $rules['reclassification_criteria_reviewed'] = ['exclude_unless:reclassification_decision,approved', 'exclude_unless:process_kind,reclassification', 'accepted'];
            $rules['reclassification_attendance'] = ['exclude_unless:reclassification_ground,attendance',
                'exclude_unless:reclassification_decision,approved', 'exclude_unless:process_kind,reclassification', 'required', 'numeric', 'between:75,100'];
        }
        $data = $request->validate($rules);
        $payload = [
            'title' => $title, 'student' => ['full_name' => $enrollment->student->full_name],
            'enrollment_id' => $enrollment->id, 'original_source' => $data['origin'],
            'act_date' => $data['recorded_on'], 'process_kind' => $data['process_kind'] ?? null,
            'education_stages' => $enrollment->courses->pluck('stage')->unique()->values()->all(),
            'transfer_during_period' => $data['transfer_during_period'] ?? null, 'original_received' => true,
        ];
        $evidence = $request->hasFile('supporting_pdf') ? ['supporting_document' => $request->file('supporting_pdf')->get()] : [];
        if (! $transfer) {
            $payload['registry_subject'] = $enrollment->student->full_name.' · '.$data['origin'];
            foreach (self::PROCESS_FIELDS + self::RECLASSIFICATION_SIGNERS as $field => $label) {
                if (isset($data[$field])) {
                    $payload['process_fields'][$label] = $data[$field];
                }
            }
            if ($data['process_kind'] === 'reclassification') {
                $payload['reclassification_decision'] = $data['reclassification_decision'];
                $payload['reclassification_ground'] = $data['reclassification_ground'];
                $payload['process_fields']['Decisão'] = $data['reclassification_decision'] === 'approved' ? 'Deferida' : 'Indeferida';
                $payload['process_fields']['Hipótese de reclassificação'] = ReclassificationProcess::GROUNDS[$data['reclassification_ground']];
                if (isset($data['reclassification_target_course_id'])) {
                    $target = $year->courses()->findOrFail($data['reclassification_target_course_id']);
                    $payload['reclassification_target_course_id'] = $target->id;
                    $payload['process_fields']['Matriz de destino deferida'] = $target->name;
                    $payload['process_fields']['Critérios pedagógicos e condução do processo'] = 'Conferidos pela secretaria.';
                }
                if (isset($data['reclassification_attendance'])) {
                    $payload['process_fields']['Frequência comprovada'] = $data['reclassification_attendance'].'%';
                }
            }
            if (isset($data['summary_pdf'])) {
                $evidence['signed_process_summary'] = $data['summary_pdf']->get();
            }
        }
        $document = $records->issue($request->user(), $school, $type, $enrollment->person_id, $payload, $data,
            fn () => $request->file('original_pdf')->get(), evidence: $evidence);

        return redirect()->route('credential-registry.entry', $document->registration);
    }
}
