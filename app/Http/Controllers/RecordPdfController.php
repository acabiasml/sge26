<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordPdfController extends Controller
{
    public function school(Request $request, School $school): Response
    {
        abort_unless($request->user()->canManageSchools(), 403);

        $issuedDocument = $this->issuedDocument($request, 'school-record', 'Ficha da escola', $school->id);

        $pdf = Pdf::loadView('reports.records.school', [
            'school' => $school->load('roles.person'),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
        ])->setPaper('a4');

        return $pdf->download('beaba-ficha-escola-'.$school->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function person(Request $request, Person $person): Response
    {
        abort_unless($this->canSeePerson($request, $person), 403);

        $issuedDocument = $this->issuedDocument($request, 'person-record', 'Ficha da pessoa', null, $person->id);

        $pdf = Pdf::loadView('reports.records.person', [
            'person' => $person->load(['schoolRoles.school', 'relationships.relatedPerson']),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
        ])->setPaper('a4');

        return $pdf->download('beaba-ficha-pessoa-'.$person->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function canSeePerson(Request $request, Person $person): bool
    {
        $user = $request->user();

        if ($user->isAdministrator()) {
            return true;
        }

        return $person->schoolRoles()
            ->whereIn('school_id', $user->manageableSchoolIds())
            ->exists();
    }

    private function issuedDocument(Request $request, string $type, string $title, ?int $schoolId = null, ?int $personId = null): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => $type,
            'person_id' => $personId ?? $request->user()->person_id,
            'school_id' => $schoolId,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $title,
                'school_id' => $schoolId,
                'person_id' => $personId,
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
