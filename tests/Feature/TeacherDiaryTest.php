<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryAttendanceJustification;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherDiaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_diary_for_approved_academic_year_component(): void
    {
        [$teacher, $year, $class, $component] = $this->diaryScenario();

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.index'))
            ->assertOk()
            ->assertSee('Matemática')
            ->assertSee('1º Ano A');

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Diários');
    }

    public function test_management_diary_index_is_grouped_by_class_with_filters(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-painel-diarios@ctjj.org');

        $this->actingAs($manager)
            ->get(route('teacher-diaries.index', [
                'academic_year' => $year->id,
                'period' => $period->id,
                'class' => $class->id,
                'component' => $component->id,
                'teacher' => $teacher->person_id,
                'status' => 'waiting',
            ]))
            ->assertOk()
            ->assertSee('Acompanhamento')
            ->assertSee($class->name)
            ->assertSee($component->name)
            ->assertSee('Aguardando');
    }

    public function test_component_with_short_duration_rejects_launches_outside_its_periods(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $secondPeriod = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'name' => '2º Bimestre',
            'starts_at' => '2026-04-13',
            'ends_at' => '2026-07-08',
            'position' => 2,
        ]);
        $component->update([
            'starts_period_id' => $period->id,
            'ends_period_id' => $period->id,
        ]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-componente-curto@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ]);
        $assessment = DiaryAssessment::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $secondPeriod->id,
                'scores' => [$assessment->id => [$enrollment->id => 8]],
            ])
            ->assertSessionHasErrors('academic_period_id');
    }

    public function test_management_can_send_diary_alert_to_teacher(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-alerta-diario@ctjj.org');

        $this->actingAs($manager)
            ->post(route('teacher-diaries.alerts.store', [$class, $component]), [
                'academic_period_id' => $period->id,
                'message' => 'Confira os lançamentos de frequência desta semana.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('diary_alerts', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'to_person_id' => $teacher->person_id,
            'message' => 'Confira os lançamentos de frequência desta semana.',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('Confira os lançamentos de frequência desta semana.');
    }

    public function test_student_can_view_own_diary_entries_without_editing_them(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $student = User::query()->where('person_id', $enrollment->person_id)->firstOrFail();

        $this->actingAs($student)
            ->get(route('student-diaries.index'))
            ->assertOk()
            ->assertSee('Matemática');

        $this->actingAs($student)
            ->get(route('student-diaries.show', [$enrollment, $component]))
            ->assertOk()
            ->assertSee('Notas lançadas');
    }

    public function test_management_configures_assessments_and_teacher_launches_grades_and_attendance(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-avaliacoes@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [2, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $assessment = DiaryAssessment::query()->orderBy('id')->firstOrFail();
        $this->assertSame('Avaliação 1', $assessment->title);
        $this->assertSame(2, $assessment->weight);

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [
                    $assessment->id => [
                        $enrollment->id => 8.5,
                    ],
                ],
            ])
            ->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->assertDatabaseHas('diary_assessment_results', [
            'diary_assessment_id' => $assessment->id,
            'student_enrollment_id' => $enrollment->id,
            'score' => 8.5,
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scheduled_dates' => ['2026-03-11'],
                'page' => 1,
                'lesson_counts' => ['2026-03-11' => 3],
                'attendance' => [
                    '2026-03-11' => [$enrollment->id => [1 => '1']],
                ],
            ])
            ->assertRedirect(route('teacher-diaries.attendance', [$class, $component, 'period' => $period->id, 'page' => 1]));

        $this->assertDatabaseHas('diary_attendance_entries', [
            'student_enrollment_id' => $enrollment->id,
            'status' => DiaryAttendanceRecord::STATUS_PARTIAL,
            'attended_lessons' => 1,
        ]);
        $this->assertDatabaseHas('diary_attendance_records', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'lesson_count' => 3,
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scheduled_dates' => ['2026-03-11'],
                'page' => 1,
                'lesson_counts' => ['2026-03-11' => 2],
                'attendance' => ['2026-03-11' => [$enrollment->id => [1 => '1', 2 => '1']]],
            ])
            ->assertRedirect();

        $this->assertSame(1, DiaryAttendanceRecord::query()
            ->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)
            ->whereDate('class_date', '2026-03-11')
            ->count());
        $this->assertDatabaseHas('diary_attendance_records', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'lesson_count' => 2,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('8.5');
    }

    public function test_teacher_cannot_change_school_assessment_rules(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();

        $this->actingAs($teacher)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertForbidden();
    }

    public function test_management_can_configure_weighted_recovery_for_a_period(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-recuperacao@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [2, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_WEIGHTED,
                'recovery_weight' => 3,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('academic_periods', [
            'id' => $period->id,
            'recovery_mode' => AcademicPeriod::RECOVERY_WEIGHTED,
            'recovery_weight' => 3,
        ]);
        $this->assertDatabaseHas('diary_assessments', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'is_recovery' => true,
            'recovery_mode' => AcademicPeriod::RECOVERY_WEIGHTED,
            'weight' => 3,
        ]);
    }

    public function test_weighted_recovery_uses_default_weight_when_period_type_is_changed(): void
    {
        [$teacher, $year, $class, $component] = $this->diaryScenario();
        $period = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'name' => '2º Bimestre',
            'starts_at' => '2026-04-13',
            'ends_at' => '2026-07-08',
            'position' => 2,
        ]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-recuperacao-padrao@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'period_form_id' => $period->id,
                'assessment_count' => 2,
                'weights' => [1, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_WEIGHTED,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('academic_periods', [
            'id' => $period->id,
            'recovery_mode' => AcademicPeriod::RECOVERY_WEIGHTED,
            'recovery_weight' => 1,
        ]);
        $this->assertDatabaseHas('diary_assessments', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'is_recovery' => true,
            'weight' => 1,
        ]);
    }

    public function test_management_can_configure_recovery_to_replace_a_specific_assessment(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-substituicao@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [1, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT,
                'recovery_replaced_position' => 2,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $replacedRule = $period->fresh()->assessmentRules()->where('position', 2)->firstOrFail();
        $this->assertDatabaseHas('diary_assessments', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'is_recovery' => true,
            'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT,
            'recovery_replaced_rule_id' => $replacedRule->id,
        ]);
    }

    public function test_recovery_can_replace_the_lowest_launched_grade_in_the_period_average(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-menor-nota@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [1, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_LOWEST,
            ]);

        $assessments = DiaryAssessment::query()
            ->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)
            ->orderBy('is_recovery')
            ->orderBy('school_assessment_rule_id')
            ->get();
        $regularAssessments = $assessments->where('is_recovery', false)->values();
        $recoveryAssessment = $assessments->firstWhere('is_recovery', true);

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [
                    $regularAssessments[0]->id => [$enrollment->id => 4],
                    $regularAssessments[1]->id => [$enrollment->id => 8],
                    $recoveryAssessment->id => [$enrollment->id => 9],
                ],
            ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->assertSame(8.5, $response->viewData('averages')[$enrollment->id]['value']);
    }

    public function test_period_average_is_rounded_to_the_nearest_half_point(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-arredondamento@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ]);

        $assessment = DiaryAssessment::query()->firstOrFail();
        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 8.3]],
            ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->assertSame(8.5, $response->viewData('averages')[$enrollment->id]['value']);
    }

    public function test_content_attendance_and_grades_can_be_confirmed_and_reopened_by_management(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-fechamento@ctjj.org');

        $this->actingAs($manager)->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
            'assessment_count' => 1,
            'weights' => [1],
            'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
        ]);
        $assessment = DiaryAssessment::query()->firstOrFail();

        $this->actingAs($teacher)->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scheduled_dates' => ['2026-03-11'],
            'page' => 1,
            'lesson_counts' => ['2026-03-11' => 1],
            'attendance' => ['2026-03-11' => [$enrollment->id => [1 => '1']]],
        ]);
        $this->actingAs($teacher)->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'selected_dates' => ['2026-03-11'],
            'contents' => ['2026-03-11' => 'Revisão de equações do primeiro grau.'],
        ]);
        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scores' => [$assessment->id => [$enrollment->id => 8]],
        ]);

        $this->actingAs($teacher)->post(route('teacher-diaries.confirmation.confirm', [$class, $component]), [
            'academic_period_id' => $period->id,
        ])->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->assertDatabaseHas('diary_period_confirmations', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'confirmed' => true,
        ]);

        $this->actingAs($teacher)->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'starts_at' => '2026-03-11',
            'ends_at' => '2026-03-11',
            'contents' => ['2026-03-11' => 'Ajuste posterior.'],
        ])->assertSessionHasErrors('academic_period_id');

        $this->actingAs($manager)->post(route('teacher-diaries.confirmation.reopen', [$class, $component]), [
            'academic_period_id' => $period->id,
            'reopen_reason' => 'Correção solicitada pela coordenação.',
        ])->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->assertDatabaseHas('diary_period_confirmations', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'confirmed' => false,
        ]);
    }

    public function test_management_consolidates_period_only_after_all_diaries_are_confirmed(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-consolidacao@ctjj.org');

        $this->actingAs($manager)->post(route('academic-years.periods.diaries.consolidate', [$year, $period]))
            ->assertSessionHasErrors('period');

        $this->actingAs($manager)->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
            'assessment_count' => 1,
            'weights' => [1],
            'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
        ]);
        $assessment = DiaryAssessment::query()->firstOrFail();

        $this->actingAs($teacher)->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scheduled_dates' => ['2026-03-11'],
            'page' => 1,
            'lesson_counts' => ['2026-03-11' => 1],
            'attendance' => ['2026-03-11' => [$enrollment->id => [1 => '1']]],
        ]);
        $this->actingAs($teacher)->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'selected_dates' => ['2026-03-11'],
            'contents' => ['2026-03-11' => 'Estudo dirigido.'],
        ]);
        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scores' => [$assessment->id => [$enrollment->id => 8]],
        ]);
        $this->actingAs($teacher)->post(route('teacher-diaries.confirmation.confirm', [$class, $component]), [
            'academic_period_id' => $period->id,
        ]);

        $this->actingAs($manager)->post(route('academic-years.periods.diaries.consolidate', [$year, $period]))
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('academic_period_diary_consolidations', [
            'academic_period_id' => $period->id,
            'consolidated' => true,
            'consolidated_by_person_id' => $manager->person_id,
        ]);

        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scores' => [$assessment->id => [$enrollment->id => 9]],
        ])->assertSessionHasErrors('academic_period_id');

        $this->actingAs($manager)->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
            'assessment_count' => 2,
            'weights' => [1, 1],
            'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
        ])->assertSessionHasErrors('assessment_count');

        $this->actingAs($manager)->post(route('academic-years.periods.diaries.reopen', [$year, $period]), [
            'reopen_reason' => 'Correção necessária nos lançamentos.',
        ])->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('academic_period_diary_consolidations', [
            'academic_period_id' => $period->id,
            'consolidated' => false,
            'reopened_by_person_id' => $manager->person_id,
        ]);
        $this->assertDatabaseHas('diary_period_confirmations', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'confirmed' => false,
        ]);
    }

    public function test_period_management_screen_handles_diary_date_pendencies(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-pendencias-diario@ctjj.org');

        $this->actingAs($teacher)->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'selected_dates' => ['2026-03-11'],
            'contents' => ['2026-03-11' => 'Conteúdo lançado sem frequência.'],
        ]);

        $this->actingAs($manager)
            ->get(route('academic-years.periods.index', $year))
            ->assertOk()
            ->assertSee('conteúdo sem freq.');
    }

    public function test_teacher_can_download_diary_pdf_by_period(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.pdf', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_teacher_cannot_access_unapproved_year_diary(): void
    {
        [$teacher, $year, $class, $component] = $this->diaryScenario(['approved_at' => null]);

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component]))
            ->assertForbidden();
    }

    public function test_diary_requires_dates_inside_period_and_calls_on_school_days(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scheduled_dates' => ['2026-03-12'],
                'page' => 1,
                'lesson_counts' => ['2026-03-12' => 1],
                'attendance' => ['2026-03-12' => [$enrollment->id => [1 => '1']]],
            ])
            ->assertSessionHasErrors('scheduled_dates');
    }

    public function test_diary_without_a_class_schedule_keeps_manual_date_selection(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $class->schedules()->each(fn ($schedule) => $schedule->delete());

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.attendance', [
                $class,
                $component,
                'period' => $period->id,
                'starts_at' => '2026-03-11',
                'ends_at' => '2026-03-11',
            ]))
            ->assertOk()
            ->assertSee('Período de lançamento');

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'starts_at' => '2026-03-11',
                'ends_at' => '2026-03-11',
                'lesson_counts' => ['2026-03-11' => 2],
                'attendance' => ['2026-03-11' => [$enrollment->id => [1 => '1', 2 => '1']]],
            ])
            ->assertRedirect(route('teacher-diaries.attendance', [
                $class,
                $component,
                'period' => $period->id,
                'starts_at' => '2026-03-11',
                'ends_at' => '2026-03-11',
            ]));

        $this->assertDatabaseHas('diary_attendance_records', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'class_date' => '2026-03-11 00:00:00',
            'lesson_count' => 2,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.contents', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('11/03');
    }

    public function test_only_management_can_register_absence_justifications_for_an_enrollment(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao@ctjj.org');

        $payload = [
            'student_enrollment_id' => $enrollment->id,
            'starts_at' => '2026-03-10',
            'ends_at' => '2026-03-11',
            'reason' => 'Atestado médico apresentado.',
        ];

        $this->actingAs($teacher)
            ->post(route('attendance-justifications.store'), $payload)
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('attendance-justifications.store'), $payload)
            ->assertRedirect(route('attendance-justifications.index', ['school' => $year->school_id]));

        $this->assertDatabaseHas('diary_attendance_justifications', [
            'student_enrollment_id' => $enrollment->id,
            'reason' => 'Atestado médico apresentado.',
            'granted_by_person_id' => $manager->person_id,
        ]);

        $this->assertSame(1, DiaryAttendanceJustification::query()->count());
    }

    public function test_management_cannot_change_assessment_configuration_after_grade_entry(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-protegida@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $assessment = DiaryAssessment::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 8]],
            ])
            ->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [1, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertSessionHasErrors('assessment_count');
    }

    public function test_management_can_change_only_recovery_rule_after_regular_grade_entry(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-recuperacao-tardia@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [5, 5],
                'assessment_names' => ['Avaliação 1', 'Avaliação 2'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $assessment = DiaryAssessment::query()
            ->where('is_recovery', false)
            ->orderBy('id')
            ->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 7.5]],
            ])
            ->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'period_form_id' => $period->id,
                'assessment_count' => 2,
                'weights' => [99, 99],
                'assessment_names' => ['Nome que não deve alterar', 'Outro nome que não deve alterar'],
                'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_LOWEST,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 1,
            'name' => 'Avaliação 1',
            'weight' => 5,
        ]);
        $this->assertDatabaseHas('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 2,
            'name' => 'Avaliação 2',
            'weight' => 5,
        ]);
        $this->assertDatabaseHas('academic_periods', [
            'id' => $period->id,
            'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_LOWEST,
        ]);
        $this->assertDatabaseHas('diary_assessments', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'is_recovery' => true,
            'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_LOWEST,
        ]);
    }

    public function test_diary_show_creates_missing_recovery_column_from_period_rule(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-recuperacao-diario@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [5, 5],
                'assessment_names' => ['Avaliação 1', 'Avaliação 2'],
                'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_LOWEST,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        DiaryAssessment::query()
            ->where('academic_period_id', $period->id)
            ->where('is_recovery', true)
            ->delete();

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('Recuperação')
            ->assertSee('Substitui a menor nota');

        $this->assertDatabaseHas('diary_assessments', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'is_recovery' => true,
            'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_LOWEST,
        ]);
    }

    public function test_diary_rejects_grade_above_assessment_maximum(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-notas@ctjj.org');
        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ]);
        $assessment = DiaryAssessment::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 10.1]],
            ])
            ->assertSessionHasErrors("scores.{$assessment->id}.{$enrollment->id}");
    }

    /**
     * @return array{0: User, 1: AcademicYear, 2: SchoolClass, 3: CurriculumComponent, 4: AcademicPeriod, 5: StudentEnrollment}
     */
    private function diaryScenario(array $yearOverrides = []): array
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);
        $teacher = $this->userWithRole(PersonSchoolRole::ROLE_TEACHER, $school->id, 'docente@ctjj.org');
        $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $school->id, 'estudante@ctjj.org');

        $year = AcademicYear::query()->create(array_merge([
            'school_id' => $school->id,
            'name' => 'Educação Básica',
            'reference_year' => 2026,
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
            'approved_at' => '2025-12-10',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ], $yearOverrides));

        $period = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'name' => '1º Bimestre',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-04-10',
            'position' => 1,
        ]);

        $year->days()->create([
            'date' => '2026-03-11',
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);

        $course = $year->courses()->create([
            'name' => '1º Ano',
            'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);

        $component = $course->components()->create([
            'name' => 'Matemática',
            'teacher_person_id' => $teacher->person_id,
            'weekly_lessons' => 5,
            'workload_hours' => 160,
            'active' => true,
        ]);

        $class = $year->classes()->create([
            'name' => '1º Ano A',
            'active' => true,
        ]);
        $class->courses()->attach($course);
        $assignment = SchoolClassComponent::query()->create([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'teacher_person_id' => $teacher->person_id,
            'active' => true,
        ]);
        $schedule = $class->schedules()->create([
            'name' => 'Horário regular',
            'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18',
        ]);
        $schedule->slots()->create([
            'weekday' => 3,
            'starts_at' => '08:00',
            'ends_at' => '08:50',
            'type' => 'aula',
            'school_class_component_id' => $assignment->id,
        ]);

        $enrollment = $class->enrollments()->create([
            'person_id' => $student->person_id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($course);

        return [$teacher, $year, $class, $component, $period, $enrollment];
    }

    private function userWithRole(string $role, int $schoolId, string $email): User
    {
        $person = Person::query()->create([
            'full_name' => fake()->name(),
            'institutional_email' => $email,
            'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'mother_name' => 'Maria da Silva',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
            'active' => true,
        ]);

        $person->schoolRoles()->create([
            'school_id' => $schoolId,
            'role' => $role,
            'started_at' => '2026-01-01',
            'active' => true,
        ]);

        return User::factory()->create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $email,
        ]);
    }
}
