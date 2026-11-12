<?php

namespace App\Console\Commands;

use App\Models\AcademicCourse;
use App\Models\AuditLog;
use App\Models\StudentEnrollment;
use App\Support\LegacyLiceuItineraryLinks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LinkLiceuThirdYearItineraries extends Command
{
    protected $signature = 'itineraries:link-liceu-third-year {--apply} {--expected= : Hash da consulta protegida conferida}';

    protected $description = 'Regulariza somente o itinerário por módulos dos alunos do 3º ano do Liceu de 2026 e seu 2º ano de 2025.';

    public function handle(LegacyLiceuItineraryLinks $legacy): int
    {
        return DB::transaction(function () use ($legacy): int {
            $snapshot = $legacy->authorizedThirdYearSnapshot();
            $enrollments = StudentEnrollment::whereIn('id', collect($snapshot['links'])->pluck('enrollment_id'))->lockForUpdate()->get()->keyBy('id');
            $matrices = AcademicCourse::whereIn('id', collect($snapshot['links'])->pluck('high_school_course_id'))->lockForUpdate()->get()->keyBy('id');
            if ($snapshot['sha256'] !== $legacy->authorizedThirdYearSnapshot()['sha256']) {
                throw new \RuntimeException('O recorte mudou durante a conferência.');
            }
            $pending = 0;
            foreach ($snapshot['links'] as $row) {
                $enrollment = $enrollments[$row['enrollment_id']];
                if ($enrollment->technical_itinerary_course_id && $enrollment->technical_itinerary_course_id !== $row['technical_course_id']) {
                    throw new \RuntimeException('Há um vínculo técnico diferente do recorte autorizado. Nenhum registro foi alterado.');
                }
                $pending += $enrollment->technical_itinerary_course_id !== $row['technical_course_id'] ? 1 : 0;
                $pending += $matrices[$row['high_school_course_id']]->technicalItineraries()->where('academic_courses.id', $row['technical_course_id'])->exists() ? 0 : 1;
            }
            $this->info(json_encode(['students' => $snapshot['students'], 'enrollment_links' => $snapshot['count'], 'pending' => $pending, 'sha256' => $snapshot['sha256']], JSON_THROW_ON_ERROR));
            if (! $this->option('apply') || $pending === 0) {
                return self::SUCCESS;
            }
            if (! $this->option('expected') || ! hash_equals($snapshot['sha256'], $this->option('expected')) || $snapshot['students'] === 0) {
                $this->error('O hash deve corresponder ao recorte consultado e autorizado.');

                return self::FAILURE;
            }
            $directory = storage_path('app/private/itinerary-backfills');
            File::ensureDirectoryExists($directory, 0700);
            $backup = $directory.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(8)).'.json';
            File::put($backup, json_encode(['snapshot' => $snapshot, 'enrollments' => $enrollments->toArray(),
                'matrix_links' => DB::table('academic_course_technical_itinerary')->whereIn('academic_course_id', $matrices->keys())->get()->all()], JSON_THROW_ON_ERROR));
            chmod($backup, 0600);
            foreach ($snapshot['links'] as $row) {
                $matrices[$row['high_school_course_id']]->technicalItineraries()->syncWithoutDetaching([$row['technical_course_id']]);
                $enrollments[$row['enrollment_id']]->update(['technical_itinerary_course_id' => $row['technical_course_id']]);
            }
            AuditLog::create(['school_id' => $snapshot['school_id'], 'auditable_type' => AcademicCourse::class,
                'auditable_id' => $matrices->keys()->first(), 'action' => 'updated',
                'metadata' => ['operation' => 'liceu-third-year-itinerary-2026', 'snapshot_sha256' => $snapshot['sha256'], 'backup' => basename($backup),
                    'students' => $snapshot['students'], 'enrollment_links' => $snapshot['count']]]);
            $this->info('Vínculos registrados. Notas, faltas, períodos e matrizes do 1º e do 2º ano atuais foram preservados.');

            return self::SUCCESS;
        });
    }
}
