<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\School;
use App\Support\AdministrativeDiaryReconstruction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AdministrativeDiaryReconstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconstruction_retains_provenance_propagates_boarding_absences_skips_unknowns_and_is_idempotent(): void
    {
        [$manifest, $class, $component, $enrollment, $departed] = $this->scenario();
        $grades = $enrollment->assessmentResults()->get()->toArray();
        $service = app(AdministrativeDiaryReconstruction::class);
        $plan = $service->reconcile($manifest);
        $this->assertSame(3, $plan['stats']['calls_to_create']);
        $this->assertSame(3, $plan['stats']['entries_to_create']);
        $this->assertSame(2, $plan['stats']['present_entries']);
        $this->assertSame(1, $plan['stats']['absent_entries']);
        $this->assertSame(1, $plan['stats']['unrecorded_entries']);
        $this->assertSame(180, $plan['maximum_daily_minutes']);
        $this->assertSame(0, DiaryContent::count());
        $result = $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        Storage::disk('local')->assertExists($result['backup_path']);
        $calls = DiaryAttendanceRecord::where('curriculum_component_id', $component->id)->orderBy('class_date')->get();
        $this->assertCount(3, $calls);
        $this->assertNull($calls[0]->teacher_person_id);
        $this->assertNull($calls[0]->legacy_id);
        $this->assertStringContainsString($manifest['batch_id'], $calls[0]->legacy_metadata['administrative_record_key']);
        $this->assertSame('presente', $calls[0]->entries->firstWhere('student_enrollment_id', $departed->id)->status);
        $this->assertSame('falta', $calls[1]->entries->sole()->status);
        $this->assertSame(0, $calls[1]->entries->sole()->attended_lessons);
        $this->assertCount(0, $calls[2]->entries);
        $this->assertSame([$enrollment->id], $calls[2]->legacy_metadata['unrecorded_enrollment_ids']);
        $this->assertSame(AdministrativeDiaryReconstruction::SOURCE, $calls[2]->legacy_metadata['provenance']);
        $this->assertFalse($calls[2]->legacy_metadata['original_event_dates_confirmed']);
        $this->assertFalse($calls[2]->legacy_metadata['original_records_available']);
        $content = DiaryContent::firstOrFail();
        $this->assertTrue($content->legacy_metadata['content_references'][0]['used_as_reference_only']);
        $this->assertNull($content->created_by_person_id);
        $this->assertSame('Formas geométricas e composição.', $content->content);
        $this->assertSame($grades, $enrollment->assessmentResults()->get()->toArray());
        $this->assertSame('aprovado', $enrollment->fresh()->final_result_status);
        $this->assertTrue(AuditLog::where('metadata->operation', AdministrativeDiaryReconstruction::SOURCE)->exists());
        $again = $service->reconcile($manifest);
        $this->assertSame(0, array_sum(array_intersect_key($again['stats'], array_flip(['calls_to_create', 'contents_to_create', 'entries_to_create']))));
        $this->assertTrue($service->reconcile($manifest, true, $again['snapshot_sha256'])['idempotent']);
    }

    public function test_daily_limit_is_shared_across_every_component_of_the_batch(): void
    {
        [$manifest, $class, $component] = $this->scenario();
        $extra = $component->course->components()->create(['name' => 'Oficina', 'active' => true, 'workload_hours' => 5]);
        $assignment = $class->componentAssignments()->create(['curriculum_component_id' => $extra->id, 'active' => true]);
        $diary = $manifest['diaries'][0];
        $diary['assignment_id'] = $assignment->id;
        $diary['component_id'] = $extra->id;
        $diary['expected_lessons'] = 5;
        $diary['sessions'] = [$diary['sessions'][0]];
        $diary['sessions'][0]['lesson_count'] = 5;
        $manifest['diaries'][] = $diary;
        $this->expectExceptionMessage('sete horas diárias');
        try {
            app(AdministrativeDiaryReconstruction::class)->reconcile($manifest);
        } finally {
            $this->assertSame(0, DiaryContent::count());
        }
    }

    public function test_changed_source_snapshot_or_later_manual_edits_are_never_overwritten(): void
    {
        [$manifest, $class, $component] = $this->scenario();
        $service = app(AdministrativeDiaryReconstruction::class);
        $plan = $service->reconcile($manifest);
        DiaryAttendanceRecord::where('school_class_id', $class->id)->firstOrFail()->update(['notes' => 'Conferido pela gestão']);
        try {
            $service->reconcile($manifest, true, $plan['snapshot_sha256']);
            $this->fail('Deveria recusar uma fotografia desatualizada.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mudaram desde a simulação', $e->getMessage());
        }
        $this->assertSame(0, DiaryContent::count());
        $plan = $service->reconcile($manifest);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        DiaryContent::firstOrFail()->update(['content' => 'Retificação pedagógica posterior']);
        $this->expectExceptionMessage('Conteúdo do lote foi alterado');
        $service->reconcile($manifest);
    }

    public function test_enrollment_windows_and_reconstruction_authorization_are_mandatory(): void
    {
        [$manifest, $class, $component, $enrollment, $departed] = $this->scenario();
        $manifest['management_authorizes_audited_reconstruction'] = false;
        try {
            app(AdministrativeDiaryReconstruction::class)->reconcile($manifest);
            $this->fail('Deveria exigir autorização da recomposição auditada.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('não autorizada', $e->getMessage());
        }
        $manifest['management_authorizes_audited_reconstruction'] = true;
        $manifest['diaries'][0]['sessions'][1]['roster'][] = ['enrollment_id' => $departed->id, 'status' => 'presente', 'source_record_ids' => []];
        $this->expectExceptionMessage('vínculos vigentes');
        app(AdministrativeDiaryReconstruction::class)->reconcile($manifest);
    }

    public function test_new_first_period_hours_are_credited_to_the_receiving_class_without_creating_duplicate_calls(): void
    {
        [$manifest, $class, $component, $origin] = $this->scenario();
        $year = $class->academicYear;
        $year->periods()->create(['name' => 'II Bimestre', 'position' => 2, 'starts_at' => '2025-05-01', 'ends_at' => '2025-07-01']);
        $origin->update(['status' => 'reclassificado', 'reclassified_at' => '2025-05-01']);
        $course = $year->courses()->create(['name' => '1º Ano', 'stage' => 'medio', 'class_hour_minutes' => 60]);
        $targetComponent = $course->components()->create(['name' => 'Arte', 'active' => true, 'workload_hours' => 4]);
        $destinationClass = $year->classes()->create(['name' => '1º Ano']);
        $destinationClass->courses()->attach($course);
        $destination = $destinationClass->enrollments()->create(['person_id' => $origin->person_id, 'status' => 'matriculado',
            'enrolled_at' => '2025-05-01', 'reclassified_at' => '2025-05-01', 'reclassified_from_enrollment_id' => $origin->id]);
        $destination->courses()->attach($course);
        $service = app(AdministrativeDiaryReconstruction::class);
        $plan = $service->reconcile($manifest);
        $service->reconcile($manifest, true, $plan['snapshot_sha256']);
        $credit = $destination->periodConvalidations()->where('curriculum_component_id', $targetComponent->id)->sole();
        $this->assertSame(120, $credit->verified_diary_attended_minutes);
        $this->assertSame(3, $credit->attendance_lessons);
        $this->assertStringContainsString('recomposição administrativa auditada', $credit->notes);
        $this->assertSame(0, $destination->attendanceEntries()->count());
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $school = School::create(['name' => 'Internato']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20']);
        $period = $year->periods()->create(['name' => 'I Bimestre', 'position' => 1, 'starts_at' => '2025-02-01', 'ends_at' => '2025-04-30']);
        foreach (['2025-03-10', '2025-03-11', '2025-03-12'] as $date) {
            $year->days()->create(['date' => $date, 'type' => 'letivo', 'counts_as_school_day' => true]);
        }
        $course = $year->courses()->create(['name' => '9º Ano', 'stage' => 'fundamental', 'class_hour_minutes' => 60]);
        $component = $course->components()->create(['name' => 'Arte', 'active' => true, 'workload_hours' => 4]);
        $other = $course->components()->create(['name' => 'Matemática', 'active' => true, 'workload_hours' => 80]);
        $class = $year->classes()->create(['name' => '9º Ano']);
        $class->courses()->attach($course);
        $assignment = $class->componentAssignments()->create(['curriculum_component_id' => $component->id, 'active' => true]);
        $enrollments = [];
        foreach (['Estudante', 'Transferido'] as $name) {
            $person = Person::create(['full_name' => $name]);
            $e = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2025-02-01',
                'status' => $name === 'Transferido' ? 'transferido' : 'matriculado', 'final_result_status' => 'aprovado',
                'transferred_at' => $name === 'Transferido' ? '2025-03-10' : null]);
            $e->courses()->attach($course);
            $enrollments[] = $e;
        }
        [$enrollment, $departed] = $enrollments;
        $first = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $other->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-03-10', 'lesson_count' => 1]);
        foreach ($enrollments as $e) {
            $first->entries()->create(['student_enrollment_id' => $e->id, 'status' => 'presente', 'attended_lessons' => 1]);
        }
        $second = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $other->id,
            'academic_period_id' => $period->id, 'class_date' => '2025-03-11', 'lesson_count' => 1]);
        $second->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'falta', 'attended_lessons' => 0]);
        // Another school's presence contributes load, but never boarding evidence.
        $otherSchool = School::create(['name' => 'Outra escola']);
        $otherYear = AcademicYear::create(['school_id' => $otherSchool->id, 'name' => '2025', 'reference_year' => 2025,
            'starts_at' => '2025-02-01', 'ends_at' => '2025-12-20']);
        $otherCourse = $otherYear->courses()->create(['name' => 'Complementar', 'stage' => 'fundamental', 'class_hour_minutes' => 60]);
        $otherComponent = $otherCourse->components()->create(['name' => 'Oficina', 'active' => true]);
        $otherClass = $otherYear->classes()->create(['name' => 'Oficina']);
        $otherEnrollment = $otherClass->enrollments()->create(['person_id' => $enrollment->person_id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado']);
        $otherEnrollment->courses()->attach($otherCourse);
        $external = DiaryAttendanceRecord::create(['school_class_id' => $otherClass->id, 'curriculum_component_id' => $otherComponent->id, 'class_date' => '2025-03-12', 'lesson_count' => 1]);
        $external->entries()->create(['student_enrollment_id' => $otherEnrollment->id, 'status' => 'presente', 'attended_lessons' => 1]);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'title' => 'Nota existente']);
        $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 8]);
        $sessions = [];
        foreach ([['2025-03-10', 2, 'presente', [$first->id]], ['2025-03-11', 1, 'falta', [$second->id]], ['2025-03-12', 1, null, []]] as [$date, $count, $status, $ids]) {
            $roster = [['enrollment_id' => $enrollment->id, 'status' => $status, 'source_record_ids' => $ids]];
            if ($date === '2025-03-10') {
                $roster[] = ['enrollment_id' => $departed->id, 'status' => 'presente', 'source_record_ids' => [$first->id]];
            }
            $sessions[] = ['date' => $date, 'lesson_count' => $count, 'content' => 'Formas geométricas e composição.',
                'content_references' => [['file' => 'diario-referencia.pdf', 'sha256' => str_repeat('a', 64), 'used_as_reference_only' => true]], 'roster' => $roster];
        }
        $manifest = ['format' => 'administrative-diary-reconstruction-v1', 'batch_id' => 'e11aa31d-5428-4e37-af2a-30b28c39274a',
            'source_snapshot_sha256' => str_repeat('b', 64), 'school_id' => $school->id,
            'management_confirms_classes_taught' => true, 'original_records_available' => false,
            'management_authorizes_audited_reconstruction' => true, 'diaries' => [[
                'assignment_id' => $assignment->id, 'class_id' => $class->id, 'component_id' => $component->id,
                'reference_year' => 2025, 'expected_lessons' => 4, 'sessions' => $sessions]]];

        return [$manifest, $class, $component, $enrollment, $departed];
    }
}
