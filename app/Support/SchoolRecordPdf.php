<?php

namespace App\Support;

use App\Models\IssuedDocument;
use App\Models\CredentialRegistration;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class SchoolRecordPdf
{
    public function __construct(private CredentialRegistry $registry) {}

    public static function registryRules(School $school): array
    {
        $rules = ['issuance_token' => ['required', 'uuid']];
        if (! $school->usesPhysicalRegistry()) {
            return $rules + ['registry_book_id' => ['required', 'integer']];
        }

        return $rules + [
            'registry_book' => ['required', 'string', 'max:50'],
            'registry_number' => ['required', 'string', 'max:50'],
            'registry_folio' => ['required', 'string', 'max:50'],
            'registered_by' => ['required', 'string', 'max:150'],
        ];
    }

    public static function rules(School $school): array
    {
        return self::registryRules($school) + [
            'signature_mode' => ['required', 'in:physical,digital'],
        ] + ($school->usesPhysicalRegistry() ? ['registration_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']] : []);
    }

    public static function physicalReference(School $school, array $data): ?array
    {
        return $school->usesPhysicalRegistry() ? array_intersect_key($data, array_flip([
            'registry_book', 'registry_number', 'registry_folio', 'registered_by',
        ])) : null;
    }

    public function draft(User $user, School $school, string $type, ?int $personId, array $payload): IssuedDocument
    {
        return $this->document($user, $school, $type, $personId, $payload + ['is_draft' => true]);
    }

    public function issue(User $user, School $school, string $type, ?int $personId, array $payload, array $data, callable $render, ?string $scope = null, array $evidence = []): IssuedDocument
    {
        $path = null;
        try {
            return $this->registry->issue(isset($data['registry_book_id']) ? (int) $data['registry_book_id'] : null, $school->id, $type, $user,
                $data['registration_date'] ?? now('America/Cuiaba')->toDateString(), null, function (array $registration) use ($user, $school, $type, $personId, $payload, $data, $render, &$path) {
                    $document = $this->document($user, $school, $type, $personId, $payload + ['registration' => $registration]
                        + array_intersect_key($data, ['signature_mode' => true]));
                    $response = $render($document);
                    $bytes = $response instanceof Response ? $response->getContent() : $response;
                    throw_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-'), \RuntimeException::class, 'O registro exige um PDF original.');
                    $path = 'school-records/'.$document->uuid.'.pdf';
                    $this->registry->writePrivate($path, $bytes);
                    $document->forceFill(['file_path' => $path, 'file_sha256' => hash('sha256', $bytes)])->save();

                    return $document;
                }, $data['issuance_token'].':'.$this->registry->digest(array_intersect_key($payload, array_flip([
                    'student_academic_history_id', 'school_class_id', 'enrollment_id', 'technical_course_id', 'module_key', 'delivery_event_id',
                ])) + ($type === 'academic-year-final-results' ? ['academic_year_id' => $payload['academic_year_id']] : [])
                    + ['person_id' => $personId, 'type' => $type]), $scope, $evidence, self::physicalReference($school, $data));
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    /** @return array{diretor: \App\Models\Person, secretário: \App\Models\Person} */
    public function signers(School $school): array
    {
        $staff = SchoolSignatureStaff::forSchool($school);
        if (! $staff['diretor'] || ! $staff['secretário']) {
            throw ValidationException::withMessages(['document' => 'Cadastre a direção e a secretaria em exercício antes do registro oficial.']);
        }

        return ['diretor' => $staff['diretor'], 'secretário' => $staff['secretário']];
    }

    public function signedHistory(string $code, int $personId, int $schoolId, string $stage, ?int $courseId = null): CredentialRegistration
    {
        $document = IssuedDocument::query()->with('registration')->where('verification_code', mb_strtoupper(trim($code)))
            ->where('person_id', $personId)->where('school_id', $schoolId)->whereNull('revoked_at')
            ->where('type', 'student-academic-history')->where('payload->education_stage', $stage)
            ->when($courseId, fn ($q) => $q->where('payload->technical_course_id', $courseId))
            ->first();
        $entry = $document?->registration;
        $copy = $entry?->finalCopy();
        if (! $entry || ! $copy) {
            throw ValidationException::withMessages(['history_code' => 'Informe o código do histórico registrado e assinado do mesmo estudante, escola e etapa ou curso.']);
        }
        $this->registry->bytes($copy->file_path, $copy->sha256);

        return $entry;
    }

    private function document(User $user, School $school, string $type, ?int $personId, array $payload): IssuedDocument
    {
        return new IssuedDocument([
            'uuid' => (string) Str::uuid(),
            'verification_code' => ($payload['is_draft'] ?? false) ? 'PREVIA-SEM-REGISTRO' : 'BEABA-'.implode('-', str_split(Str::upper(Str::random(12)), 4)),
            'type' => $type, 'person_id' => $personId ?? $user->person_id, 'school_id' => $school->id,
            'issued_by_user_id' => $user->id, 'issued_at' => now(), 'payload' => $payload,
        ]);
    }
}
