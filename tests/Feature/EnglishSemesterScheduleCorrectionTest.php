<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\School;
use App\Support\EnglishSemesterScheduleCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EnglishSemesterScheduleCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrects_only_the_second_semester_slots_and_preserves_all_hours_and_school_records(): void
    {
        [$schedule, $source, $target, $course, $firstSlot, $lastSlot] = $this->scenario();
        $before = [$course->fresh()->getAttributes(), $course->components()->orderBy('id')->get()->toArray(),
            DiaryAssessment::with('results')->get()->toArray(), DiaryAttendanceRecord::with('entries')->get()->toArray(), DiaryContent::get()->toArray()];
        $otherSlots = $schedule->slots()->where('school_class_component_id', '!=', $source->id)->get()->toArray();
        $service = app(EnglishSemesterScheduleCorrection::class);
        $plan = $service->correct($schedule->id, $source->id, $target->id, 14);
        $this->assertSame(1, $plan['slots_to_reassign']);
        $this->assertSame(1, $plan['slots_to_remove']);
        $this->assertEquals(480, $plan['annual_hours']);
        $this->assertSame(15, $schedule->slots()->count());
        $applied = $service->correct($schedule->id, $source->id, $target->id, 14, true, $plan['snapshot_sha256']);
        Storage::disk('local')->assertExists($applied['backup_path']);
        $this->assertSame(14, $schedule->slots()->count());
        $this->assertSame($target->id, $firstSlot->fresh()->school_class_component_id);
        $this->assertNull($lastSlot->fresh());
        $this->assertSame($otherSlots, $schedule->slots()->whereNotIn('school_class_component_id', [$source->id, $target->id])->get()->toArray());
        $this->assertSame($before, [$course->fresh()->getAttributes(), $course->components()->orderBy('id')->get()->toArray(),
            DiaryAssessment::with('results')->get()->toArray(), DiaryAttendanceRecord::with('entries')->get()->toArray(), DiaryContent::get()->toArray()]);
        $verified = $service->correct($schedule->id, $source->id, $target->id, 14);
        $this->assertSame(0, $verified['slots_to_reassign']);
        $this->assertSame(0, $verified['slots_to_remove']);
    }

    public function test_changed_schedule_or_teacher_after_preview_prevents_any_correction(): void
    {
        [$schedule, $source, $target, , $firstSlot] = $this->scenario();
        $service = app(EnglishSemesterScheduleCorrection::class);
        $plan = $service->correct($schedule->id, $source->id, $target->id, 14);
        $firstSlot->update(['starts_at' => '14:40:00', 'ends_at' => '15:30:00']);
        try {
            $service->correct($schedule->id, $source->id, $target->id, 14, true, $plan['snapshot_sha256']);
            $this->fail('Uma grade alterada precisa invalidar a conferência.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudaram', $error->getMessage());
        }
        $this->assertSame(15, $schedule->slots()->count());
        $target->update(['teacher_person_id' => Person::create(['full_name' => 'Outra docente'])->id]);
        $this->expectExceptionMessage('professora divergentes');
        $service->correct($schedule->id, $source->id, $target->id, 14);
    }

    public function test_it_cannot_rewrite_a_first_semester_schedule(): void
    {
        [$schedule, $source, $target] = $this->scenario();
        $schedule->update(['starts_at' => '2026-02-02']);
        $this->expectExceptionMessage('segundo semestre');
        app(EnglishSemesterScheduleCorrection::class)->correct($schedule->id, $source->id, $target->id, 14);
    }

    public function test_relinks_old_second_semester_attendance_and_content_without_changing_values_or_grades(): void
    {
        [$schedule, $source, $target] = $this->scenario();
        $existing = DiaryAttendanceRecord::first();
        $orphan = $existing->replicate();
        $orphan->curriculum_component_id = $source->curriculum_component_id;
        $orphan->class_date = '2026-08-17';
        $orphan->lesson_count = 2;
        $orphan->save();
        $orphan->entries()->create(['student_enrollment_id' => $existing->entries()->sole()->student_enrollment_id,
            'status' => 'parcial', 'attended_lessons' => 1, 'lesson_presence' => [true, false]]);
        $content = DiaryContent::first()->replicate();
        $content->curriculum_component_id = $source->curriculum_component_id;
        $content->class_date = '2026-08-17';
        $content->save();
        $entries = $orphan->entries()->get()->toArray();
        $grades = DiaryAssessment::with('results')->get()->toArray();
        $service = app(EnglishSemesterScheduleCorrection::class);
        $plan = $service->correct($schedule->id, $source->id, $target->id, 14);
        $this->assertSame(1, $plan['attendance_records_to_relink']);
        $this->assertSame(2, $plan['attendance_lessons_to_relink']);
        $this->assertSame(1, $plan['content_records_to_relink']);
        $service->correct($schedule->id, $source->id, $target->id, 14, true, $plan['snapshot_sha256']);
        $this->assertSame($target->curriculum_component_id, $orphan->fresh()->curriculum_component_id);
        $this->assertSame($target->curriculum_component_id, $content->fresh()->curriculum_component_id);
        $this->assertSame(2, $orphan->fresh()->lesson_count);
        $this->assertSame($entries, $orphan->entries()->get()->toArray());
        $this->assertSame($grades, DiaryAssessment::with('results')->get()->toArray());
        $verified = $service->correct($schedule->id, $source->id, $target->id, 14);
        $this->assertSame(0, $verified['attendance_records_to_relink']);
        $this->assertSame(0, $verified['content_records_to_relink']);
    }

    public function test_conflicting_dates_between_both_components_prevent_relinking_or_schedule_changes(): void
    {
        [$schedule, $source, $target] = $this->scenario();
        $orphan = DiaryAttendanceRecord::first()->replicate();
        $orphan->curriculum_component_id = $source->curriculum_component_id;
        $orphan->save();
        $this->expectExceptionMessage('conflitantes na mesma data');
        app(EnglishSemesterScheduleCorrection::class)->correct($schedule->id, $source->id, $target->id, 14);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Escola de teste']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026,
            'starts_at' => '2026-02-02', 'ends_at' => '2026-12-22', 'minimum_school_days' => 200]);
        $one = $year->periods()->create(['name' => 'Primeiro semestre', 'position' => 1, 'starts_at' => '2026-02-02', 'ends_at' => '2026-07-08']);
        $two = $year->periods()->create(['name' => 'Segundo semestre', 'position' => 2, 'starts_at' => '2026-08-03', 'ends_at' => '2026-12-22']);
        $course = $year->courses()->create(['name' => '3º Ano', 'stage' => 'medio', 'class_hour_minutes' => 50, 'workload_hours' => 480]);
        $english = $course->components()->create(['name' => 'Língua Inglesa', 'workload_hours' => 30, 'starts_period_id' => $one->id, 'ends_period_id' => $one->id]);
        $englishTwo = $course->components()->create(['name' => 'Língua Inglesa II', 'weekly_lessons' => 1, 'starts_period_id' => $two->id, 'ends_period_id' => $two->id]);
        $other = $course->components()->create(['name' => 'Outros componentes', 'workload_hours' => 433.33, 'weekly_lessons' => 13]);
        $class = $year->classes()->create(['name' => '3º Ano']);
        $class->courses()->attach($course);
        $teacher = Person::create(['full_name' => 'Professora de Inglês']);
        $source = $class->componentAssignments()->create(['curriculum_component_id' => $english->id, 'teacher_person_id' => $teacher->id, 'active' => true]);
        $target = $class->componentAssignments()->create(['curriculum_component_id' => $englishTwo->id, 'teacher_person_id' => $teacher->id, 'active' => true]);
        $otherAssignment = $class->componentAssignments()->create(['curriculum_component_id' => $other->id, 'teacher_person_id' => $teacher->id, 'active' => true]);
        $schedule = $class->schedules()->create(['name' => 'Segundo semestre', 'starts_at' => '2026-08-03']);
        for ($i = 0; $i < 13; $i++) {
            $hour = 7 + $i % 4;
            $schedule->slots()->create(['weekday' => 1 + intdiv($i, 4), 'starts_at' => sprintf('%02d:00:00', $hour), 'ends_at' => sprintf('%02d:50:00', $hour), 'type' => 'aula', 'school_class_component_id' => $otherAssignment->id]);
        }
        $firstSlot = $schedule->slots()->create(['weekday' => 1, 'starts_at' => '14:45:00', 'ends_at' => '15:35:00', 'type' => 'aula', 'school_class_component_id' => $source->id]);
        $lastSlot = $schedule->slots()->create(['weekday' => 1, 'starts_at' => '15:35:00', 'ends_at' => '16:25:00', 'type' => 'aula', 'school_class_component_id' => $source->id]);
        $student = Person::create(['full_name' => 'Estudante']);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2026-02-02', 'status' => 'matriculado']);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $englishTwo->id, 'academic_period_id' => $two->id, 'title' => 'Avaliação']);
        $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 8]);
        $record = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $englishTwo->id, 'academic_period_id' => $two->id, 'class_date' => '2026-08-10', 'lesson_count' => 1]);
        $record->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        DiaryContent::create(['school_class_id' => $class->id, 'curriculum_component_id' => $englishTwo->id, 'academic_period_id' => $two->id, 'class_date' => '2026-08-10', 'content' => 'Conteúdo existente']);

        return [$schedule, $source, $target, $course, $firstSlot, $lastSlot];
    }
}
