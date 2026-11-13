<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryExcludedDate;
use App\Models\Person;
use App\Models\School;
use App\Support\DocumentedDiaryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentedDiaryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_two_classes_sharing_a_matrix_and_is_repeatable_without_changing_grades_or_matrix(): void
    {
        [$manifest, $year, $course, $component] = $this->scenario();
        $before = [$year->fresh()->getAttributes(), $course->fresh()->getAttributes(), $component->fresh()->getAttributes(), DiaryAssessmentResult::get()->toArray()];
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(2, $plan['diaries'][0]['stats']['calls_to_create']);
        $this->assertDatabaseCount('diary_attendance_records', 0);
        $applied = $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        Storage::disk('local')->assertExists($applied['backup_path']);
        $this->assertDatabaseCount('diary_attendance_records', 4);
        $this->assertDatabaseCount('diary_attendance_entries', 4);
        $this->assertDatabaseCount('diary_contents', 4);
        $this->assertSame(6, (int) DiaryAttendanceRecord::where('school_class_id', $manifest['diaries'][0]['class_id'])->sum('lesson_count'));
        $this->assertNull(DiaryAttendanceRecord::first()->notes);
        $this->assertSame('Conteúdo documentado.', DiaryContent::first()->content);
        $this->assertSame($before, [$year->fresh()->getAttributes(), $course->fresh()->getAttributes(), $component->fresh()->getAttributes(), DiaryAssessmentResult::get()->toArray()]);
        $next = $service->reconcile($manifest);
        foreach ($next['diaries'] as $diary) {
            $this->assertSame(0, $diary['stats']['calls_to_create']);
            $this->assertSame(0, $diary['stats']['entries_to_create']);
            $this->assertSame(0, $diary['stats']['contents_to_create']);
        }
        $service->reconcile($manifest, true, $next['snapshot_sha256']);
        $this->assertDatabaseCount('diary_attendance_entries', 4);
    }

    public function test_presence_respects_entry_transfer_cancellation_reclassification_and_return_episodes(): void
    {
        [$manifest, , , , $classes] = $this->scenario();
        $class = $classes[0];
        $original = $class->enrollments()->first();
        $original->update(['status' => 'transferido', 'transferred_at' => '2026-02-05']);
        $return = $class->enrollments()->create(['person_id' => $original->person_id, 'enrolled_at' => '2026-02-10', 'status' => 'matriculado']);
        foreach (['transferred_at', 'cancelled_at', 'reclassified_at'] as $field) {
            $person = Person::create(['full_name' => 'Estudante '.$field]);
            $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2026-02-02', $field => '2026-02-08', 'status' => 'transferido']);
        }
        $late = $class->enrollments()->create(['person_id' => Person::create(['full_name' => 'Ingresso posterior'])->id, 'enrolled_at' => '2026-02-10', 'status' => 'matriculado']);
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $first = DiaryAttendanceRecord::where('school_class_id', $class->id)->whereDate('class_date', '2026-02-05')->sole();
        $last = DiaryAttendanceRecord::where('school_class_id', $class->id)->whereDate('class_date', '2026-02-12')->sole();
        $this->assertSame(4, $first->entries()->count());
        $this->assertTrue($first->entries()->where('student_enrollment_id', $original->id)->exists());
        $this->assertFalse($first->entries()->where('student_enrollment_id', $late->id)->exists());
        $this->assertEqualsCanonicalizing([$return->id, $late->id], $last->entries()->pluck('student_enrollment_id')->all());
    }

    public function test_a_changed_enrollment_after_preview_blocks_both_classes_before_writing(): void
    {
        [$manifest, , , , $classes] = $this->scenario();
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $classes[1]->enrollments()->first()->update(['transferred_at' => '2026-02-06']);
        try {
            $service->reconcile($manifest, true, $plan['snapshot_sha256']);
            $this->fail('A mudança do vínculo precisa interromper a gravação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudou', $error->getMessage());
        }
        $this->assertDatabaseCount('diary_attendance_records', 0);
        $this->assertDatabaseCount('diary_contents', 0);
    }

    public function test_conflicting_existing_absences_are_not_silently_overwritten(): void
    {
        [$manifest, , , , $classes] = $this->scenario();
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $entry = DiaryAttendanceEntry::whereHas('record', fn ($q) => $q->where('school_class_id', $classes[1]->id))->first();
        $entry->update(['attended_lessons' => 0, 'status' => 'falta']);
        try {
            $service->reconcile($manifest);
            $this->fail('Uma falta existente precisa ser conferida.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('marcações divergentes', $error->getMessage());
        }
        $this->assertSame('falta', $entry->fresh()->status);
    }

    public function test_holidays_future_dates_and_excluded_diary_dates_cannot_be_imported(): void
    {
        [$manifest, $year] = $this->scenario();
        $service = app(DocumentedDiaryImporter::class);
        foreach (['holiday', 'excluded', 'future'] as $case) {
            $copy = $manifest;
            if ($case === 'holiday') {
                $year->days()->whereDate('date', '2026-02-05')->update(['counts_as_school_day' => false]);
            } elseif ($case === 'excluded') {
                $year->days()->whereDate('date', '2026-02-05')->update(['counts_as_school_day' => true]);
                DiaryExcludedDate::create(['school_class_component_id' => $copy['diaries'][0]['assignment_id'],
                    'academic_period_id' => $year->periods()->sole()->id, 'class_date' => '2026-02-05']);
            } else {
                $copy['diaries'][0]['sessions'][0]['date'] = '2027-02-05';
            }
            try {
                $service->reconcile($copy);
                $this->fail('A data inválida foi aceita: '.$case);
            } catch (RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $this->assertDatabaseCount('diary_attendance_records', 0);
    }

    public function test_changed_manifest_teacher_or_hour_total_cannot_reuse_a_preview(): void
    {
        [$manifest] = $this->scenario();
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        foreach (['content', 'teacher', 'hours'] as $case) {
            $copy = $manifest;
            if ($case === 'content') {
                $copy['diaries'][0]['sessions'][0]['content'] = 'Outro conteúdo';
            }
            if ($case === 'teacher') {
                $copy['diaries'][0]['teacher_person_id'] = 99999;
            }
            if ($case === 'hours') {
                $copy['diaries'][0]['expected_hours'] = 60;
            }
            try {
                $service->reconcile($copy, true, $plan['snapshot_sha256']);
                $this->fail('A alteração não conferida precisa ser rejeitada.');
            } catch (RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $this->assertDatabaseCount('diary_attendance_records', 0);
    }

    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 13));
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026,
            'starts_at' => '2026-02-02', 'ends_at' => '2026-12-22']);
        $period = $year->periods()->create(['name' => 'I Bimestre', 'position' => 1, 'starts_at' => '2026-02-02', 'ends_at' => '2026-04-10']);
        foreach (['2026-02-05', '2026-02-12'] as $date) {
            $year->days()->create(['date' => $date, 'type' => CalendarDay::TYPE_SCHOOL_DAY, 'counts_as_school_day' => true]);
        }
        $course = $year->courses()->create(['name' => 'Curso técnico', 'stage' => 'tecnico', 'class_hour_minutes' => 60, 'workload_hours' => 60]);
        $component = $course->components()->create(['name' => 'História da Arte', 'workload_hours' => 60, 'active' => true]);
        $teacher = Person::create(['full_name' => 'Professor de teste']);
        $classes = $diaries = [];
        foreach (['1º Ano', '2º Ano'] as $name) {
            $class = $year->classes()->create(['name' => $name]);
            $class->courses()->attach($course);
            $assignment = $class->componentAssignments()->create(['curriculum_component_id' => $component->id, 'teacher_person_id' => $teacher->id, 'active' => true]);
            $student = Person::create(['full_name' => 'Estudante '.$name]);
            $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2026-02-02', 'status' => 'matriculado']);
            $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id, 'title' => 'Avaliação existente', 'is_recovery' => false]);
            $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 7.5]);
            $diaries[] = ['assignment_id' => $assignment->id, 'teacher_person_id' => $teacher->id, 'class_id' => $class->id,
                'component_id' => $component->id, 'school_id' => $school->id, 'reference_year' => 2026, 'expected_hours' => 6,
                'sessions' => [['date' => '2026-02-05', 'lesson_count' => 2, 'content' => 'Conteúdo documentado.'],
                    ['date' => '2026-02-12', 'lesson_count' => 4, 'content' => 'Continuação.']]];
            $classes[] = $class;
        }

        return [['format' => 'documented-diary-import-v1', 'source_file' => 'fonte.docx', 'source_sha256' => str_repeat('a', 64), 'diaries' => $diaries], $year, $course, $component, $classes];
    }
}
