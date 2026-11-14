<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceRecord;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Support\TechnicalLegacyEnrollmentRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TechnicalLegacyEnrollmentRestorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_only_missing_grades_and_2025_presences_without_reactivating_transferred_student(): void
    {
        [$request, $person, $other, $role] = $this->scenario();
        $roleBefore = $role->fresh()->getAttributes();
        $otherBefore = $other->fresh()->getAttributes();
        $service = app(TechnicalLegacyEnrollmentRestorer::class);
        $plan = $service->reconcile($request);
        $this->assertSame(['enrollments_to_create' => 1, 'grades_to_create' => 1, 'presences_to_create' => 2], $plan['stats']);
        $this->assertDatabaseCount('student_enrollments', 1);
        $applied = $service->reconcile($request, true, $plan['snapshot_sha256']);
        $enrollment = StudentEnrollment::where('person_id', $person->id)->sole();
        $this->assertSame('transferido', $enrollment->status);
        $this->assertNull($enrollment->transferred_at);
        $this->assertTrue($enrollment->legacy_metadata['transfer_date_pending']);
        $this->assertSame('2025-02-19', $enrollment->enrolled_at->toDateString());
        $this->assertSame([$request['course_id']], $enrollment->courses->modelKeys());
        $this->assertSame('7.50', $enrollment->assessmentResults()->sole()->score);
        $this->assertSame([2, 1], $enrollment->attendanceEntries()->orderBy('diary_attendance_record_id')->pluck('attended_lessons')->all());
        $this->assertSame([true, true], $enrollment->attendanceEntries()->orderBy('id')->first()->lesson_presence);
        $this->assertSame($roleBefore, $role->fresh()->getAttributes());
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        Storage::disk('local')->assertExists($applied['backup_path']);
        $again = $service->reconcile($request);
        $this->assertSame(0, array_sum($again['stats']));
        $service->reconcile($request, true, $again['snapshot_sha256']);
        $this->assertDatabaseCount('student_enrollments', 2);
        $this->assertDatabaseCount('diary_attendance_records', 5);
    }

    public function test_exact_transfer_date_is_inclusive_and_excludes_later_calls(): void
    {
        [$request, $person] = $this->scenario();
        $request['attendance_through'] = '2026-02-28';
        $request['students'][0]['transferred_at'] = '2026-02-03';
        $service = app(TechnicalLegacyEnrollmentRestorer::class);
        $plan = $service->reconcile($request);
        $this->assertSame(3, $plan['stats']['presences_to_create']);
        $service->reconcile($request, true, $plan['snapshot_sha256']);
        $enrollment = StudentEnrollment::where('person_id', $person->id)->sole();
        $this->assertSame('2026-02-03', $enrollment->attendanceEndsAt());
        $this->assertFalse($enrollment->legacy_metadata['transfer_date_pending']);
        $this->assertSame([], $service->reconcile($request)['pending_transfer_dates']);
    }

    public function test_unknown_transfer_date_prevents_filling_any_2026_call(): void
    {
        [$request] = $this->scenario();
        $request['attendance_through'] = '2026-01-01';
        $this->expectExceptionMessage('data de transferência');
        app(TechnicalLegacyEnrollmentRestorer::class)->reconcile($request);
    }

    public function test_rejects_stale_snapshot_and_never_overwrites_existing_grade(): void
    {
        [$request, $person, $other] = $this->scenario();
        $service = app(TechnicalLegacyEnrollmentRestorer::class);
        $plan = $service->reconcile($request);
        $other->update(['score' => 9]);
        try {
            $service->reconcile($request, true, $plan['snapshot_sha256']);
            $this->fail('Uma edição posterior deve invalidar a simulação.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('mudaram', $error->getMessage());
        }
        $this->assertFalse(StudentEnrollment::where('person_id', $person->id)->exists());
        $plan = $service->reconcile($request);
        $service->reconcile($request, true, $plan['snapshot_sha256']);
        $enrollment = StudentEnrollment::where('person_id', $person->id)->sole();
        $enrollment->assessmentResults()->sole()->update(['score' => 8]);
        $this->expectExceptionMessage('Existe uma nota diferente');
        $service->reconcile($request);
    }

    public function test_rejects_source_identity_mismatch_before_creating_any_record(): void
    {
        [$request] = $this->scenario();
        $request['students'][0]['legacy_row']['users_id'] = '999';
        $this->expectExceptionMessage('Identidade técnica divergente');
        app(TechnicalLegacyEnrollmentRestorer::class)->reconcile($request);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('u810745753_beaba.sql', 'fonte legada privada de teste');
        $school = School::create(['name' => 'Escola', 'legacy_source' => 'beaba', 'legacy_id' => 1]);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025–2026', 'reference_year' => 2025,
            'starts_at' => '2025-02-03', 'ends_at' => '2026-12-22', 'class_hour_minutes' => 60]);
        $period = $year->periods()->create(['name' => 'Módulo I', 'position' => 1, 'starts_at' => '2025-02-03', 'ends_at' => '2026-12-22']);
        $course = $year->courses()->create(['name' => 'Técnico', 'stage' => 'tecnico', 'class_hour_minutes' => 60, 'workload_hours' => 120]);
        $component = $course->components()->create(['name' => 'Desenho', 'workload_hours' => 120, 'active' => true]);
        $class = $year->classes()->create(['name' => '2025–2026']);
        $class->courses()->attach($course);
        $person = Person::create(['full_name' => 'Estudante transferido', 'legacy_source' => 'beaba', 'legacy_id' => 83, 'active' => false]);
        $role = PersonSchoolRole::create(['person_id' => $person->id, 'school_id' => $school->id, 'role' => 'aluno', 'active' => false, 'ended_at' => '2026-07-07']);
        $otherPerson = Person::create(['full_name' => 'Outro estudante']);
        $otherEnrollment = $class->enrollments()->create(['person_id' => $otherPerson->id, 'status' => 'matriculado', 'enrolled_at' => '2025-02-03']);
        $otherEnrollment->courses()->attach($course);
        $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'title' => 'Avaliação', 'is_recovery' => false, 'weight' => 10, 'maximum_score' => 10]);
        $other = DiaryAssessmentResult::create(['diary_assessment_id' => $assessment->id, 'student_enrollment_id' => $otherEnrollment->id, 'score' => 8.25]);
        foreach (['2025-02-18' => 2, '2025-02-19' => 2, '2025-12-19' => 1, '2026-02-03' => 2, '2026-02-04' => 1] as $date => $quantity) {
            $call = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id, 'class_date' => $date, 'lesson_count' => $quantity]);
            $call->entries()->create(['student_enrollment_id' => $otherEnrollment->id, 'status' => 'falta', 'attended_lessons' => 0]);
        }
        $request = ['format' => 'technical-legacy-enrollments-v1', 'inclusion_and_presence_approved' => true,
            'course_id' => $course->id, 'class_id' => $class->id, 'school_id' => $school->id, 'reference_year' => 2025,
            'legacy_course_id' => 13, 'legacy_dump_sha256' => hash_file('sha256', Storage::disk('local')->path('u810745753_beaba.sql')),
            'attendance_through' => '2025-12-31', 'students' => [['person_id' => $person->id, 'confirmed_transfer_year' => 2026,
                'legacy_row' => ['id' => '106', 'users_id' => '83', 'cursos_id' => '13', 'datamatricula' => '2025-02-19', 'datatransf' => null, 'status' => 'matriculado', 'tipo' => 'regular'],
                'grades' => [['component_id' => $component->id, 'period_id' => $period->id, 'score' => 7.5,
                    'source_grade_kind' => 'reported_average', 'source_pdf_sha256' => str_repeat('a', 64)]]]]];

        return [$request, $person, $other, $role];
    }
}
