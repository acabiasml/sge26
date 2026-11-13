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

    public function test_daily_limit_uses_each_course_lesson_duration_and_counts_the_combined_new_diaries(): void
    {
        [$manifest, $year, $course, $component, $classes] = $this->scenario();
        $manifest['max_daily_minutes'] = 480;
        $general = $year->courses()->create(['name' => 'Formação geral', 'stage' => 'medio', 'class_hour_minutes' => 50]);
        $other = $general->components()->create(['name' => 'Matemática', 'weekly_lessons' => 4, 'active' => true]);
        $record = DiaryAttendanceRecord::create(['school_class_id' => $classes[0]->id, 'curriculum_component_id' => $other->id,
            'academic_period_id' => $year->periods()->sole()->id, 'class_date' => '2026-02-12', 'lesson_count' => 4]);
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(440, $plan['daily_load']['maximum_minutes']);
        $record->update(['lesson_count' => 5]); // 250 + 240 = 490 minutos, acima de oito horas.
        try {
            $service->reconcile($manifest);
            $this->fail('A soma das durações precisa respeitar oito horas.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('limite diário', $error->getMessage());
        }
        $record->update(['lesson_count' => 4]);
        $extra = $course->components()->create(['name' => 'Outra disciplina técnica', 'workload_hours' => 20, 'active' => true]);
        $assignment = $classes[0]->componentAssignments()->create(['curriculum_component_id' => $extra->id,
            'teacher_person_id' => $manifest['diaries'][0]['teacher_person_id'], 'active' => true]);
        $third = $manifest['diaries'][0];
        $third['assignment_id'] = $assignment->id;
        $third['component_id'] = $extra->id;
        $third['expected_hours'] = 2;
        $third['sessions'] = [['date' => '2026-02-12', 'lesson_count' => 2, 'content' => 'Atividade adicional.']];
        $manifest['diaries'][] = $third;
        $this->expectExceptionMessage('limite diário');
        $service->reconcile($manifest);
    }

    public function test_other_class_lessons_are_counted_and_a_later_teacher_record_invalidates_the_preview(): void
    {
        [$manifest, $year, $course, $component, $classes] = $this->scenario();
        $manifest['max_daily_minutes'] = 480;
        $personId = $classes[0]->enrollments()->first()->person_id;
        $parallel = $year->classes()->create(['name' => 'Atividades paralelas']);
        $parallel->enrollments()->create(['person_id' => $personId, 'enrolled_at' => '2026-02-02', 'status' => 'matriculado']);
        $record = DiaryAttendanceRecord::create(['school_class_id' => $parallel->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $year->periods()->sole()->id, 'class_date' => '2026-02-12', 'lesson_count' => 4]);
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(480, $plan['daily_load']['maximum_minutes']);
        // Mesmo sem presenças registradas, a chamada consome a carga diária.
        $record->update(['lesson_count' => 3]);
        try {
            $service->reconcile($manifest, true, $plan['snapshot_sha256']);
            $this->fail('A mudança de outro diário precisa invalidar a conferência.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudou', $error->getMessage());
        }
        $this->assertDatabaseCount('diary_contents', 0);
        $this->assertDatabaseCount('diary_attendance_records', 1);
        $record->update(['lesson_count' => 5]);
        $this->expectExceptionMessage('limite diário');
        $service->reconcile($manifest);
    }

    public function test_daily_limit_does_not_double_count_already_imported_calls_or_other_class_lessons_after_exit(): void
    {
        [$manifest, $year, $course, $component, $classes] = $this->scenario();
        $manifest['max_daily_minutes'] = 480;
        $parallel = $year->classes()->create(['name' => 'Turma anterior']);
        $parallel->enrollments()->create(['person_id' => $classes[0]->enrollments()->first()->person_id,
            'enrolled_at' => '2026-02-02', 'transferred_at' => '2026-02-04', 'status' => 'transferido']);
        DiaryAttendanceRecord::create(['school_class_id' => $parallel->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $year->periods()->sole()->id, 'class_date' => '2026-02-12', 'lesson_count' => 8]);
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(240, $plan['daily_load']['maximum_minutes']);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $after = $service->reconcile($manifest);
        $this->assertSame(240, $after['daily_load']['maximum_minutes']);
        $this->assertSame(0, $after['diaries'][0]['stats']['calls_to_create']);
    }

    public function test_redistributes_only_its_previous_import_across_periods_without_changing_hours_or_grades(): void
    {
        [$manifest, $year, $course, $component, $classes] = $this->scenario();
        $service = app(DocumentedDiaryImporter::class);
        $original = $service->reconcile($manifest);
        $applied = $service->reconcile($manifest, true, $original['snapshot_sha256']);
        foreach ([[2, '2026-04-13', '2026-07-08'], [3, '2026-08-03', '2026-10-09'], [4, '2026-10-14', '2026-12-22']] as [$position, $start, $end]) {
            $year->periods()->create(['name' => $position.'º Bimestre', 'position' => $position, 'starts_at' => $start, 'ends_at' => $end]);
        }
        foreach (['2026-05-05', '2026-08-05', '2026-10-15'] as $date) {
            $year->days()->create(['date' => $date, 'type' => CalendarDay::TYPE_SCHOOL_DAY, 'counts_as_school_day' => true]);
        }
        $grades = DiaryAssessmentResult::get()->toArray();
        $matrix = [$course->fresh()->getAttributes(), $component->fresh()->getAttributes()];
        $manifest['replace_previous_import'] = $applied['backup_path'];
        $manifest['max_daily_minutes'] = 480;
        foreach ($manifest['diaries'] as &$diary) {
            $diary['sessions'] = [
                ['date' => '2026-02-05', 'lesson_count' => 2, 'content' => 'Instrumentos.'],
                ['date' => '2026-05-05', 'lesson_count' => 2, 'content' => 'Escalas.'],
                ['date' => '2026-08-05', 'lesson_count' => 1, 'content' => 'Perspectivas.'],
                ['date' => '2026-10-15', 'lesson_count' => 1, 'content' => 'Vistas ortográficas.'],
            ];
        }
        unset($diary);
        $other = $course->components()->create(['name' => 'Outra disciplina', 'workload_hours' => 60]);
        $untouched = DiaryAttendanceRecord::create(['school_class_id' => $classes[0]->id, 'curriculum_component_id' => $other->id,
            'academic_period_id' => $year->periods()->where('position', 1)->sole()->id, 'class_date' => '2026-02-05', 'lesson_count' => 6]);
        $plan = $service->reconcile($manifest);
        $this->assertSame(480, $plan['daily_load']['maximum_minutes']);
        $this->assertSame(2, $plan['diaries'][0]['stats']['calls_to_replace']);
        $this->assertDatabaseCount('diary_attendance_records', 5);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $this->assertDatabaseCount('diary_attendance_records', 9);
        $this->assertDatabaseCount('diary_contents', 8);
        $this->assertTrue($untouched->fresh()->exists);
        foreach ($classes as $class) {
            $records = DiaryAttendanceRecord::where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)->get();
            $this->assertSame(4, $records->pluck('academic_period_id')->unique()->count());
            $this->assertSame(6, (int) $records->sum('lesson_count'));
        }
        $this->assertSame($grades, DiaryAssessmentResult::get()->toArray());
        $this->assertSame($matrix, [$course->fresh()->getAttributes(), $component->fresh()->getAttributes()]);
        unset($manifest['replace_previous_import']);
        $verified = $service->reconcile($manifest);
        $this->assertSame(0, $verified['diaries'][0]['stats']['calls_to_create']);
        $this->assertSame(0, $verified['diaries'][0]['stats']['entries_to_create']);
    }

    public function test_redistribution_rejects_later_manual_notes_and_does_not_remove_existing_data(): void
    {
        [$manifest] = $this->scenario();
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $applied = $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $manifest['replace_previous_import'] = $applied['backup_path'];
        DiaryAttendanceRecord::first()->update(['notes' => 'Observação posterior do docente']);
        try {
            $service->reconcile($manifest);
            $this->fail('Uma edição posterior precisa impedir a substituição automática.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('edições posteriores', $error->getMessage());
        }
        $this->assertDatabaseCount('diary_attendance_records', 4);
        $this->assertDatabaseCount('diary_attendance_entries', 4);
        $this->assertDatabaseCount('diary_contents', 4);
    }

    public function test_redistribution_cannot_change_the_original_teacher_or_total_hours(): void
    {
        [$manifest] = $this->scenario();
        $service = app(DocumentedDiaryImporter::class);
        $plan = $service->reconcile($manifest);
        $applied = $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $manifest['replace_previous_import'] = $applied['backup_path'];
        $manifest['diaries'][1]['expected_hours'] = 60;
        $this->expectExceptionMessage('alvo ou a carga');
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
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
