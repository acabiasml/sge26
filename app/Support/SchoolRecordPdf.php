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
    public const RULES = [
        'registry_book_id' => ['required', 'integer'],
        'issuance_token' => ['required', 'uuid'],
        'signature_mode' => ['required', 'in:physical,digital'],
    ];

    public function __construct(private CredentialRegistry $registry) {}

    public function draft(User $user, School $school, string $type, ?int $personId, array $payload): IssuedDocument
    {
        return $this->document($user, $school, $type, $personId, $payload + ['is_draft' => true]);
    }

    public function issue(User $user, School $school, string $type, ?int $personId, array $payload, array $data, callable $render, ?string $scope = null, array $evidence = []): IssuedDocument
    {
        $path = null;
        try {
            return $this->registry->issue((int) $data['registry_book_id'], $school->id, $type, $user,
                now('America/Cuiaba')->toDateString(), null, function (array $registration) use ($user, $school, $type, $personId, $payload, $data, $render, &$path) {
                    $document = $this->document($user, $school, $type, $personId, $payload + [
                        'registration' => $registration, 'signature_mode' => $data['signature_mode'],
                    ]);
                    $response = $render($document);
                    $bytes = $response instanceof Response ? $response->getContent() : $response;
                    throw_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-'), \RuntimeException::class, 'O registro exige um PDF original.');
                    $path = 'school-records/'.$document->uuid.'.pdf';
                    $this->registry->writePrivate($path, $bytes);
                    $document->forceFill(['file_path' => $path, 'file_sha256' => hash('sha256', $bytes)])->save();

                    return $document;
                }, $data['issuance_token'].':'.$this->registry->digest(array_intersect_key($payload, array_flip([
                    'student_academic_history_id', 'school_class_id', 'enrollment_id', 'technical_course_id', 'module_key',
                ])) + ['person_id' => $personId, 'type' => $type]), $scope, $evidence);
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
            'type' => $type, 'person_id' => $personId, 'school_id' => $school->id,
            'issued_by_user_id' => $user->id, 'issued_at' => now(), 'payload' => $payload,
        ]);
    }
}
