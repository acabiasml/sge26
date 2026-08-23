<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordPdfController extends Controller
{
    public function school(Request $request, School $school): Response|RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        if ($message = OfficialDocumentCompliance::schoolMessage($school)) {
            return redirect()->route($request->user()->canManageSchools() ? 'schools.edit' : 'schools.academic-years.index', $school)->with('status', $message);
        }

        $issuedDocument = $this->issuedDocument($request, 'school-record', 'Ficha da escola', $school->id);

        $pdf = Pdf::loadView('reports.records.school', [
            'school' => $school->load('roles.person'),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($school),
        ])->setPaper('a4', 'portrait');

        return \App\Support\PdfMetadata::stream($pdf, 'beaba-ficha-escola-'.$school->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function person(Request $request, Person $person): Response|RedirectResponse
    {
        abort_unless($this->canSeePerson($request, $person), 403);

        $person->load(['schoolRoles.school', 'contacts']);

        if (! $person->hasActiveRoleForDate() && ! $person->hasRequiredIdentityForOfficialUse()) {
            return redirect()->route('people.show', $person)
                ->with('status', 'Não é possível emitir documento de pessoa inativa sem CPF.');
        }

        $school = $this->schoolForPerson($person);

        if ($message = OfficialDocumentCompliance::personMessage($person)) {
            return redirect()->route('people.show', $person)->with('status', $message);
        }

        if ($school && $message = OfficialDocumentCompliance::schoolMessage($school)) {
            return redirect()->route($request->user()->canManageSchools() ? 'schools.edit' : 'schools.academic-years.index', $school)->with('status', $message);
        }

        $issuedDocument = $this->issuedDocument($request, 'person-record', 'Ficha da pessoa', $school?->id, $person->id);

        $pdf = Pdf::loadView('reports.records.person', [
            'person' => $person,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($school),
        ])->setPaper('a4', 'portrait');

        return \App\Support\PdfMetadata::stream($pdf, 'beaba-ficha-pessoa-'.$person->id.'-'.now()->format('Ymd-His').'.pdf');
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

    private function schoolForPerson(Person $person): ?School
    {
        if ($person->schoolRoles->contains(fn ($role): bool => $role->school_id === null
            && $role->role === PersonSchoolRole::ROLE_ADMINISTRATOR
            && $role->isActiveForDate())) {
            return null;
        }

        return $person->schoolRoles
            ->filter(fn ($role): bool => $role->school !== null && $role->isActiveForDate())
            ->sortByDesc(fn ($role): int => PersonSchoolRole::ROLE_PRIORITY[$role->role] ?? 0)
            ->first()
            ?->school
            ?? $person->schoolRoles->first(fn ($role): bool => $role->school !== null)?->school;
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
