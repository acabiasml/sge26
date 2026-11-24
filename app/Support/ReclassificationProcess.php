<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\CredentialBook;
use App\Models\CredentialRegistration;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ReclassificationProcess
{
    public const BASIC_STAGES = [AcademicCourse::STAGE_ELEMENTARY, AcademicCourse::STAGE_HIGH_SCHOOL];

    public const GROUNDS = [
        'advancement' => 'Avanço por habilidades avançadas, altas habilidades ou superdotação',
        'acceleration' => 'Aceleração por distorção idade/série',
        'transfer' => 'Transferência com avaliação para reposicionamento',
        'attendance' => 'Frequência mínima de 75% e desempenho satisfatório',
    ];

    public static function approved(int $schoolId): Builder
    {
        return CredentialRegistration::query()
            ->whereHas('book', fn (Builder $book) => $book->where('school_id', $schoolId)->where('kind', 'special-process'))
            ->whereHas('document', fn (Builder $document) => $document->where('school_id', $schoolId)
                ->where('type', 'school-special-process')->whereNull('revoked_at'))
            ->where('snapshot->process_kind', 'reclassification')
            ->where('snapshot->reclassification_decision', 'approved')
            ->whereDoesntHave('reclassifiedEnrollment');
    }

    public static function lockApproved(int $id, int $schoolId, StudentEnrollment $origin): CredentialRegistration
    {
        $query = self::approved($schoolId)->whereKey($id)
            ->where('snapshot->enrollment_id', $origin->id)
            ->whereHas('document', fn (Builder $document) => $document->where('person_id', $origin->person_id));
        $bookId = (clone $query)->value('credential_book_id');
        if ($bookId) {
            $book = CredentialBook::whereKey($bookId)->lockForUpdate()->firstOrFail();
            if ($entry = $query->with('document', 'files')->lockForUpdate()->first()) {
                $registry = app(CredentialRegistry::class);
                abort_unless($registry->integrity($book), 409, 'Divergência na cadeia de integridade do livro.');
                $registry->bytes($entry->document->file_path, $entry->document_sha256);
                foreach (['signed_process_summary', 'supporting_document'] as $kind) {
                    $file = $entry->files->firstWhere('kind', $kind);
                    abort_unless($file, 409, 'Falta um comprovante obrigatório do processo de reclassificação.');
                    $registry->bytes($file->file_path, $file->sha256);
                }

                return $entry;
            }
        }

        throw ValidationException::withMessages(['reclassification_registration_id' => __('Selecione um processo deferido, deste estudante e desta matrícula, registrado no Livro de Processos Especiais e ainda sem movimentação.')]);
    }

    public static function validateMovement(CredentialRegistration $entry, StudentEnrollment $origin, SchoolClass $target, array $courseIds, string $effectiveOn, string $firstEndsOn, string $secondStartsOn): void
    {
        $process = $entry->snapshot;
        $decisionOn = $process['act_date'];
        if ($decisionOn < $origin->enrolled_at?->toDateString() || $decisionOn > $firstEndsOn
            || $decisionOn >= $secondStartsOn || $decisionOn > $effectiveOn) {
            throw ValidationException::withMessages(['reclassification_registration_id' => __('A decisão deve constar do I bimestre, a partir da matrícula e antes do II bimestre, e anteceder ou coincidir com a efetivação.')]);
        }

        $sourceCourses = $origin->courses()->whereIn('stage', self::BASIC_STAGES)->get();
        $targetCourses = $target->courses()->whereKey($courseIds)->whereIn('stage', self::BASIC_STAGES)->get();
        if ($sourceCourses->count() !== 1 || $targetCourses->count() !== 1
            || (int) $process['reclassification_target_course_id'] !== $targetCourses->first()->id) {
            throw ValidationException::withMessages(['course_ids' => __('Use uma matriz de Educação Básica na origem e selecione a matriz de destino deferida no processo.')]);
        }

        $source = self::grade($sourceCourses->first(), $origin->schoolClass);
        $destination = self::grade($targetCourses->first(), $target);
        if (! $source || ! $destination || $source === 12 || $destination === 12 || $destination <= $source) {
            throw ValidationException::withMessages(['course_ids' => __('A reclassificação exige avanço para uma série identificada e não se aplica ao último ano do Ensino Médio.')]);
        }
    }

    private static function grade(AcademicCourse $course, SchoolClass $class): ?int
    {
        $grades = app(StudentAcademicHistoryCompleteness::class);
        $number = $grades->gradeNumber($course->name, $course->stage) ?? $grades->gradeNumber($class->name, $course->stage);

        return $number ? $number + ($course->stage === AcademicCourse::STAGE_HIGH_SCHOOL ? 9 : 0) : null;
    }
}
