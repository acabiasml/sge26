<?php

namespace App\Http\Controllers;

use App\Http\Requests\VolunteerReceiptRequest;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Support\BrazilianMoney;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\PdfMetadata;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RecordPdfController extends Controller
{
    public function volunteerReceipt(VolunteerReceiptRequest $request, Person $person): Response
    {
        $data = $request->validated();
        $school = School::query()->findOrFail($data['receipt_school_id']);
        abort_unless($request->user()->canManageSchool($school->id), 403);
        abort_unless($person->schoolRoles()->where('school_id', $school->id)->exists(), 404);

        $missing = collect(['full_name' => 'nome', 'cpf' => 'CPF', 'address' => 'endereço', 'city' => 'cidade', 'state' => 'UF'])
            ->filter(fn (string $label, string $field): bool => blank($person->{$field}))->values()->all();
        if ($missing !== []) {
            throw ValidationException::withMessages(['person' => 'Complete o cadastro da pessoa: '.implode(', ', $missing).'.']);
        }
        if ($message = OfficialDocumentCompliance::schoolMessage($school)) {
            throw ValidationException::withMessages(['receipt_school_id' => $message]);
        }

        $address = collect([$person->address, $person->number, $person->address_complement, $person->district,
            trim($person->city.'-'.$person->state)])->filter()->join(', ');
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $periodStart = Carbon::createFromFormat('!Y-m-d', $data['period_start']);
        $periodEnd = Carbon::createFromFormat('!Y-m-d', $data['period_end']);
        $periodLabel = $periodStart->isSameMonth($periodEnd) && $periodStart->day === 1 && $periodEnd->isLastOfMonth()
            ? $periodStart->locale('pt_BR')->translatedFormat('F \\d\\e Y')
            : $periodStart->format('d/m/Y').' a '.$periodEnd->format('d/m/Y');
        $issuedDate = Carbon::createFromFormat('!Y-m-d', $data['issued_date']);
        $amountWords = BrazilianMoney::words($amount);
        $issuedDocument = IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'volunteer-receipt',
            'person_id' => $person->id,
            'school_id' => $school->id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Recibo mensal de atividade de voluntário',
                'person_name' => $person->full_name,
                'person_cpf' => $person->cpf,
                'person_address' => $address,
                'school_name' => $school->name,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'amount' => $amount,
                'amount_words' => $amountWords,
                'issued_date' => $data['issued_date'],
            ],
            'issued_at' => now(),
        ]);

        $pdf = Pdf::loadView('reports.records.volunteer-receipt', [
            'person' => $person,
            'school' => $school,
            'address' => $address,
            'amount' => number_format((float) $amount, 2, ',', '.'),
            'amountWords' => $amountWords,
            'periodLabel' => $periodLabel,
            'issuedDate' => $issuedDate,
            'letterhead' => PdfLetterhead::make($school),
            'issuedDocument' => $issuedDocument,
        ])->setPaper('a4', 'portrait');

        return PdfMetadata::stream($pdf, 'beaba-recibo-voluntario-'.$person->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function school(Request $request, School $school): Response|RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        if ($message = OfficialDocumentCompliance::schoolMessage($school)) {
            return redirect()->route($request->user()->canManageSchools() ? 'schools.edit' : 'schools.academic-years.index', $school)->with('status', $message);
        }

        $issuedDocument = $this->issuedDocument($request, 'school-record', __('Ficha da escola'), $school->id);

        $pdf = Pdf::loadView('reports.records.school', [
            'school' => $school->load('roles.person'),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($school),
        ])->setPaper('a4', 'portrait');

        return PdfMetadata::stream($pdf, 'beaba-ficha-escola-'.$school->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function person(Request $request, Person $person): Response|RedirectResponse
    {
        abort_unless($this->canSeePerson($request, $person), 403);

        $person->load(['schoolRoles.school', 'contacts']);

        if (! $person->hasActiveRoleForDate() && ! $person->hasRequiredIdentityForOfficialUse()) {
            return redirect()->route('people.show', $person)
                ->with('status', __('Não é possível emitir documento de pessoa inativa sem CPF.'));
        }

        $school = $this->schoolForPerson($person);

        if ($message = OfficialDocumentCompliance::personMessage($person)) {
            return redirect()->route('people.show', $person)->with('status', $message);
        }

        if ($school && $message = OfficialDocumentCompliance::schoolMessage($school)) {
            return redirect()->route($request->user()->canManageSchools() ? 'schools.edit' : 'schools.academic-years.index', $school)->with('status', $message);
        }

        $issuedDocument = $this->issuedDocument($request, 'person-record', __('Ficha da pessoa'), $school?->id, $person->id);

        $pdf = Pdf::loadView('reports.records.person', [
            'person' => $person,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($school),
        ])->setPaper('a4', 'portrait');

        return PdfMetadata::stream($pdf, 'beaba-ficha-pessoa-'.$person->id.'-'.now()->format('Ymd-His').'.pdf');
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
