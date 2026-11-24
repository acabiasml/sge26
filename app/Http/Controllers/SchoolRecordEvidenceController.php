<?php

namespace App\Http\Controllers;

use App\Models\StudentEnrollment;
use App\Support\CredentialRegistry;
use App\Support\SchoolRecordPdf;
use Illuminate\Http\Request;

class SchoolRecordEvidenceController extends Controller
{
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
            ]);
        }
        $data = $request->validate(SchoolRecordPdf::rules($school) + [
            'origin' => ['required', 'string', 'max:250'], 'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'process_kind' => [$transfer ? 'exclude' : 'required', 'in:classification,reclassification,placement'],
            'transfer_during_period' => [$transfer ? 'required' : 'exclude', 'boolean'],
            'original_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'supporting_pdf' => [$transfer ? 'required_if:transfer_during_period,1' : 'required', 'nullable', 'file', 'mimes:pdf', 'max:20480'],
            'reviewed' => ['accepted'],
        ]);
        $payload = [
            'title' => $title, 'student' => ['full_name' => $enrollment->student->full_name],
            'enrollment_id' => $enrollment->id, 'original_source' => $data['origin'],
            'act_date' => $data['recorded_on'], 'process_kind' => $data['process_kind'] ?? null,
            'education_stages' => $enrollment->courses->pluck('stage')->unique()->values()->all(),
            'transfer_during_period' => $data['transfer_during_period'] ?? null, 'original_received' => true,
        ];
        $evidence = $request->hasFile('supporting_pdf') ? ['supporting_document' => $request->file('supporting_pdf')->get()] : [];
        $document = $records->issue($request->user(), $school, $type, $enrollment->person_id, $payload, $data,
            fn () => $request->file('original_pdf')->get(), evidence: $evidence);

        return redirect()->route('credential-registry.entry', $document->registration);
    }
}
