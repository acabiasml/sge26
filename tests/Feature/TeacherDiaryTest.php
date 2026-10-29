<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodDiaryConsolidation;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\CalendarDay;
use App\Models\CurriculumComponent;
use App\Models\DiaryAlert;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryPeriodConfirmation;
use App\Models\IssuedDocument;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolAssessmentRule;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\AttendanceSummaryCalculator;
use App\Support\CurriculumCatalog;
use App\Support\DiaryPeriodStatus;
use App\Support\PdfLetterhead;
use App\Support\SchoolClassEnrollmentCourses;
use App\Support\StudentAttendanceCertificateBuilder;
use App\Support\StudentProgressSummary;
use App\Support\StudentReportCardBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class TeacherDiaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returning_student_report_combines_prior_enrollment_origin_sheet_and_current_diary(): void
    {
        [$teacher, $year, $class, $component, $first, $original] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'retorno@ctjj.org');
        $second = $year->periods()->create(['name' => '2º Bimestre', 'starts_at' => '2026-04-11', 'ends_at' => '2026-06-30', 'position' => 2]);
        $third = $year->periods()->create(['name' => '3º Bimestre', 'starts_at' => '2026-07-01', 'ends_at' => '2026-09-30', 'position' => 3]);
        $fourth = $year->periods()->create(['name' => '4º Bimestre', 'starts_at' => '2026-10-01', 'ends_at' => '2026-12-18', 'position' => 4]);

        $assessment = DiaryAssessment::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $first->id, 'title' => 'Nota antes da saída', 'assessment_date' => '2026-03-10']);
        $assessment->results()->create(['student_enrollment_id' => $original->id, 'score' => 8]);
        $lateAssessment = DiaryAssessment::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $third->id, 'title' => 'Lançamento indevido após saída', 'assessment_date' => '2026-08-10']);
        $lateAssessment->results()->create(['student_enrollment_id' => $original->id, 'score' => 0]);
        $original->update(['status' => StudentEnrollment::STATUS_TRANSFERRED, 'transferred_at' => '2026-04-10']);

        foreach ([['2026-03-11', $first->id, $original->id, 1], ['2026-08-10', $third->id, $original->id, 0]] as [$date, $periodId, $enrollmentId, $attended]) {
            $record = DiaryAttendanceRecord::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $periodId, 'class_date' => $date, 'lesson_count' => 2]);
            $record->entries()->create(['student_enrollment_id' => $enrollmentId, 'status' => DiaryAttendanceRecord::STATUS_PARTIAL, 'attended_lessons' => $attended]);
        }

        $returnClass = $year->classes()->create(['name' => '1º Ano B', 'active' => true]);
        $returnClass->courses()->attach($component->course);
        $returned = $returnClass->enrollments()->create(['person_id' => $original->person_id, 'enrolled_at' => '2026-10-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
        $returned->courses()->attach($component->course);
        foreach ([[$second, 7, 20, 2], [$third, 9, 25, 3]] as [$period, $score, $lessons, $absences]) {
            $returned->periodConvalidations()->create(['academic_period_id' => $period->id, 'curriculum_component_id' => $component->id,
                'score' => $score, 'attendance_lessons' => $lessons, 'attendance_absences' => $absences,
                'source_school' => 'Escola fictícia', 'convalidated_at' => '2026-10-01']);
        }
        $currentAssessment = DiaryAssessment::query()->create(['school_class_id' => $returnClass->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $fourth->id, 'title' => 'Nota do retorno', 'assessment_date' => '2026-11-10']);
        $currentAssessment->results()->create(['student_enrollment_id' => $returned->id, 'score' => 6]);
        $currentRecord = DiaryAttendanceRecord::query()->create(['school_class_id' => $returnClass->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $fourth->id, 'class_date' => '2026-11-10', 'lesson_count' => 2]);
        $currentRecord->entries()->create(['student_enrollment_id' => $returned->id, 'status' => DiaryAttendanceRecord::STATUS_PARTIAL, 'attended_lessons' => 1]);

        $report = app(StudentReportCardBuilder::class)->build($returned->fresh());
        $this->assertSame([8.0, 7.0, 9.0, 6.0], $report['periodReports']->map(fn ($period) => $period['components']->first()['average']['value'])->all());
        $this->assertSame([1, 2, 3, 1], $report['periodReports']->map(fn ($period) => $period['components']->first()['attendance']['absent'])->all());
        $this->assertSame(7, $report['annualAttendance']['absent']);
        $this->assertSame(49, $report['annualAttendance']['lessons']);
        $this->assertSame(30.0, $report['annualComponents']->first()['points']);

        $certificate = app(StudentAttendanceCertificateBuilder::class)->build($returned->fresh(), $year->starts_at, $year->ends_at);
        $this->assertSame(49, $certificate['attendance']['lessons']);
        $this->assertSame(7, $certificate['attendance']['absent']);
        $march = app(StudentAttendanceCertificateBuilder::class)->build($returned->fresh(), CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));
        $this->assertSame(2, $march['attendance']['lessons']);

        $this->actingAs($manager)->get(route('enrollments.report-card.show', $returned))->assertOk()->assertSee('Escola fictícia');
        $this->get(route('enrollments.individual-record.pdf', $returned))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_returning_student_in_another_network_school_keeps_previous_period_by_series_and_component(): void
    {
        [$teacher, $year, $class, $component, $first, $original] = $this->diaryScenario();
        $assessment = DiaryAssessment::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $first->id, 'title' => 'Nota da escola anterior', 'assessment_date' => '2026-03-10']);
        $assessment->results()->create(['student_enrollment_id' => $original->id, 'score' => 8]);
        $record = DiaryAttendanceRecord::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $first->id, 'class_date' => '2026-03-11', 'lesson_count' => 2]);
        $record->entries()->create(['student_enrollment_id' => $original->id, 'status' => DiaryAttendanceRecord::STATUS_PARTIAL, 'attended_lessons' => 1]);
        $original->update(['status' => StudentEnrollment::STATUS_TRANSFERRED, 'transferred_at' => '2026-04-10']);

        $otherSchool = School::query()->create($this->officialSchoolData(['name' => 'Escola B']));
        $otherYear = $otherSchool->academicYears()->create(['name' => 'Educação Básica', 'reference_year' => 2026,
            'starts_at' => '2026-01-20', 'ends_at' => '2026-12-18', 'active' => true]);
        $otherFirst = $otherYear->periods()->create(['name' => '1º Bimestre', 'starts_at' => '2026-02-01', 'ends_at' => '2026-04-10', 'position' => 1]);
        $otherCourse = $otherYear->courses()->create(['name' => '1º Ano', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado', 'class_hour_minutes' => 50, 'active' => true]);
        $otherComponent = $otherCourse->components()->create(['name' => 'Matemática', 'weekly_lessons' => 5, 'active' => true]);
        $otherClass = $otherYear->classes()->create(['name' => '1º Ano B', 'active' => true]);
        $otherClass->courses()->attach($otherCourse);
        $returned = $otherClass->enrollments()->create(['person_id' => $original->person_id, 'enrolled_at' => '2026-10-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
        $returned->courses()->attach($otherCourse);

        $report = app(StudentReportCardBuilder::class)->build($returned->fresh());
        $this->assertSame(8.0, $report['periodReports']->firstWhere('period.id', $otherFirst->id)['components']->first()['average']['value']);
        $this->assertSame($otherComponent->id, $report['annualComponents']->first()['component']->id);
        $this->assertSame(1, $report['annualAttendance']['absent']);
        $certificate = app(StudentAttendanceCertificateBuilder::class)->build($returned->fresh(), $otherYear->starts_at, $otherYear->ends_at);
        $this->assertSame(1, $certificate['attendance']['absent']);
    }

    public function test_management_saves_origin_sheet_together_and_reopens_existing_values(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'sheet-manager@ctjj.org');
        $second = $year->periods()->create(['name' => '2º Bimestre', 'starts_at' => '2026-04-11', 'ends_at' => '2026-06-30', 'position' => 2]);
        $another = $component->course->components()->create(['name' => 'História', 'active' => true, 'weekly_lessons' => 2]);
        $key = $period->id.'_'.$component->id;
        $secondKey = $second->id.'_'.$another->id;
        $data = ['source_school' => 'Escola anterior', 'previous_source_school' => '', 'convalidated_at' => '2026-07-01', 'sheet_complete' => 1,
            'rows' => [$key => ['score' => '7,5', 'attendance_lessons' => 40, 'attendance_absences' => 3],
                $secondKey => ['score' => '0', 'attendance_lessons' => 30, 'attendance_absences' => 0]]];
        $this->actingAs($manager)->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $enrollment->periodConvalidations()->count());
        $this->assertDatabaseCount('diary_attendance_records', 0);
        $this->assertDatabaseCount('diary_assessment_results', 0);
        $this->get(route('enrollments.origin-sheet', $enrollment))->assertOk()->assertSee('value="Escola anterior"', false)->assertSee('value="7.5"', false);
        $data['previous_source_school'] = 'Escola anterior';
        $data['rows'][$key]['score'] = '8,0';
        $this->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $enrollment->periodConvalidations()->count());
        $this->assertSame('8.0', $enrollment->periodConvalidations()->where('academic_period_id', $period->id)->sole()->score);
        $data['rows'][$key]['score'] = 9;
        $data['rows'][$secondKey]['attendance_absences'] = 99;
        $this->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertSessionHasErrors('rows.'.$secondKey.'.attendance_absences');
        $this->assertSame('8.0', $enrollment->periodConvalidations()->where('academic_period_id', $period->id)->sole()->score);
        $data['rows'][$secondKey]['attendance_absences'] = 0;
        $data['rows'][$key] = ['score' => '', 'attendance_lessons' => '', 'attendance_absences' => ''];
        $this->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertSessionHasNoErrors();
        $this->assertSame('8.0', $enrollment->periodConvalidations()->where('academic_period_id', $period->id)->sole()->score);
        unset($data['sheet_complete']);
        $this->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertSessionHasErrors('sheet_complete');
        $this->actingAs($teacher)->get(route('enrollments.origin-sheet', $enrollment))->assertForbidden();
        $this->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertForbidden();
    }

    public function test_english_closure_inspection_matches_origin_records_without_writing_school_records(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29'));
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $component->update(['name' => 'Língua Inglesa']);
        $rule = SchoolAssessmentRule::query()->where('academic_period_id', $period->id)->orderBy('position')->firstOrFail();
        DiaryAssessment::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'school_assessment_rule_id' => $rule->id,
            'teacher_person_id' => $teacher->person_id, 'title' => 'Avaliação', 'assessment_date' => '2026-03-11',
        ]);
        $enrollment->periodConvalidations()->create([
            'academic_period_id' => $period->id, 'curriculum_component_id' => $component->id,
            'score' => 7.5, 'source_school' => 'Escola de origem', 'convalidated_by_person_id' => $teacher->person_id,
        ]);
        $auditCount = AuditLog::query()->count();
        ob_start();
        try {
            require base_path('scripts/inspect-english-closures.php');
            $report = json_decode(ob_get_contents(), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
        $this->assertCount(1, $report['diaries']);
        $this->assertSame($enrollment->id, $report['diaries'][0]['missing'][0]['enrollment_id']);
        $this->assertSame('7.5', $report['diaries'][0]['missing'][0]['origin']['score']);
        $this->assertSame(0, $report['diaries'][0]['pending']['missing_grades']);
        $this->assertDatabaseCount('diary_assessment_results', 0);
        $this->assertDatabaseCount('diary_period_confirmations', 0);
        $this->assertDatabaseCount('student_period_convalidations', 1);
        $this->assertSame($auditCount, AuditLog::query()->count());
    }

    public function test_diary_closure_accepts_origin_grades_including_zero_and_keeps_real_pendencies(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29'));
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'origin-closure-manager@ctjj.org');
        $this->actingAs($manager)->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
            'assessment_count' => 2, 'weights' => [1, 1], 'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
        ])->assertSessionHasNoErrors();
        $assessments = DiaryAssessment::query()->where('is_recovery', false)->get();
        $this->assertCount(2, $assessments);
        $others = collect();
        foreach (['zero-origin', 'missing-origin'] as $key) {
            $student = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, $key.'@ctjj.org');
            $other = $class->enrollments()->create(['person_id' => $student->person_id, 'enrolled_at' => '2026-02-01', 'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
            $other->courses()->attach($component->academic_course_id);
            $others->put($key, $other);
        }
        foreach ([[$enrollment, 7.5], [$others['zero-origin'], 0]] as [$studentEnrollment, $score]) {
            $studentEnrollment->periodConvalidations()->create([
                'academic_period_id' => $period->id, 'curriculum_component_id' => $component->id,
                'score' => $score, 'source_school' => 'Escola de origem', 'convalidated_by_person_id' => $manager->person_id,
            ]);
        }
        $otherComponent = $component->course->components()->create(['name' => 'Outro componente', 'active' => true]);
        $otherPeriod = $year->periods()->create(['name' => '2º Bimestre', 'starts_at' => '2026-04-11', 'ends_at' => '2026-06-30', 'position' => 2]);
        foreach ([[$period->id, $otherComponent->id], [$otherPeriod->id, $component->id]] as [$periodId, $componentId]) {
            $others['missing-origin']->periodConvalidations()->create(['academic_period_id' => $periodId, 'curriculum_component_id' => $componentId, 'score' => 9]);
        }
        $assignment = SchoolClassComponent::query()->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)->firstOrFail();
        $this->assertSame(2, app(DiaryPeriodStatus::class)->pending($assignment, $period)['missing_grades']);
        $parameters = [$class, $component, 'period' => $period->id];
        $this->actingAs($teacher)->get(route('teacher-diaries.show', $parameters))
            ->assertOk()->assertViewHas('diaryPending', fn ($pending) => $pending['missing_grades'] === 2);
        $this->post(route('teacher-diaries.confirmation.confirm', [$class, $component]), ['academic_period_id' => $period->id])
            ->assertSessionHasErrors('academic_period_id');
        foreach ($assessments as $assessment) {
            $assessment->results()->create(['student_enrollment_id' => $others['missing-origin']->id, 'score' => 8]);
        }
        $record = DiaryAttendanceRecord::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => '2026-03-11', 'lesson_count' => 1,
        ]);
        $pending = app(DiaryPeriodStatus::class)->pending($assignment, $period);
        $this->assertSame(0, $pending['missing_grades']);
        $this->assertSame(['2026-03-11'], $pending['attendance_without_content']);
        $this->post(route('teacher-diaries.confirmation.confirm', [$class, $component]), ['academic_period_id' => $period->id])
            ->assertSessionHasErrors('academic_period_id');
        $this->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id, 'selected_dates' => ['2026-03-11'], 'contents' => ['2026-03-11' => 'Conteúdo ministrado.'],
        ])->assertSessionHasNoErrors();
        $this->get(route('teacher-diaries.show', $parameters))
            ->assertOk()->assertViewHas('diaryPending', fn ($pending) => $pending['missing_grades'] === 0);
        $this->assertFalse(app(DiaryPeriodStatus::class)->pending($assignment, $period)['is_pending']);
        $this->post(route('teacher-diaries.confirmation.confirm', [$class, $component]), ['academic_period_id' => $period->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('diary_period_confirmations', ['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'confirmed' => true]);
        $this->assertDatabaseCount('diary_assessment_results', 2);
        $this->assertSame('7.5', $enrollment->periodConvalidations()->sole()->score);
        $this->assertSame('0.0', $others['zero-origin']->periodConvalidations()->sole()->score);
        $this->assertModelExists($record);
    }

    public function test_origin_sheet_rejects_foreign_components_and_preserves_other_source_results(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'sheet-scope@ctjj.org');
        $key = $period->id.'_'.$component->id;
        $enrollment->periodConvalidations()->create(['academic_period_id' => $period->id, 'curriculum_component_id' => $component->id, 'score' => 6, 'source_school' => 'Escola preservada']);
        $data = ['source_school' => 'Outra escola', 'previous_source_school' => '', 'convalidated_at' => '2026-07-01', 'sheet_complete' => 1, 'rows' => [$key => ['score' => 9]]];
        $this->actingAs($manager)->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertSessionHasErrors('rows');
        $this->assertSame('6.0', $enrollment->periodConvalidations()->sole()->score);
        $this->get(route('enrollments.origin-sheet', ['enrollment' => $enrollment, 'new' => 1]))->assertOk()->assertViewHas('sourceSchool', '')->assertSee('Resultado cadastrado em outra ficha');
        $data['rows'] = [$period->id.'_999999' => ['score' => 8]];
        $this->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertSessionHasErrors('rows');
        $this->assertSame(1, $enrollment->periodConvalidations()->count());
        $otherSchool = School::query()->create(['name' => 'Outra unidade', 'active' => true]);
        $otherManager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $otherSchool->id, 'other-sheet@ctjj.org');
        $this->actingAs($otherManager)->get(route('enrollments.origin-sheet', $enrollment))->assertForbidden();
        $this->post(route('enrollments.origin-sheet.store', $enrollment), $data)->assertForbidden();
    }

    public function test_teacher_central_only_lists_and_issues_assigned_diaries(): void
    {
        [$teacher, $year, $class, $component] = $this->diaryScenario();
        $own = SchoolClassComponent::query()->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)->firstOrFail();
        $other = $own->replicate();
        $otherClass = $year->classes()->create(['name' => 'Outra turma', 'active' => true]);
        $other->school_class_id = $otherClass->id;
        $other->teacher_person_id = null;
        $other->save();
        $this->actingAs($teacher)->get(route('document-issuance.index'))->assertOk()
            ->assertSee('value="teacher-diary"', false)->assertSee('value="attendance-sheet"', false)
            ->assertDontSee('value="class-report-cards"', false);
        $this->getJson(route('document-issuance.targets', ['type' => 'teacher-diary']))->assertOk()
            ->assertJsonCount(1, 'targets')->assertJsonPath('targets.0.id', $own->id);
        $this->get(route('document-issuance.issue', ['type' => 'teacher-diary', 'target_id' => $own->id]))
            ->assertRedirect(route('teacher-diaries.pdf', ['schoolClass' => $class, 'component' => $component, 'notas' => 'numeros']));
        $this->get(route('document-issuance.issue', ['type' => 'teacher-diary', 'target_id' => $other->id]))->assertNotFound();
    }

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
            ->assertSee('Meus diários')
            ->assertSee('Meus horários');
    }

    public function test_diary_list_counts_lessons_for_contents_and_attendance_with_visible_inconsistencies(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-20'));
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $schedule = $class->schedules()->firstOrFail();
        $slot = $schedule->slots()->firstOrFail()->replicate();
        $slot->starts_at = '09:00';
        $slot->ends_at = '09:50';
        $slot->save();
        foreach (['2026-03-04', '2026-03-18', '2026-03-25'] as $date) {
            $year->days()->create(['date' => $date, 'type' => $date === '2026-03-25' ? CalendarDay::TYPE_HOLIDAY : CalendarDay::TYPE_SCHOOL_DAY, 'counts_as_school_day' => $date !== '2026-03-25']);
        }
        foreach ([['2026-03-11', 2], ['2026-03-18', 1]] as [$date, $lessons]) {
            DiaryAttendanceRecord::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => $date, 'lesson_count' => $lessons]);
        }
        foreach (['2026-03-04', '2026-03-11'] as $date) {
            DiaryContent::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => $date, 'content' => 'Conteúdo para as aulas do dia.']);
        }
        $response = $this->actingAs($teacher)->get(route('teacher-diaries.index'))
            ->assertOk()->assertSee('66,7%')->assertSee('50,0%')->assertSee('Inconsistências: 2')
            ->assertSee('Previsão pelo horário e calendário letivo')
            ->assertSee(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]).'#section-situacao', false);
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertSame(6, $summary['planned']);
        $this->assertSame(['registered' => 4, 'percentage' => 66.7], $summary['content']);
        $this->assertSame(['registered' => 3, 'percentage' => 50.0], $summary['attendance']);
        $this->assertSame(['attendance_without_content' => 1, 'content_without_attendance' => 1, 'missing_grades' => 0], $summary['issues']);
        $this->assertSame(2, substr_count($response->getContent(), '<progress '));
        $teacher->update(['locale' => 'it']);
        $this->actingAs($teacher->fresh())->get(route('teacher-diaries.index'))->assertOk()
            ->assertSee('Incongruenze: 2')->assertSee('lezioni', false)->assertDontSee('Ver pendências');
    }

    public function test_diary_list_handles_only_attendance_or_only_contents_in_a_period(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $record = DiaryAttendanceRecord::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => '2026-03-11', 'lesson_count' => 1]);
        $response = $this->actingAs($teacher)->get(route('teacher-diaries.index'))->assertOk()->assertSee('Inconsistências: 1');
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertSame(1, $summary['issues']['attendance_without_content']);
        $this->assertSame(0, $summary['content']['registered']);
        $record->delete();
        DiaryContent::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => '2026-03-11', 'content' => 'Conteúdo sem frequência ainda.']);
        $response = $this->get(route('teacher-diaries.index'))->assertOk()->assertSee('Inconsistências: 1');
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertSame(1, $summary['issues']['content_without_attendance']);
        $this->assertSame(0, $summary['attendance']['registered']);
        $this->assertSame(1, $summary['content']['registered']);
    }

    public function test_diary_list_uses_matrix_without_schedule_and_does_not_invent_unknown_percentages(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $class->schedules()->get()->each->delete();
        $component->update(['workload_hours' => 40]);
        DiaryAttendanceRecord::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => '2026-03-11', 'lesson_count' => 2]);
        DiaryContent::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => '2026-03-11', 'content' => 'Conteúdo registrado.']);
        $response = $this->actingAs($teacher)->get(route('teacher-diaries.index'))->assertOk()->assertSee('Previsão estimada pela carga horária da matriz')->assertSee('4,2%');
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertSame(48, $summary['planned']); // 40 horas de 60 minutos / aulas de 50 minutos.
        $this->assertSame(2, $summary['content']['registered']);
        $component->update(['workload_hours' => null, 'weekly_lessons' => null]);
        $response = $this->get(route('teacher-diaries.index'))->assertOk()->assertSee('Previsão de aulas não definida');
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertNull($summary['planned']);
        $this->assertNull($summary['attendance']['percentage']);
        $this->assertSame(2, $summary['attendance']['registered']);
        $this->assertStringContainsString('previsão indisponível', $response->getContent());
    }

    public function test_diary_list_keeps_actual_counts_above_forecast_without_overflowing_progress_bars(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        DiaryAttendanceRecord::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => '2026-03-12', 'lesson_count' => 3]);
        DiaryContent::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => '2026-03-12', 'content' => 'Aulas fora do horário atual.']);
        $response = $this->actingAs($teacher)->get(route('teacher-diaries.index'))->assertOk()->assertSee('300,0%')->assertSee('Acima do previsto');
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertSame(1, $summary['planned']);
        $this->assertSame(3, $summary['attendance']['registered']);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        foreach ($document->getElementsByTagName('progress') as $progress) {
            $this->assertSame('100', $progress->getAttribute('value'));
            $this->assertStringContainsString('300,0%', $progress->getAttribute('aria-valuetext'));
        }
    }

    public function test_diary_list_restricts_component_periods_and_counts_only_due_grades_with_origin_credit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29'));
        [$teacher, $year, $class, $component, $first, $enrollment] = $this->diaryScenario();
        $second = $year->periods()->create(['name' => '2º Bimestre', 'starts_at' => '2026-04-11', 'ends_at' => '2026-06-30', 'position' => 2]);
        $future = $year->periods()->create(['name' => 'Último período', 'starts_at' => '2026-11-01', 'ends_at' => '2026-12-18', 'position' => 3]);
        $component->update(['starts_period_id' => $second->id]);
        $year->days()->create(['date' => '2026-05-13', 'type' => CalendarDay::TYPE_SCHOOL_DAY, 'counts_as_school_day' => true]);
        $rule = SchoolAssessmentRule::query()->where('academic_period_id', $second->id)->orderBy('position')->firstOrFail();
        $assessment = DiaryAssessment::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $second->id, 'school_assessment_rule_id' => $rule->id, 'title' => 'Avaliação encerrada']);
        $futureRule = SchoolAssessmentRule::query()->where('academic_period_id', $future->id)->orderBy('position')->firstOrFail();
        DiaryAssessment::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $future->id, 'school_assessment_rule_id' => $futureRule->id, 'title' => 'Avaliação futura']);
        DiaryAttendanceRecord::query()->create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $first->id, 'class_date' => '2026-03-11', 'lesson_count' => 10]);
        $response = $this->actingAs($teacher)->get(route('teacher-diaries.index'))->assertOk();
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertSame(1, $summary['planned']);
        $this->assertSame(0, $summary['attendance']['registered']);
        $this->assertSame(1, $summary['issues']['missing_grades']);
        $enrollment->periodConvalidations()->create(['academic_period_id' => $second->id, 'curriculum_component_id' => $component->id, 'score' => 0, 'source_school' => 'Escola de origem']);
        $response = $this->get(route('teacher-diaries.index'))->assertOk();
        $summary = $response->viewData('diaries')->sole()['summary'];
        $this->assertSame(0, $summary['issue_count']);
        $this->assertSame(0, $assessment->results()->count());
        $this->assertSame([$second->id, $future->id], $summary['periods']->pluck('period.id')->all());
    }

    public function test_only_administrator_lists_diaries_from_closed_classes(): void
    {
        [$teacher, $year, $class, $component] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'manager-closed-class@ctjj.org');
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, $year->school_id, 'admin-closed-class@ctjj.org');
        $year->update(['closed_at' => '2026-12-31 18:00:00']);

        $this->actingAs($teacher)->get(route('teacher-diaries.index'))
            ->assertOk()->assertDontSee($class->name);
        $this->actingAs($teacher)->getJson(route('document-issuance.targets', ['type' => 'teacher-diary']))
            ->assertOk()->assertJsonCount(0, 'targets');
        $this->actingAs($manager)->get(route('teacher-diaries.index'))
            ->assertOk()->assertDontSee($class->name);
        $this->actingAs($manager)->getJson(route('document-issuance.targets', ['type' => 'teacher-diary']))
            ->assertOk()->assertJsonCount(0, 'targets');
        $this->actingAs($administrator)->get(route('teacher-diaries.index', [
            'academic_year' => $year->id,
            'period' => $year->periods->first()->id,
        ]))->assertOk()->assertSee($class->name)->assertSee($component->name);
        $this->actingAs($administrator)->getJson(route('document-issuance.targets', ['type' => 'teacher-diary']))
            ->assertOk()->assertJsonCount(1, 'targets');
    }

    public function test_administrator_can_filter_all_school_years_and_see_inactive_diaries(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, $year->school_id, 'admin-all-years@ctjj.org');
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'manager-all-years@ctjj.org');
        $year->school->update(['active' => false]);
        $year->update(['active' => false, 'approved_at' => null, 'reference_year' => 2022]);
        $class->update(['active' => false]);
        SchoolClassComponent::query()->where('school_class_id', $class->id)->update(['active' => false]);
        $other = School::query()->create(['name' => 'Outra escola arquivada', 'active' => false]);
        $otherYear = $other->academicYears()->create(['name' => 'Ano antigo', 'reference_year' => 2021, 'starts_at' => '2021-01-01', 'ends_at' => '2021-12-31', 'active' => false]);
        $this->actingAs($admin)->get(route('teacher-diaries.index'))
            ->assertOk()->assertSee($other->name)
            ->assertViewHas('years', fn ($years) => $years->contains('id', $year->id) && $years->contains('id', $otherYear->id));
        $this->get(route('teacher-diaries.index', ['year' => 2022, 'academic_year' => $year->id]))
            ->assertOk()->assertViewHas('years', fn ($years) => $years->count() === 1 && $years->first()->id === $year->id)
            ->assertSee($class->name)->assertSee($component->name)
            ->assertViewHas('stats', fn ($stats) => $stats['total'] > 0);
        $this->actingAs($manager)->get(route('teacher-diaries.index', ['academic_year' => $otherYear->id]))
            ->assertOk()->assertViewHas('years', fn ($years) => $years->isEmpty())->assertDontSee($other->name);
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

    public function test_component_filter_groups_identical_names_and_matches_all_corresponding_diaries(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'manager-filter@ctjj.org');
        $otherCourse = $component->course->replicate();
        $otherCourse->name = 'Outra matriz';
        $otherCourse->save();
        $otherComponent = $component->replicate();
        $otherComponent->academic_course_id = $otherCourse->id;
        $otherComponent->save();
        $otherClass = $class->replicate();
        $otherClass->name = 'Outra turma';
        $otherClass->save();
        $otherClass->courses()->attach($otherCourse);
        SchoolClassComponent::create(['school_class_id' => $otherClass->id, 'curriculum_component_id' => $otherComponent->id, 'teacher_person_id' => $teacher->person_id, 'active' => true]);
        foreach ([$component->id, $otherComponent->id] as $id) {
            $this->actingAs($manager)->get(route('teacher-diaries.index', ['academic_year' => $year->id, 'period' => $period->id, 'component' => $id]))
                ->assertOk()->assertViewHas('componentOptions', fn ($options) => $options->count() === 1)
                ->assertViewHas('groupedClasses', fn ($classes) => $classes->count() === 2);
        }
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
        $alert = DiaryAlert::query()->firstOrFail();

        $this->actingAs($teacher)->get(route('profile.edit'))->assertOk()
            ->assertSee('id="diary-alert-', false)
            ->assertSee('Abrir diário')
            ->assertSee('Já visto')
            ->assertSee(route('teacher-diaries.alerts.dismiss', $alert))
            ->assertSee($manager->person->full_name)
            ->assertSee('Confira os lançamentos de frequência desta semana.');
        $this->actingAs($manager)->get(route('profile.edit'))->assertOk()
            ->assertDontSee('id="diary-alert-', false);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alerta da gestão em Matemática')
            ->assertSee('Confira os lançamentos de frequência desta semana.');

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('Confira os lançamentos de frequência desta semana.')
            ->assertSee('Já visto');

        $this->actingAs($manager)
            ->get(route('teacher-diaries.index', ['academic_year' => $year->id, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('Alertas deste diário')
            ->assertSee('Confira os lançamentos de frequência desta semana.')
            ->assertSee('Não visto');

        $this->actingAs($teacher)
            ->patch(route('teacher-diaries.alerts.dismiss', $alert))
            ->assertRedirect()
            ->assertSessionHas('status', 'Alerta marcado como já visto.');

        $this->assertNotNull($alert->fresh()->dismissed_at);

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertDontSee('Confira os lançamentos de frequência desta semana.');

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Alerta da gestão em Matemática');

        $this->actingAs($teacher)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('id="diary-alert-', false);
        $this->delete(route('teacher-diaries.alerts.destroy', $alert))->assertForbidden();

        $this->actingAs($manager)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('Confira os lançamentos de frequência desta semana.')
            ->assertSee('Já visto')
            ->assertSee('Excluir alerta');
        $this->get(route('teacher-diaries.index', ['academic_year' => $year->id, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('Alertas deste diário')
            ->assertSee('Confira os lançamentos de frequência desta semana.')
            ->assertSee('Já visto');

        $otherSchool = School::query()->create($this->officialSchoolData(['name' => 'Escola sem acesso ao alerta']));
        $otherManager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $otherSchool->id, 'outra-gestao-alerta@ctjj.org');
        $this->actingAs($otherManager)->delete(route('teacher-diaries.alerts.destroy', $alert))->assertForbidden();

        $this->actingAs($manager)->delete(route('teacher-diaries.alerts.destroy', $alert))->assertRedirect();
        $this->assertDatabaseMissing('diary_alerts', ['id' => $alert->id]);
        $this->get(route('teacher-diaries.index', ['academic_year' => $year->id, 'period' => $period->id]))
            ->assertOk()
            ->assertDontSee('Confira os lançamentos de frequência desta semana.');
    }

    public function test_period_cannot_be_configured_without_an_assessment(): void
    {
        [, $year, , , $period] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-avaliacao-obrigatoria@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 0,
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertSessionHasErrors('assessment_count');

        $this->assertDatabaseHas('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 1,
            'weight' => 10,
        ]);
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
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Meu diário')
            ->assertSee('Minha vida escolar');

        $this->actingAs($student)
            ->get(route('student-diaries.show', [$enrollment, $component]))
            ->assertOk()
            ->assertSee('Conceitos lançados')
            ->assertDontSee('Notas lançadas');
    }

    public function test_diary_lists_students_enrolled_in_the_class_without_a_direct_course_link(): void
    {
        [$teacher, , $class, $component, $period, $enrollment] = $this->diaryScenario();
        $studentName = $enrollment->student->full_name;

        $enrollment->courses()->detach();

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [
                'schoolClass' => $class,
                'component' => $component,
                'period' => $period->id,
            ]))
            ->assertOk()
            ->assertSee($studentName);

        $student = User::query()->where('person_id', $enrollment->person_id)->firstOrFail();

        $this->actingAs($student)
            ->get(route('student-diaries.index'))
            ->assertOk()
            ->assertSee($component->name);

        $this->actingAs($student)
            ->get(route('student-diaries.show', [$enrollment, $component]))
            ->assertOk();
    }

    public function test_report_card_respects_student_concept_view_and_manager_pdf(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-boletim@ctjj.org');
        $student = User::query()->where('person_id', $enrollment->person_id)->firstOrFail();

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'assessment_names' => ['Avaliação 1'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ]);

        $assessment = DiaryAssessment::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 8.5]],
            ])
            ->assertRedirect();

        $this->actingAs($student)
            ->get(route('enrollments.report-card.show', $enrollment))
            ->assertOk()
            ->assertSee('Boletim escolar')
            ->assertSee('Conceito não definido')
            ->assertDontSee('8,5');

        $this->actingAs($manager)
            ->get(route('enrollments.report-card.show', ['enrollment' => $enrollment, 'notas' => 'numeros']))
            ->assertOk()
            ->assertSee('8,5');

        $this->actingAs($manager)
            ->get(route('enrollments.report-card.pdf', $enrollment))
            ->assertOk();
    }

    public function test_management_can_emit_class_report_cards_and_grade_mirror_as_pdf(): void
    {
        [, $year, $class] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-documentos-turma@ctjj.org');

        $this->actingAs($manager)
            ->get(route('classes.report-cards.pdf', [
                'class' => $class,
                'notas' => 'numeros',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($manager)
            ->get(route('classes.grade-mirror.pdf', [
                'class' => $class,
                'notas' => 'numeros',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'class-report-cards',
            'school_id' => $year->school_id,
            'issued_by_user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('issued_documents', [
            'type' => 'class-grade-mirror',
            'school_id' => $year->school_id,
            'issued_by_user_id' => $manager->id,
        ]);
    }

    public function test_individual_and_class_report_cards_use_director_and_secretary_signatures(): void
    {
        [, $year, , , , $enrollment] = $this->diaryScenario();
        $names = [
            PersonSchoolRole::POSITION_DIRECTOR => 'Helena Albuquerque',
            PersonSchoolRole::POSITION_SECRETARY => 'Beatriz Ferreira',
            PersonSchoolRole::POSITION_COORDINATOR => 'Carolina Ribeiro',
        ];
        foreach ($names as $position => $name) {
            $person = Person::query()->create(['full_name' => $name, 'active' => true]);
            $person->schoolRoles()->create([
                'school_id' => $year->school_id,
                'role' => PersonSchoolRole::ROLE_MANAGER,
                'position' => $position,
                'active' => true,
            ]);
        }

        $report = app(StudentReportCardBuilder::class)->build($enrollment);
        $document = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'TESTE']);
        $document->setRelation('issuedBy', null);
        $data = [
            'scoreView' => 'numeros',
            'issuedDocument' => $document,
            'verificationUrl' => route('documents.verify', 'TESTE'),
            'letterhead' => PdfLetterhead::make($year->school),
        ];

        foreach ([
            ['reports.student-report-card', ['report' => $report]],
            ['reports.class-report-cards', ['reports' => collect([$report])]],
        ] as [$template, $reportData]) {
            $html = View::make($template, $data + $reportData)->render();

            $this->assertMatchesRegularExpression('/<td class="label">Estudante<\/td>\s*<td>[^<]+<\/td>\s*<td class="label">Nascimento<\/td>/u', $html);
            $this->assertStringContainsString('Helena Albuquerque', $html);
            $this->assertStringContainsString('Beatriz Ferreira', $html);
            $this->assertStringContainsString('Direção escolar', $html);
            $this->assertStringContainsString('Secretaria escolar', $html);
            $this->assertStringNotContainsString('Carolina Ribeiro', $html);
            $this->assertStringNotContainsString('Coordenação escolar', $html);
        }
    }

    public function test_confirmed_year_criteria_recalculate_saved_results_without_reopening_the_year(): void
    {
        [, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $course = $class->courses()->firstOrFail();
        foreach ([2, 3, 4] as $number) {
            $year->periods()->create([
                'name' => "{$number}º Bimestre", 'position' => $number,
                'starts_at' => '2026-04-11', 'ends_at' => '2026-12-18',
            ]);
        }
        $components = collect([$component]);
        foreach (['História', 'Língua Inglesa'] as $name) {
            $components->push($course->components()->create(['name' => $name, 'workload_hours' => 80, 'active' => true]));
        }
        $scores = [[5, 5, 5, 6], [5.5, 5.5, 5.5, 6], [5, 6, 6, 6]];
        foreach ($components as $index => $item) {
            foreach ($year->periods()->orderBy('position')->get() as $position => $evaluationPeriod) {
                $enrollment->periodConvalidations()->create([
                    'academic_period_id' => $evaluationPeriod->id, 'curriculum_component_id' => $item->id,
                    'score' => $scores[$index][$position], 'source_school' => 'Escola anterior',
                    'attendance_lessons' => 20, 'attendance_absences' => 1,
                ]);
            }
        }
        $year->update(['closed_at' => now(), 'passing_points' => 24]);
        $enrollment->update(['final_result_status' => StudentEnrollment::FINAL_RETAINED_POINTS, 'final_result_calculated_at' => now()]);
        $closedAt = $year->closed_at;
        $this->artisan('academic-years:correct-criteria', [
            'enrollment' => $enrollment->id, '--passing-points' => '22.5', '--dependency-limit' => '2',
        ])->assertSuccessful();
        $this->assertSame(24.0, (float) $year->fresh()->passing_points);

        $this->artisan('academic-years:correct-criteria', [
            'enrollment' => $enrollment->id, '--passing-points' => '22.5', '--dependency-limit' => '2', '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame(22.5, (float) $year->fresh()->passing_points);
        $this->assertTrue($closedAt->equalTo($year->fresh()->closed_at));
        $this->assertSame(2, $year->school->fresh()->dependencyComponentLimitForDate($year->ends_at));
        $this->assertSame(StudentEnrollment::FINAL_DEPENDENCY, $enrollment->fresh()->final_result_status);
        $this->assertCount(1, $enrollment->fresh()->final_result_details['failed_components']);
        $this->assertSame(12, $enrollment->periodConvalidations()->count());
    }

    public function test_imported_period_grade_correction_updates_only_target_result_and_recalculates_closed_enrollment(): void
    {
        [, $year, $class, $component, $firstPeriod, $enrollment] = $this->diaryScenario();
        $component->update(['name' => 'Geografia']);
        $year->school->update(['dependency_component_limit' => 2]);
        foreach ([['2026-04-11', '2026-06-30'], ['2026-07-01', '2026-09-30'], ['2026-10-01', '2026-12-18']] as $index => [$start, $end]) {
            $year->periods()->create(['name' => ($index + 2).'º Bimestre', 'position' => $index + 2, 'starts_at' => $start, 'ends_at' => $end]);
        }
        $year->update(['passing_points' => 22.5, 'closed_at' => now()]);
        $closedAt = $year->closed_at;
        $scores = [6, 5, 6, 4];
        $target = null;
        foreach ($year->periods()->orderBy('position')->get() as $index => $period) {
            $assessment = DiaryAssessment::query()->create([
                'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id, 'is_recovery' => false, 'title' => 'Nota importada',
                'weight' => 10, 'maximum_score' => 10, 'legacy_source' => 'fixture', 'legacy_id' => 100 + $index,
            ]);
            $result = $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => $scores[$index]]);
            if ($index === 3) {
                $target = $result;
            }
        }
        $attendance = DiaryAttendanceRecord::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $firstPeriod->id, 'class_date' => '2026-03-10', 'lesson_count' => 1,
        ]);
        $attendance->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => DiaryAttendanceRecord::STATUS_PRESENT, 'attended_lessons' => 1]);
        $otherPerson = Person::query()->create(['full_name' => 'Outro estudante']);
        $other = $class->enrollments()->create(['person_id' => $otherPerson->id, 'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR]);
        $otherResult = $target->assessment->results()->create(['student_enrollment_id' => $other->id, 'score' => 4]);
        $enrollment->update(['final_result_status' => StudentEnrollment::FINAL_DEPENDENCY, 'final_result_calculated_at' => now()]);

        $arguments = ['enrollment' => $enrollment->id, 'component' => 'Geografia', 'period' => 4, 'score' => 6, '--expected' => '4'];
        $this->artisan('diaries:correct-imported-grade', $arguments)->assertSuccessful();
        $this->assertSame(4.0, (float) $target->fresh()->score);
        $this->artisan('diaries:correct-imported-grade', $arguments + ['--apply' => true])->assertSuccessful();
        $this->assertSame(6.0, (float) $target->fresh()->score);
        $this->assertSame(4.0, (float) $otherResult->fresh()->score);
        $this->assertSame(StudentEnrollment::FINAL_APPROVED, $enrollment->fresh()->final_result_status);
        $this->assertTrue($closedAt->equalTo($year->fresh()->closed_at));
        $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
        $this->assertSame([6.0, 5.0, 6.0, 6.0], $report['annualComponents']->first()['periods']->map(fn ($item) => (float) $item['average']['value'])->all());
        $this->assertSame(23.0, (float) $report['annualComponents']->first()['points']);
        $audit = AuditLog::query()->where('auditable_type', DiaryAssessmentResult::class)->where('auditable_id', $target->id)->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(4.0, (float) $audit->old_values['score']);
        $this->assertSame(6.0, (float) $audit->new_values['score']);
        $this->artisan('diaries:correct-imported-grade', $arguments + ['--apply' => true])->assertFailed();
        $this->assertSame(6.0, (float) $target->fresh()->score);
    }

    public function test_individual_record_keeps_signatures_above_footer_and_hours_follow_relative_attendance(): void
    {
        [, $year, $class, $component, , $enrollment] = $this->diaryScenario();
        $enrollment->student->update([
            'full_name' => 'Estudante com Identificação Completa de Demonstração',
            'legacy_code' => '12345', 'cpf' => '12345678901', 'student_inep' => '177607249507',
            'birth_date' => '2013-07-25', 'birth_city' => 'Barra do Garças', 'birth_state' => 'MT',
            'nationality' => 'Brasileira', 'phone' => '(66) 99999-9999', 'nis' => '12345678901',
            'receives_federal_aid' => true, 'personal_email' => 'estudante@example.org',
            'institutional_email' => 'estudante.demonstracao@ctjj.org',
            'mother_name' => 'Nome Completo da Mãe do Estudante', 'father_name' => 'Nome Completo do Pai do Estudante',
            'address' => 'Rua C9, Qd A-52, Lote 29 Residencial', 'district' => 'Jardim dos Ipês',
            'city' => 'Barra do Garças', 'state' => 'MT', 'postal_code' => '78696-512',
        ]);
        $course = $class->courses()->firstOrFail();
        foreach ([2, 3, 4] as $number) {
            $year->periods()->create(['name' => "{$number}º Bimestre", 'position' => $number, 'starts_at' => '2026-04-11', 'ends_at' => '2026-12-18']);
        }
        foreach (['Ciências', 'Geografia', 'História', 'Ensino Religioso', 'Arte', 'Educação Física', 'Língua Inglesa', 'Língua Portuguesa'] as $name) {
            $course->components()->create(['name' => $name, 'workload_hours' => 67, 'active' => true]);
        }
        foreach ([PersonSchoolRole::POSITION_DIRECTOR => 'Thelma Vieira Arrais', PersonSchoolRole::POSITION_SECRETARY => 'Erika Thayellen de Sousa Miranda'] as $position => $name) {
            $person = Person::query()->create(['full_name' => $name]);
            $person->schoolRoles()->create(['school_id' => $year->school_id, 'role' => PersonSchoolRole::ROLE_MANAGER, 'position' => $position, 'active' => true]);
        }
        $year->school->concepts()->createMany([
            ['name' => 'Bom', 'abbreviation' => 'B', 'minimum_score' => 8, 'maximum_score' => 10, 'effective_from' => '2025-01-01'],
            ['name' => 'Suficiente', 'abbreviation' => 'S', 'minimum_score' => 6, 'maximum_score' => 8, 'effective_from' => '2025-01-01'],
            ['name' => 'Insuficiente', 'abbreviation' => 'I', 'minimum_score' => 3.5, 'maximum_score' => 6, 'effective_from' => '2025-01-01'],
            ['name' => 'Insuficiente Grave', 'abbreviation' => 'IG', 'minimum_score' => 2, 'maximum_score' => 3.5, 'effective_from' => '2025-01-01'],
            ['name' => 'Insuficiente Gravíssimo', 'abbreviation' => 'IGV', 'minimum_score' => 0, 'maximum_score' => 2, 'effective_from' => '2025-01-01'],
        ]);
        $year->update(['closed_at' => now()]);
        $enrollment->update([
            'final_result_status' => StudentEnrollment::FINAL_DEPENDENCY, 'final_result_calculated_at' => now(),
            'final_result_details' => ['reason' => 'Estudante ficou abaixo da pontuação mínima dentro do limite de dependência da escola.'],
        ]);
        $report = app(StudentReportCardBuilder::class)->build($enrollment);
        $report['annualComponents'] = $report['annualComponents']->map(function ($summary) {
            $summary['attendance'] = app(AttendanceSummaryCalculator::class)->fromTotals(8, 8, 0);

            return $summary;
        });
        $document = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'TESTE']);
        $document->setRelation('issuedBy', null);
        $data = ['report' => $report, 'scoreView' => 'numeros', 'issuedDocument' => $document,
            'verificationUrl' => route('documents.verify', 'TESTE'), 'letterhead' => PdfLetterhead::make($year->school)];
        $html = View::make('reports.student-individual-record', $data)->render();
        $this->assertStringContainsString('Encerrada · Regular', $html);
        foreach (['123.456.789-01', '177607249507', '12345', '12345678901', '25/07/2013', 'estudante@example.org', 'estudante.demonstracao@ctjj.org', '78696-512', 'Nome Completo da Mãe do Estudante', 'Nome Completo do Pai do Estudante'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
        $this->assertMatchesRegularExpression('/67 h<\/td>\s*<td class="center">67 h<\/td>/', $html);
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $signatureBottom = 0;
        $signatureLineTop = INF;
        $issueDateBottom = 0;
        $footerTop = INF;
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$signatureBottom, &$signatureLineTop, &$issueDateBottom, &$footerTop): void {
            $node = $frame->get_node();
            if (! $node instanceof \DOMElement) {
                return;
            }
            $box = $frame->get_border_box();
            if ($node->getAttribute('class') === 'signature-role') {
                $signatureBottom = max($signatureBottom, $box[1] + $box[3]);
            }
            if ($node->getAttribute('class') === 'signature-line') {
                $signatureLineTop = min($signatureLineTop, $box[1]);
            }
            if ($node->getAttribute('class') === 'issue-place-date') {
                $issueDateBottom = max($issueDateBottom, $box[1] + $box[3]);
            }
            if ($node->getAttribute('class') === 'document-footer') {
                $footerTop = min($footerTop, $box[1]);
            }
        }]]);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertGreaterThan(0, $signatureBottom);
        $this->assertNotSame(INF, $footerTop);
        $this->assertGreaterThanOrEqual($signatureBottom + 6, $footerTop, 'As assinaturas devem terminar antes do rodapé, com espaço livre.');
        $this->assertGreaterThanOrEqual($issueDateBottom + 30, $signatureLineTop, 'Deve haver ao menos 40px de espaço livre para assinar.');
    }

    public function test_report_cards_with_full_curriculum_keep_each_student_on_one_page(): void
    {
        [, $year, $class, , , $enrollment] = $this->diaryScenario();
        $course = $class->courses()->firstOrFail();
        $enrollment->student->update(['full_name' => 'Maria Eduarda de Oliveira e Albuquerque Ferreira dos Santos']);
        foreach ([
            ['2º Bimestre', '2026-04-11', '2026-06-30'],
            ['3º Bimestre', '2026-07-01', '2026-09-30'],
            ['4º Bimestre', '2026-10-01', '2026-12-18'],
        ] as $index => [$name, $startsAt, $endsAt]) {
            $year->periods()->create([
                'name' => $name, 'starts_at' => $startsAt,
                'ends_at' => $endsAt, 'position' => $index + 2,
            ]);
        }
        foreach ([
            'Língua Portuguesa', 'Língua Inglesa', 'Arte', 'Educação Física',
            'Física', 'Química', 'Biologia', 'História', 'Geografia',
            'Filosofia', 'Sociologia', 'Projeto de Vida',
            'Tecnologia e Inovação', 'Educação Financeira', 'Empreendedorismo',
            'Produção Textual', 'Robótica Educacional', 'Educação Ambiental',
            'Estudos Orientados', 'Práticas de Laboratório',
        ] as $name) {
            $course->components()->create(['name' => $name, 'workload_hours' => 80, 'active' => true]);
        }
        $itinerary = KnowledgeArea::query()->firstOrCreate(
            ['name' => 'Itinerários Formativos'],
            ['formation' => CurriculumCatalog::FORMATION_ITINERARY, 'active' => true],
        );
        foreach (['Laboratório de Leitura', 'Pesquisa Aplicada', 'Iniciação Científica'] as $name) {
            $course->components()->create(['name' => $name, 'knowledge_area_id' => $itinerary->id, 'workload_hours' => 80, 'active' => true]);
        }
        $year->school->concepts()->createMany([
            ['name' => 'Excelente', 'abbreviation' => 'E', 'minimum_score' => 8, 'maximum_score' => 10, 'minimum_inclusive' => true, 'maximum_inclusive' => true, 'sort_order' => 1, 'effective_from' => '2026-01-01'],
            ['name' => 'Suficiente', 'abbreviation' => 'S', 'minimum_score' => 6, 'maximum_score' => 8, 'minimum_inclusive' => true, 'maximum_inclusive' => false, 'sort_order' => 2, 'effective_from' => '2026-01-01'],
            ['name' => 'Insuficiente', 'abbreviation' => 'I', 'minimum_score' => 0, 'maximum_score' => 6, 'minimum_inclusive' => true, 'maximum_inclusive' => false, 'sort_order' => 3, 'effective_from' => '2026-01-01'],
        ]);
        foreach ([PersonSchoolRole::POSITION_DIRECTOR => 'Maria Aparecida dos Santos', PersonSchoolRole::POSITION_SECRETARY => 'Ana Cláudia Ferreira de Souza'] as $position => $name) {
            $person = Person::query()->create(['full_name' => $name, 'active' => true]);
            $person->schoolRoles()->create([
                'school_id' => $year->school_id, 'role' => PersonSchoolRole::ROLE_MANAGER,
                'position' => $position, 'active' => true,
            ]);
        }

        $report = app(StudentReportCardBuilder::class)->build($enrollment);
        $document = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'TESTE']);
        $document->setRelation('issuedBy', null);
        $data = [
            'scoreView' => 'numeros', 'issuedDocument' => $document,
            'verificationUrl' => route('documents.verify', 'TESTE'),
            'letterhead' => PdfLetterhead::make($year->school),
        ];

        $single = Pdf::loadView('reports.student-report-card', $data + ['report' => $report])->setPaper('a4', 'portrait');
        $this->assertStringStartsWith('%PDF-', $single->output());

        $secondReport = $report;
        $secondReport['student'] = clone $report['student'];
        $secondReport['student']->full_name = 'Segundo Estudante da Turma';
        $classPdf = Pdf::loadView('reports.class-report-cards', $data + ['reports' => collect([$report, $secondReport])])->setPaper('a4', 'portrait');
        $this->assertStringStartsWith('%PDF-', $classPdf->output());
        $this->assertSame([1, 2], [
            $single->getDomPDF()->getCanvas()->get_page_count(),
            $classPdf->getDomPDF()->getCanvas()->get_page_count(),
        ]);

        $course->components()->whereIn('name', [
            'Empreendedorismo', 'Estudos Orientados', 'Educação Financeira',
            'Robótica Educacional', 'Educação Ambiental', 'Produção Textual', 'Práticas de Laboratório',
        ])->delete();
        $year->school->concepts()->createMany([
            ['name' => 'Insuficiente grave', 'abbreviation' => 'IG', 'minimum_score' => 0, 'maximum_score' => 2, 'minimum_inclusive' => true, 'maximum_inclusive' => false, 'sort_order' => 4, 'effective_from' => '2026-01-01'],
            ['name' => 'Ótimo', 'abbreviation' => 'O', 'minimum_score' => 8, 'maximum_score' => 9, 'minimum_inclusive' => true, 'maximum_inclusive' => false, 'sort_order' => 5, 'effective_from' => '2026-01-01'],
        ]);
        $spaciousReport = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
        $this->assertCount(17, $spaciousReport['annualComponents']);
        $spaciousHtml = View::make('reports.class-report-cards', $data + ['reports' => collect([$spaciousReport])])->render();
        $this->assertStringContainsString('class="signatures spacious"', $spaciousHtml);
        $spaciousPdf = Pdf::loadHTML($spaciousHtml)->setPaper('a4', 'portrait');
        $this->assertStringStartsWith('%PDF-', $spaciousPdf->output());
        $this->assertSame(1, $spaciousPdf->getDomPDF()->getCanvas()->get_page_count());
        $spaciousSingle = Pdf::loadView('reports.student-report-card', $data + ['report' => $spaciousReport])->setPaper('a4', 'portrait');
        $this->assertStringStartsWith('%PDF-', $spaciousSingle->output());
        $this->assertSame(1, $spaciousSingle->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_associated_matrix_repairs_current_enrollments_without_changing_history(): void
    {
        [, $year, $class, $component, , $enrollment] = $this->diaryScenario();
        $course = $year->courses()->create([
            'name' => 'Técnico em Móveis', 'stage' => AcademicCourse::STAGE_TECHNICAL,
            'status' => 'iniciado', 'class_hour_minutes' => 50, 'active' => true,
        ]);
        $technical = $course->components()->create([
            'name' => 'Equipamentos e Tecnologia da Madeira', 'workload_hours' => 80, 'active' => true,
        ]);
        $class->courses()->attach($course);
        $enrollment->update(['status' => StudentEnrollment::STATUS_TRANSFERRED]);
        $migration = require database_path('migrations/2026_09_09_000002_link_current_enrollments_to_class_courses.php');
        $migration->up();
        SchoolClassEnrollmentCourses::synchronize($class);
        $this->assertFalse($enrollment->courses()->whereKey($course->id)->exists());
        $enrollment->update(['status' => StudentEnrollment::STATUS_ENROLLED]);
        $migration->up();
        $migration->up();
        $this->assertSame(2, $enrollment->courses()->count());
        $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
        $this->assertContains($technical->id, $report['annualComponents']->pluck('component.id')->all());
        $this->assertContains($component->id, $report['annualComponents']->pluck('component.id')->all());
        $enrollment->courses()->detach($course);
        SchoolClassEnrollmentCourses::synchronize($class);
        $this->assertSame(2, $enrollment->courses()->count());
        $enrollment->courses()->detach($course);
        $year->update(['closed_at' => now()]);
        $migration->up();
        SchoolClassEnrollmentCourses::synchronize($class->fresh());
        $this->assertSame(1, $enrollment->courses()->count());
    }

    public function test_individual_record_report_includes_calculated_final_result(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-ficha-final@ctjj.org');

        $enrollment->update([
            'final_result_status' => StudentEnrollment::FINAL_APPROVED,
            'final_result_details' => ['reason' => 'Aprovado por pontos e frequencia.'],
            'final_result_calculated_at' => now(),
            'final_result_calculated_by_person_id' => $manager->person_id,
        ]);

        $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());

        $this->assertSame('Aprovado', $report['finalResult']['label']);
        $this->assertSame('Aprovado por pontos e frequencia.', $report['finalResult']['details']['reason']);
        $this->assertSame($manager->person_id, $report['finalResult']['calculated_by']->id);
    }

    public function test_student_life_individual_record_button_uses_latest_enrollment_even_when_transferred(): void
    {
        [, $year, $class, , , $oldEnrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-ficha-recente@ctjj.org');
        $newClass = $year->classes()->create(['name' => '1º Ano B', 'active' => true]);
        $newClass->courses()->attach($class->courses->pluck('id'));
        $latestEnrollment = $newClass->enrollments()->create([
            'person_id' => $oldEnrollment->person_id,
            'enrolled_at' => '2026-04-01',
            'transferred_at' => '2026-04-15',
            'status' => StudentEnrollment::STATUS_TRANSFERRED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $latestEnrollment->courses()->attach($class->courses->pluck('id'));

        $response = $this->actingAs($manager)->get(route('people.student-map.show', $oldEnrollment->person_id))->assertOk();
        preg_match('/<div class="sge-life-actions">(.*?)<\/div>/s', $response->getContent(), $matches);

        $this->assertStringContainsString(route('enrollments.individual-record.pdf', $latestEnrollment), $matches[1] ?? '');
        $this->assertStringNotContainsString(route('enrollments.individual-record.pdf', $oldEnrollment), $matches[1] ?? '');

        $oldEnrollment->update(['status' => StudentEnrollment::STATUS_TRANSFERRED, 'transferred_at' => '2026-03-15']);
        $response = $this->get(route('people.student-map.show', $oldEnrollment->person_id))->assertOk();
        preg_match('/<div class="sge-life-actions">(.*?)<\/div>/s', $response->getContent(), $matches);
        $this->assertStringContainsString(route('enrollments.individual-record.pdf', $latestEnrollment), $matches[1] ?? '');
    }

    public function test_retroactive_transfer_limits_absences_in_attendance_documents_and_student_views(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-ficha-transferida@ctjj.org');
        $enrollment->update(['enrolled_at' => '2026-02-10']);

        foreach (['2026-02-09' => 0, '2026-02-10' => 0, '2026-03-12' => 1, '2026-03-13' => 0] as $classDate => $attendedLessons) {
            $record = DiaryAttendanceRecord::query()->create([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
                'teacher_person_id' => $teacher->person_id,
                'class_date' => $classDate,
                'lesson_count' => 2,
            ]);
            $record->entries()->create([
                'student_enrollment_id' => $enrollment->id,
                'status' => $attendedLessons ? DiaryAttendanceRecord::STATUS_PARTIAL : DiaryAttendanceRecord::STATUS_ABSENT,
                'attended_lessons' => $attendedLessons,
            ]);
        }

        $enrollment->update(['status' => StudentEnrollment::STATUS_TRANSFERRED, 'transferred_at' => '2026-03-12']);

        $validAssessment = DiaryAssessment::query()->create([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'title' => 'Avaliação antes da transferência',
            'assessment_date' => '2026-03-10',
        ]);
        $validAssessment->results()->create([
            'student_enrollment_id' => $enrollment->id,
            'score' => 0,
        ]);

        $thirdPeriod = $year->periods()->create([
            'name' => '3º Bimestre',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-09-30',
            'position' => 3,
        ]);
        $laterAssessment = DiaryAssessment::query()->create([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $thirdPeriod->id,
            'title' => 'Avaliação após transferência',
            'assessment_date' => '2026-08-10',
        ]);
        $laterAssessment->results()->create([
            'student_enrollment_id' => $enrollment->id,
            'score' => 0,
        ]);
        $undatedLaterAssessment = DiaryAssessment::query()->create([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $thirdPeriod->id,
            'title' => 'Avaliação sem data após transferência',
        ]);
        $undatedLaterAssessment->results()->create([
            'student_enrollment_id' => $enrollment->id,
            'score' => 0,
        ]);

        $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
        $this->assertSame(4, $report['annualAttendance']['lessons']);
        $this->assertSame(3, $report['annualAttendance']['absent']);
        $this->assertSame(25.0, $report['annualAttendance']['percentage']);
        $this->assertSame(0.0, $report['periodReports']->firstWhere('period.id', $period->id)['components']->first()['average']['value']);
        $this->assertNull($report['periodReports']->firstWhere('period.id', $thirdPeriod->id)['components']->first()['average']['value']);

        $pdfReport = null;
        View::composer('reports.student-individual-record', function ($view) use (&$pdfReport): void {
            $pdfReport = $view->getData()['report'];
        });
        $this->actingAs($manager)->get(route('enrollments.individual-record.pdf', $enrollment))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(3, $pdfReport['annualAttendance']['absent']);
        $this->assertNull($pdfReport['periodReports']->firstWhere('period.id', $thirdPeriod->id)['components']->first()['average']['value']);

        $certificate = app(StudentAttendanceCertificateBuilder::class)->build($enrollment->fresh(), $year->starts_at, $year->ends_at);
        $this->assertSame(4, $certificate['attendance']['lessons']);
        $this->assertSame(3, $certificate['attendance']['absent']);

        $annualRows = null;
        View::composer('reports.academic-year-attendance', function ($view) use (&$annualRows): void {
            $annualRows = $view->getData()['rows'];
        });
        $this->get(route('academic-years.attendance-report.pdf', $year))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(4, $annualRows->first()['summary']['lessons']);
        $this->assertSame(3, $annualRows->first()['summary']['absent']);

        $diaryData = null;
        View::composer('reports.teacher-diary', function ($view) use (&$diaryData): void {
            $diaryData = $view->getData();
        });
        $this->get(route('teacher-diaries.pdf', [$class, $component, 'period' => $period->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $diaryHtml = view('reports.teacher-diary', $diaryData)->render();
        preg_match('/<td class="student-name">.*?<\/tr>/s', $diaryHtml, $diaryRow);
        $this->assertSame(3, substr_count($diaryRow[0] ?? '', '>F</td>'));
        $this->assertSame(4, substr_count($diaryRow[0] ?? '', '>-</td>'));
        $this->assertMatchesRegularExpression('/<td class="center attendance-total">1<\/td>\s*<td class="center attendance-total">3<\/td>/', $diaryRow[0] ?? '');

        $this->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()->assertViewHas('attendanceSummary', fn ($summary): bool => collect($summary)->sum('absent') === 3);

        $this->get(route('people.student-map.show', $enrollment->person_id))
            ->assertOk()
            ->assertViewHas('attendanceSummary', fn ($summary): bool => $summary->count() === 1
                && $summary->first()['total'] === 4
                && $summary->first()['absent'] === 3);

        $student = User::query()->where('person_id', $enrollment->person_id)->firstOrFail();
        $this->actingAs($student)->get(route('student-diaries.show', [$enrollment, $component]))
            ->assertOk()->assertViewHas('attendance', fn ($attendance): bool => $attendance->count() === 2);
        $this->assertDatabaseCount('diary_attendance_entries', 4);
    }

    public function test_report_card_and_individual_record_include_parallel_technical_itinerary_for_the_regular_year(): void
    {
        [, $regularYear, , , , $regularEnrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $regularYear->school_id, 'gestao-itinerario-documentos@ctjj.org');
        $technicalYear = AcademicYear::query()->create([
            'school_id' => $regularYear->school_id,
            'name' => 'Itinerário Técnico 2025-2027',
            'reference_year' => 2025,
            'starts_at' => '2025-01-20',
            'ends_at' => '2027-12-18',
            'approved_at' => '2024-12-10',
            'class_hour_minutes' => 50,
            'minimum_school_days' => 200,
            'active' => true,
        ]);
        $technicalPeriod = $technicalYear->periods()->create([
            'name' => 'Módulo II',
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-11-30',
            'position' => 2,
        ]);
        $technicalCourse = $technicalYear->courses()->create([
            'name' => 'Técnico em Móveis',
            'stage' => AcademicCourse::STAGE_TECHNICAL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $technicalComponent = $technicalCourse->components()->create([
            'name' => 'Desenho Técnico de Móveis',
            'starts_period_id' => $technicalPeriod->id,
            'ends_period_id' => $technicalPeriod->id,
            'workload_hours' => 80,
            'active' => true,
        ]);
        $technicalClass = $technicalYear->classes()->create([
            'name' => 'Técnico em Móveis',
            'starts_period_id' => $technicalPeriod->id,
            'ends_period_id' => $technicalPeriod->id,
            'active' => true,
        ]);
        $technicalClass->courses()->attach($technicalCourse);
        $technicalEnrollment = $technicalClass->enrollments()->create([
            'person_id' => $regularEnrollment->person_id,
            'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $technicalEnrollment->courses()->attach($technicalCourse);

        $report = app(StudentReportCardBuilder::class)->build($regularEnrollment->fresh());

        $this->assertContains($technicalCourse->id, $report['courses']->pluck('id')->all());
        $this->assertContains($technicalComponent->id, $report['annualComponents']->pluck('component.id')->all());
        $this->assertContains($technicalPeriod->id, $report['periods']->pluck('id')->all());
        $mirrorData = null;
        View::composer('reports.class-grade-mirror', function ($view) use (&$mirrorData): void {
            $mirrorData = $view->getData();
        });
        $this->actingAs($manager)
            ->get(route('classes.grade-mirror.pdf', ['class' => $regularEnrollment->school_class_id, 'notas' => 'numeros']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertContains($technicalPeriod->id, $mirrorData['periods']->pluck('id')->all());
        $this->assertContains($technicalComponent->id, $mirrorData['components']->pluck('id')->all());

        $this->actingAs($manager)
            ->get(route('enrollments.report-card.pdf', $regularEnrollment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($manager)
            ->get(route('enrollments.individual-record.pdf', $regularEnrollment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_individual_record_orders_periods_chronologically_when_positions_are_inconsistent(): void
    {
        [, $year, , , $period, $enrollment] = $this->diaryScenario();
        $period->update([
            'name' => 'II Bimestre',
            'starts_at' => '2026-04-20',
            'ends_at' => '2026-07-03',
            'position' => 4,
        ]);
        $year->periods()->create([
            'name' => 'III Bimestre',
            'starts_at' => '2026-07-20',
            'ends_at' => '2026-10-02',
            'position' => 2,
        ]);

        $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());

        $this->assertSame(
            ['II Bimestre', 'III Bimestre'],
            $report['periodReports']->pluck('period.name')->all(),
        );
    }

    public function test_management_can_emit_attendance_certificate_pdf(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-atestado-frequencia@ctjj.org');
        $record = DiaryAttendanceRecord::query()->create([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'teacher_person_id' => $teacher->person_id,
            'updated_by_person_id' => $teacher->person_id,
            'class_date' => '2026-03-11',
            'lesson_count' => 2,
        ]);
        $record->entries()->create([
            'student_enrollment_id' => $enrollment->id,
            'status' => DiaryAttendanceRecord::STATUS_PARTIAL,
            'attended_lessons' => 1,
            'lesson_presence' => [1 => true, 2 => false],
        ]);

        $this->actingAs($manager)
            ->get(route('enrollments.attendance-certificate.pdf', $enrollment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'student-attendance-certificate',
            'person_id' => $enrollment->person_id,
            'school_id' => $year->school_id,
        ]);
    }

    public function test_attendance_certificate_supports_month_period_and_all_enrollment_matrices(): void
    {
        [$teacher, $year, $class, $component, $firstPeriod, $enrollment] = $this->diaryScenario();
        $secondPeriod = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'name' => '2º Bimestre',
            'starts_at' => '2026-04-13',
            'ends_at' => '2026-07-08',
            'position' => 2,
        ]);
        $technicalCourse = $year->courses()->create([
            'name' => 'Técnico em Móveis',
            'stage' => AcademicCourse::STAGE_TECHNICAL,
            'status' => 'iniciado',
            'class_hour_minutes' => 50,
            'active' => true,
        ]);
        $technicalComponent = $technicalCourse->components()->create([
            'name' => 'Desenho Técnico',
            'weekly_lessons' => 1,
            'active' => true,
        ]);
        $class->courses()->attach($technicalCourse);
        $enrollment->courses()->attach($technicalCourse);

        $records = [
            [$component, $firstPeriod, '2026-03-11', 2, 1],
            [$technicalComponent, $firstPeriod, '2026-04-05', 1, 1],
            [$component, $secondPeriod, '2026-05-06', 3, 3],
        ];

        foreach ($records as [$recordComponent, $period, $date, $lessons, $attended]) {
            $record = DiaryAttendanceRecord::query()->create([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $recordComponent->id,
                'academic_period_id' => $period->id,
                'teacher_person_id' => $teacher->person_id,
                'updated_by_person_id' => $teacher->person_id,
                'class_date' => $date,
                'lesson_count' => $lessons,
            ]);
            $record->entries()->create([
                'student_enrollment_id' => $enrollment->id,
                'status' => $attended === $lessons ? DiaryAttendanceRecord::STATUS_PRESENT : DiaryAttendanceRecord::STATUS_PARTIAL,
                'attended_lessons' => $attended,
            ]);
        }

        DiaryAttendanceRecord::query()->create([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $firstPeriod->id,
            'teacher_person_id' => $teacher->person_id,
            'updated_by_person_id' => $teacher->person_id,
            'class_date' => '2026-03-12',
            'lesson_count' => 4,
        ]);

        $builder = app(StudentAttendanceCertificateBuilder::class);
        $monthly = $builder->build(
            $enrollment->fresh(),
            CarbonImmutable::parse('2026-03-01'),
            CarbonImmutable::parse('2026-03-31'),
        );
        $period = $builder->build(
            $enrollment->fresh(),
            $firstPeriod->starts_at,
            $firstPeriod->ends_at,
            $firstPeriod,
        );
        $annual = $builder->build(
            $enrollment->fresh(),
            $year->starts_at,
            $year->ends_at,
        );

        $this->assertSame(2, $monthly['attendance']['lessons']);
        $this->assertSame(3, $period['attendance']['lessons']);
        $this->assertSame(6, $annual['attendance']['lessons']);
        $this->assertCount(2, $monthly['matrices']);
        $this->assertSame(
            ['Ensino Médio', 'Educação Profissional Técnica de Nível Médio'],
            $monthly['matrices']->pluck('stage')->all(),
        );
        $this->assertSame(0, $monthly['matrices']->firstWhere('course.id', $technicalCourse->id)['attendance']['lessons']);

        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-atestado-mensal@ctjj.org');
        $this->actingAs($manager)
            ->get(route('enrollments.attendance-certificate.pdf', [
                'enrollment' => $enrollment,
                'attendance_scope' => 'month',
                'attendance_month' => '2026-03',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $document = IssuedDocument::query()->latest('id')->firstOrFail();
        $this->assertSame('month', $document->payload['scope']);
        $this->assertSame(2, $document->payload['lessons']);
        $this->assertCount(2, $document->payload['matrices']);
    }

    public function test_third_bimester_and_its_months_can_be_issued_from_the_document_hub(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $period->update(['name' => 'III Bimestre', 'starts_at' => '2026-08-03', 'ends_at' => '2026-10-09']);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'terceiro-bimestre@ctjj.org');

        foreach ([
            ['attendance_scope' => 'period', 'academic_period_id' => $period->id],
            ['attendance_scope' => 'month', 'attendance_month' => '2026-08'],
            ['attendance_scope' => 'month', 'attendance_month' => '2026-09'],
            ['attendance_scope' => 'month', 'attendance_month' => '2026-10'],
        ] as $scope) {
            $redirect = $this->actingAs($manager)->get(route('document-issuance.issue', [
                'type' => 'attendance-certificate', 'target_id' => $enrollment->id, ...$scope,
            ]))->assertRedirect();
            $this->get($redirect->headers->get('Location'))->assertOk()->assertHeader('content-type', 'application/pdf');
            $document = IssuedDocument::query()->latest('id')->firstOrFail();
            $this->assertSame($scope['attendance_scope'], $document->payload['scope']);
        }
    }

    public function test_management_can_review_and_emit_student_declarations(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-declaracoes@ctjj.org');
        $manager->person->update(['full_name' => 'Diretora da Escola']);
        $manager->person->schoolRoles()->firstOrFail()->update(['position' => PersonSchoolRole::POSITION_DIRECTOR]);
        $secretary = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'secretaria-declaracoes@ctjj.org');
        $secretary->person->update(['full_name' => 'Secretária da Escola']);
        $secretary->person->schoolRoles()->firstOrFail()->update(['position' => PersonSchoolRole::POSITION_SECRETARY]);

        $this->actingAs($manager)
            ->get(route('enrollments.documents', $enrollment))
            ->assertOk()
            ->assertSee('Conferência documental')
            ->assertSee('Declaração de matrícula')
            ->assertSee('Declaração de conclusão');

        $this->actingAs($manager)
            ->get(route('enrollments.enrollment-declaration.pdf', $enrollment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $issuedDocument = IssuedDocument::query()->where('type', 'student-enrollment-declaration')->latest('id')->firstOrFail();
        $declaration = view('reports.certificates.declaration', [
            'academicYear' => $year->load('school'),
            'class' => $class->load('courses'),
            'enrollment' => $enrollment->load('student', 'courses'),
            'issuedDocument' => $issuedDocument,
            'letterhead' => PdfLetterhead::make($year->school),
            'title' => 'Declaração de matrícula',
            'statement' => 'Declaramos a matrícula do estudante.',
        ])->render();
        $this->assertStringContainsString('Diretora da Escola', $declaration);
        $this->assertStringContainsString('Secretária da Escola', $declaration);
        $this->assertStringContainsString('Direção escolar', $declaration);
        $this->assertStringContainsString('Secretaria escolar', $declaration);

        $this->actingAs($manager)
            ->get(route('enrollments.schooling-declaration.pdf', $enrollment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($manager)
            ->get(route('enrollments.completion-declaration.pdf', $enrollment))
            ->assertRedirect(route('enrollments.documents', $enrollment));

        $enrollment->update([
            'final_result_status' => StudentEnrollment::FINAL_APPROVED,
            'final_result_calculated_at' => now(),
            'final_result_calculated_by_person_id' => $manager->person_id,
        ]);

        $this->actingAs($manager)
            ->get(route('enrollments.completion-declaration.pdf', $enrollment->fresh()))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'student-enrollment-declaration',
            'person_id' => $enrollment->person_id,
        ]);
        $this->assertDatabaseHas('issued_documents', [
            'type' => 'student-schooling-declaration',
            'person_id' => $enrollment->person_id,
        ]);
        $this->assertDatabaseHas('issued_documents', [
            'type' => 'student-completion-declaration',
            'person_id' => $enrollment->person_id,
        ]);
    }

    public function test_management_can_emit_transfer_certificate_without_effecting_transfer(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-atestado-transferencia@ctjj.org');

        $this->actingAs($manager)
            ->get(route('enrollments.transfer-certificate.pdf', $enrollment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $issuedBeforeTransfer = IssuedDocument::query()->where('type', 'student-transfer-certificate')->firstOrFail();
        $this->assertSame(StudentEnrollment::STATUS_ENROLLED, $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->transferred_at);
        $this->assertSame(StudentEnrollment::STATUS_ENROLLED, $issuedBeforeTransfer->payload['enrollment_status']);
        $this->assertNull($issuedBeforeTransfer->payload['transferred_at']);

        $certificateBeforeTransfer = view('reports.certificates.transfer', [
            'academicYear' => $year->load('school'),
            'class' => $class->load('courses'),
            'enrollment' => $enrollment->fresh()->load('student', 'courses', 'transferredBy'),
            'issuedDocument' => $issuedBeforeTransfer,
            'letterhead' => PdfLetterhead::make($year->school),
        ])->render();
        $this->assertStringContainsString('não altera a situação da matrícula nem efetiva a transferência', $certificateBeforeTransfer);
        $this->assertStringNotContainsString('Data da transferência</th>', $certificateBeforeTransfer);

        $enrollment->update([
            'status' => StudentEnrollment::STATUS_TRANSFERRED,
            'transferred_at' => '2026-04-01',
            'transferred_by_person_id' => $manager->person_id,
        ]);

        $this->actingAs($manager)
            ->get(route('enrollments.transfer-certificate.pdf', $enrollment->fresh()))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $issuedAfterTransfer = IssuedDocument::query()->where('type', 'student-transfer-certificate')->latest('id')->firstOrFail();
        $this->assertSame(StudentEnrollment::STATUS_TRANSFERRED, $issuedAfterTransfer->payload['enrollment_status']);
        $this->assertSame('2026-04-01', $issuedAfterTransfer->payload['transferred_at']);

        $this->assertDatabaseHas('issued_documents', [
            'type' => 'student-transfer-certificate',
            'person_id' => $enrollment->person_id,
            'school_id' => $year->school_id,
        ]);
    }

    public function test_management_can_convalidate_partial_period_result_for_transfer_student(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao.convalidacao@ctjj.org');

        $this->actingAs($manager)
            ->post(route('enrollments.convalidations.store', $enrollment), [
                'academic_period_id' => $period->id,
                'curriculum_component_id' => $component->id,
                'score' => '7,0',
                'attendance_lessons' => 10,
                'attendance_absences' => 2,
                'attendance_justified_absences' => 1,
                'source_school' => 'Escola de origem',
                'convalidated_at' => '2026-03-01',
                'notes' => 'Resultado parcial apresentado na matrícula.',
            ])
            ->assertRedirect(route('enrollments.report-card.show', $enrollment));

        $saved = $enrollment->periodConvalidations()->sole();
        $this->get(route('enrollments.origin-sheet', $enrollment))
            ->assertOk()->assertSee('Ficha da escola de origem')->assertSee('value="Escola de origem"', false);
        $this->assertDatabaseCount('diary_attendance_records', 0);
        $this->actingAs($teacher)->post(route('enrollments.convalidations.store', $enrollment), [])->assertForbidden();
        $this->actingAs($manager)->post(route('enrollments.convalidations.store', $enrollment), [
            'academic_period_id' => $period->id, 'curriculum_component_id' => $component->id,
            'score' => 7, 'attendance_absences' => 2,
        ])->assertSessionHasErrors('attendance_lessons');

        $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
        $componentReport = $report['periodReports']->first()['components']->first();

        $this->assertSame(7.0, $componentReport['average']['value']);
        $this->assertTrue($componentReport['average']['complete']);
        $this->assertSame('convalidated', $componentReport['average']['source']);
        $this->assertSame(10, $componentReport['attendance']['lessons']);
        $this->assertSame(2, $componentReport['attendance']['absent']);
        $this->assertSame(1, $componentReport['attendance']['justified']);
        $this->assertSame(90.0, $componentReport['attendance']['percentage']);
        $this->assertDatabaseHas('student_period_convalidations', [
            'student_enrollment_id' => $enrollment->id,
            'score' => 7.0,
            'attendance_lessons' => 10,
            'attendance_absences' => 2,
            'attendance_justified_absences' => 1,
        ]);
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

    public function test_opening_diary_creates_missing_assessment_from_period_rule(): void
    {
        [$teacher, , $class, $component, $period, $enrollment] = $this->diaryScenario();
        $rule = $period->assessmentRules()->firstOrFail();

        $this->assertDatabaseMissing('diary_assessments', [
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'school_assessment_rule_id' => $rule->id,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()
            ->assertSee('scores[');

        $assessment = DiaryAssessment::query()
            ->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)
            ->where('academic_period_id', $period->id)
            ->where('school_assessment_rule_id', $rule->id)
            ->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => '8,5']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_assessment_results', [
            'diary_assessment_id' => $assessment->id,
            'student_enrollment_id' => $enrollment->id,
            'score' => 8.5,
        ]);
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
        $this->assertFalse($response->viewData('assessments')->first()->is_recovery);
        $this->assertTrue($response->viewData('assessments')->last()->is_recovery);
    }

    public function test_recovery_can_keep_the_greater_between_period_average_and_recovery_score(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-media-recuperacao@ctjj.org');

        foreach ([2, 3, 4] as $position) {
            $year->periods()->create([
                'name' => $position.'º Bimestre',
                'starts_at' => "2026-0{$position}-01",
                'ends_at' => "2026-0{$position}-28",
                'position' => $position,
            ]);
        }

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [1, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_PERIOD_AVERAGE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $assessments = DiaryAssessment::query()
            ->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)
            ->orderBy('is_recovery')
            ->orderBy('school_assessment_rule_id')
            ->get();
        $regularAssessments = $assessments->where('is_recovery', false)->values();
        $recoveryAssessment = $assessments->firstWhere('is_recovery', true);

        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scores' => [
                $regularAssessments[0]->id => [$enrollment->id => 4],
                $regularAssessments[1]->id => [$enrollment->id => 5],
            ],
        ]);

        $average = $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->viewData('averages')[$enrollment->id];
        $this->assertSame(4.5, $average['regular_value']);
        $this->assertSame(6.0, $average['period_passing_score']);
        $this->assertTrue($average['recovery_required']);
        $this->assertTrue($average['complete']);

        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scores' => [$recoveryAssessment->id => [$enrollment->id => 3]],
        ]);
        $average = $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->viewData('averages')[$enrollment->id];
        $this->assertSame(4.5, $average['value']);
        $this->assertFalse($average['recovery_applied']);

        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), [
            'academic_period_id' => $period->id,
            'scores' => [$recoveryAssessment->id => [$enrollment->id => 8]],
        ]);
        $average = $this->actingAs($teacher)
            ->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->viewData('averages')[$enrollment->id];
        $this->assertSame(4.5, $average['regular_value']);
        $this->assertSame(8.0, $average['recovery_value']);
        $this->assertSame(8.0, $average['value']);
        $this->assertTrue($average['recovery_applied']);
        $this->assertTrue($average['complete']);
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

        $this->actingAs($manager)->put(route('academic-years.periods.behavior.update', [$year, $period]), [
            'behavior_scores' => [$enrollment->id => 9],
        ])->assertRedirect(route('academic-years.periods.index', $year));

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

    public function test_management_cannot_consolidate_period_without_behavior_grades(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-comportamento@ctjj.org');

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
            ->assertSessionHasErrors('period');

        $this->actingAs($manager)->put(route('academic-years.periods.behavior.update', [$year, $period]), [
            'behavior_scores' => [$enrollment->id => 8.5],
            'behavior_notes' => [$enrollment->id => 'Participativo.'],
        ])->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('student_behavior_grades', [
            'academic_period_id' => $period->id,
            'student_enrollment_id' => $enrollment->id,
            'updated_by_person_id' => $manager->person_id,
            'score' => 8.5,
        ]);

        $this->actingAs($manager)->post(route('academic-years.periods.diaries.consolidate', [$year, $period]))
            ->assertRedirect(route('academic-years.periods.index', $year));
    }

    public function test_behavior_class_filter_lists_year_classes_and_saving_one_class_preserves_another(): void
    {
        [, $year, $class, , $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-filtro-comportamento@ctjj.org');
        $otherStudent = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'outro-estudante-filtro@ctjj.org');
        $otherClass = $year->classes()->create(['name' => '2º Ano B', 'active' => true]);
        $otherEnrollment = $otherClass->enrollments()->create([
            'person_id' => $otherStudent->person_id,
            'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'type' => StudentEnrollment::TYPE_REGULAR,
        ]);

        $this->actingAs($manager)->get(route('academic-years.periods.index', $year))
            ->assertOk()
            ->assertSee('id="behavior-class-filter-'.$period->id.'"', false)
            ->assertSee('value="'.$class->id.'"', false)
            ->assertSee('value="'.$otherClass->id.'"', false)
            ->assertSee('data-behavior-class-id="'.$otherClass->id.'"', false);

        $this->put(route('academic-years.periods.behavior.update', [$year, $period]), [
            'behavior_scores' => [$enrollment->id => 8, $otherEnrollment->id => 9],
        ])->assertRedirect(route('academic-years.periods.index', $year));
        $this->put(route('academic-years.periods.behavior.update', [$year, $period]), [
            'behavior_scores' => [$enrollment->id => 7],
        ])->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('student_behavior_grades', [
            'academic_period_id' => $period->id,
            'student_enrollment_id' => $otherEnrollment->id,
            'score' => 9,
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

    public function test_saved_attendance_outside_current_schedule_remains_visible_and_can_be_completed_or_deleted(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        for ($day = CarbonImmutable::parse('2026-02-04'); $day->lte('2026-04-08'); $day = $day->addWeek()) {
            $year->days()->firstOrCreate(['date' => $day->startOfDay()], ['type' => CalendarDay::TYPE_SCHOOL_DAY, 'counts_as_school_day' => true]);
        }
        $date = '2026-04-09'; // Quinta-feira: o horário atual prevê somente quarta-feira.
        $year->days()->create(['date' => $date, 'type' => CalendarDay::TYPE_SCHOOL_DAY, 'counts_as_school_day' => true]);
        $record = DiaryAttendanceRecord::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'teacher_person_id' => $teacher->person_id,
            'class_date' => $date, 'lesson_count' => 2,
        ]);
        $entry = $record->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 2, 'lesson_presence' => [true, true]]);
        $assignment = SchoolClassComponent::query()->where('school_class_id', $class->id)->firstOrFail();
        $this->assertSame([$date], app(DiaryPeriodStatus::class)->pending($assignment, $period)['attendance_without_content']);

        $this->actingAs($teacher)->get(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]))
            ->assertOk()->assertSee('09/04/2026')->assertSee('focus_date=2026-04-09', false);
        $parameters = [$class, $component, 'period' => $period->id, 'focus_date' => $date];
        $this->get(route('teacher-diaries.attendance', $parameters))
            ->assertOk()->assertViewHas('page', 2)->assertSee('Fora do horário atual')
            ->assertSee('name="lesson_counts['.$date.']"', false)
            ->assertViewHas('records', fn ($records) => $records->get($date)?->lesson_count === 2);
        $this->get(route('teacher-diaries.contents', $parameters))
            ->assertOk()->assertViewHas('page', 2)->assertSee('name="contents['.$date.']"', false);

        $this->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id, 'page' => 2, 'selected_dates' => [$date],
            'contents' => [$date => 'Conteúdo da aula já realizada.'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([], app(DiaryPeriodStatus::class)->pending($assignment, $period)['attendance_without_content']);

        $this->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id, 'page' => 2, 'selected_dates' => [$date], 'delete_dates' => [$date],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
            'academic_period_id' => $period->id, 'page' => 2, 'scheduled_dates' => [$date], 'lesson_counts' => [$date => 0],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertModelMissing($record);
        $this->assertModelMissing($entry);
        $pending = app(DiaryPeriodStatus::class)->pending($assignment, $period);
        $this->assertSame([], $pending['attendance_without_content']);
        $this->assertSame([], $pending['content_without_attendance']);
        $this->get(route('teacher-diaries.contents', $parameters))->assertOk()->assertDontSee('name="contents['.$date.']"', false);
    }

    public function test_existing_contents_remain_accessible_after_calendar_changes_without_allowing_unrecorded_dates(): void
    {
        [$teacher, $year, $class, $component, $period] = $this->diaryScenario();
        // O conteúdo existe, mas a data já não está no calendário nem no horário.
        $date = '2026-03-12';
        DiaryContent::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'class_date' => $date, 'content' => 'Aula registrada anteriormente.',
        ]);
        $otherPeriod = $year->periods()->create(['name' => '2º Bimestre', 'starts_at' => '2026-04-11', 'ends_at' => '2026-06-30', 'position' => 2]);
        DiaryContent::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $otherPeriod->id, 'class_date' => '2026-05-07', 'content' => 'Outro bimestre.',
        ]);
        $parameters = [$class, $component, 'period' => $period->id, 'page' => 1];
        $this->actingAs($teacher)->get(route('teacher-diaries.contents', $parameters))
            ->assertOk()->assertSee('Aula registrada anteriormente.')->assertDontSee('Outro bimestre.');
        $this->get(route('teacher-diaries.attendance', $parameters))
            ->assertOk()->assertSee('name="lesson_counts['.$date.']"', false)
            ->assertDontSee('name="lesson_counts[2026-05-07]"', false);
        $this->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
            'academic_period_id' => $period->id, 'scheduled_dates' => [$date], 'lesson_counts' => [$date => 1],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('teacher-diaries.contents.update', [$class, $component]), [
            'academic_period_id' => $period->id, 'selected_dates' => [$date, '2026-03-13', '2026-05-07'],
            'contents' => [$date => 'Conteúdo corrigido.', '2026-03-13' => 'Data não permitida.', '2026-05-07' => 'Outro período não pode ser alterado.'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('diary_contents', ['class_date' => $date.' 00:00:00', 'content' => 'Conteúdo corrigido.']);
        $this->assertDatabaseMissing('diary_contents', ['class_date' => '2026-03-13 00:00:00']);
        $this->assertDatabaseHas('diary_contents', ['academic_period_id' => $otherPeriod->id, 'content' => 'Outro bimestre.']);
        $this->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
            'academic_period_id' => $period->id, 'scheduled_dates' => ['2026-03-13'], 'lesson_counts' => ['2026-03-13' => 1],
        ])->assertSessionHasErrors('scheduled_dates');
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
            ->assertRedirect(route('attendance-justifications.index', ['school' => $year->school_id, 'year' => $year->id, 'class' => $class->id]));

        $this->assertDatabaseHas('diary_attendance_justifications', [
            'student_enrollment_id' => $enrollment->id,
            'reason' => 'Atestado médico apresentado.',
            'granted_by_person_id' => $manager->person_id,
        ]);

        $this->assertSame(1, DiaryAttendanceJustification::query()->count());
    }

    public function test_absence_justifications_filter_by_school_year_and_class(): void
    {
        [, $year, $class, , , $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'filtros-justificativas@ctjj.org');
        $otherClass = $year->classes()->create(['name' => '1º Ano B', 'active' => true]);
        $otherClass->courses()->attach($class->courses()->firstOrFail());
        $otherStudent = Person::query()->create(['full_name' => 'Estudante da Turma B', 'active' => true]);
        $otherEnrollment = $otherClass->enrollments()->create([
            'person_id' => $otherStudent->id, 'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $previousYear = $year->school->academicYears()->create([
            'name' => 'Ano anterior', 'reference_year' => 2025, 'starts_at' => '2025-01-20',
            'ends_at' => '2025-12-18', 'active' => true,
        ]);
        $previousClass = $previousYear->classes()->create(['name' => 'Turma antiga', 'active' => true]);
        $previousEnrollment = $previousClass->enrollments()->create([
            'person_id' => $otherStudent->id, 'enrolled_at' => '2025-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        foreach ([
            [$enrollment, 'Motivo da turma A', '2026-03-10'],
            [$otherEnrollment, 'Motivo da turma B', '2026-03-11'],
            [$previousEnrollment, 'Motivo do ano anterior', '2025-03-11'],
        ] as [$target, $reason, $date]) {
            DiaryAttendanceJustification::query()->create([
                'student_enrollment_id' => $target->id, 'starts_at' => $date,
                'ends_at' => $date, 'reason' => $reason,
                'granted_by_person_id' => $manager->person_id,
            ]);
        }

        $this->actingAs($manager)->get(route('attendance-justifications.index', [
            'school' => $year->school_id, 'year' => $year->id, 'class' => $class->id,
        ]))->assertOk()
            ->assertSee('Motivo da turma A')
            ->assertDontSee('Motivo da turma B')
            ->assertDontSee('Motivo do ano anterior')
            ->assertSee('Criada por')
            ->assertSee('Última alteração')
            ->assertSee('Alterações');

        $this->get(route('attendance-justifications.index', [
            'school' => $year->school_id, 'year' => $year->id,
        ]))->assertOk()->assertSee('Motivo da turma A')->assertSee('Motivo da turma B')->assertDontSee('Motivo do ano anterior');

        $otherSchool = School::query()->create($this->officialSchoolData(['name' => 'Outra escola']));
        $otherYear = $otherSchool->academicYears()->create([
            'name' => 'Ano letivo', 'reference_year' => 2026, 'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18', 'active' => true,
        ]);
        $unrelatedClass = $otherYear->classes()->create(['name' => 'Turma de outra escola', 'active' => true]);
        $unrelatedEnrollment = $unrelatedClass->enrollments()->create([
            'person_id' => $otherStudent->id, 'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        DiaryAttendanceJustification::query()->create([
            'student_enrollment_id' => $unrelatedEnrollment->id, 'starts_at' => '2026-03-10',
            'ends_at' => '2026-03-10', 'reason' => 'Motivo de outra escola',
        ]);
        $this->get(route('attendance-justifications.index', ['school' => $otherSchool->id]))
            ->assertOk()->assertDontSee('Motivo de outra escola');

        $otherClass->update(['active' => false]);
        $this->get(route('attendance-justifications.index', ['school' => $year->school_id]))
            ->assertOk()->assertDontSee('Motivo da turma B');
        $this->post(route('attendance-justifications.store'), [
            'student_enrollment_id' => $otherEnrollment->id, 'starts_at' => '2026-03-12',
            'ends_at' => '2026-03-12', 'reason' => 'Não autorizado',
        ])->assertForbidden();
    }

    public function test_absence_justifications_paginate_without_losing_filters(): void
    {
        [, $year, $class, , , $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'paginas-justificativas@ctjj.org');

        for ($day = 1; $day <= 21; $day++) {
            $date = sprintf('2026-03-%02d', $day);
            DiaryAttendanceJustification::query()->create([
                'student_enrollment_id' => $enrollment->id,
                'starts_at' => $date, 'ends_at' => $date,
                'reason' => sprintf('Justificativa %02d', $day),
            ]);
        }

        $filters = ['school' => $year->school_id, 'year' => $year->id, 'class' => $class->id];
        $this->actingAs($manager)->get(route('attendance-justifications.index', $filters))
            ->assertOk()
            ->assertSee('21 justificativas')
            ->assertSee('Justificativa 21')
            ->assertDontSee('Justificativa 01')
            ->assertSee('page=2', false)
            ->assertSee('year='.$year->id, false)
            ->assertSee('class='.$class->id, false);
        $this->get(route('attendance-justifications.index', $filters + ['page' => 2]))
            ->assertOk()
            ->assertSee('Justificativa 11')
            ->assertDontSee('Justificativa 21');
        $this->get(route('attendance-justifications.index', $filters + ['page' => 3]))
            ->assertOk()
            ->assertSee('Justificativa 01')
            ->assertDontSee('Justificativa 11');
    }

    public function test_management_can_edit_absence_justification_and_see_its_edit_history(): void
    {
        [$teacher, $year, $class, , , $enrollment] = $this->diaryScenario();
        $creator = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'criador-justificativas@ctjj.org');
        $editor = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'editor-justificativas@ctjj.org');
        $lastEditor = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'ultimo-editor-justificativas@ctjj.org');
        $justification = DiaryAttendanceJustification::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'starts_at' => '2026-03-10', 'ends_at' => '2026-03-11',
            'reason' => 'Atestado inicial', 'granted_by_person_id' => $creator->person_id,
        ]);
        $payload = [
            'student_enrollment_id' => $enrollment->id,
            'starts_at' => '2026-03-11', 'ends_at' => '2026-03-11',
            'reason' => 'Atestado corrigido',
        ];

        $this->actingAs($teacher)->get(route('attendance-justifications.edit', $justification))->assertForbidden();
        $this->put(route('attendance-justifications.update', $justification), $payload)->assertForbidden();
        $this->actingAs($editor)->get(route('attendance-justifications.edit', $justification))
            ->assertOk()->assertSee('Atestado inicial');
        $this->put(route('attendance-justifications.update', $justification), $payload)
            ->assertRedirect(route('attendance-justifications.index', ['school' => $year->school_id, 'year' => $year->id, 'class' => $class->id]));
        $this->assertDatabaseHas('diary_attendance_justifications', [
            'id' => $justification->id, 'reason' => 'Atestado corrigido',
            'granted_by_person_id' => $creator->person_id,
            'updated_by_person_id' => $editor->person_id,
            'modification_count' => 1,
        ]);
        $this->put(route('attendance-justifications.update', $justification), $payload)->assertRedirect();
        $this->assertSame(1, $justification->fresh()->modification_count);
        $this->actingAs($lastEditor)->put(route('attendance-justifications.update', $justification), array_merge($payload, [
            'reason' => 'Atestado revisado novamente',
        ]))->assertRedirect();
        $this->assertDatabaseHas('diary_attendance_justifications', [
            'id' => $justification->id, 'modification_count' => 2,
            'updated_by_person_id' => $lastEditor->person_id,
        ]);
        $this->get(route('attendance-justifications.index', ['school' => $year->school_id]))
            ->assertOk()->assertSee($creator->person->full_name)->assertSee($lastEditor->person->full_name)->assertSee('Atestado revisado novamente');

        $correctStudent = Person::query()->create(['full_name' => 'Estudante correto', 'active' => true]);
        $correctEnrollment = $class->enrollments()->create([
            'person_id' => $correctStudent->id, 'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $this->put(route('attendance-justifications.update', $justification), array_merge($payload, [
            'student_enrollment_id' => $correctEnrollment->id,
            'reason' => 'Atestado vinculado ao estudante correto',
        ]))->assertRedirect();
        $this->assertDatabaseHas('diary_attendance_justifications', [
            'id' => $justification->id, 'student_enrollment_id' => $correctEnrollment->id,
            'modification_count' => 3, 'granted_by_person_id' => $creator->person_id,
        ]);

        $otherSchool = School::query()->create($this->officialSchoolData(['name' => 'Escola sem acesso']));
        $otherYear = $otherSchool->academicYears()->create([
            'name' => 'Ano letivo', 'reference_year' => 2026, 'starts_at' => '2026-01-20',
            'ends_at' => '2026-12-18', 'active' => true,
        ]);
        $otherClass = $otherYear->classes()->create(['name' => 'Turma alheia', 'active' => true]);
        $otherEnrollment = $otherClass->enrollments()->create([
            'person_id' => $enrollment->person_id, 'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $this->put(route('attendance-justifications.update', $justification), array_merge($payload, ['student_enrollment_id' => $otherEnrollment->id]))
            ->assertForbidden();
        $this->put(route('attendance-justifications.update', $justification), array_merge($payload, ['starts_at' => '2026-01-01']))
            ->assertSessionHasErrors('starts_at');
        $this->assertSame(3, $justification->fresh()->modification_count);
    }

    public function test_only_administrators_can_see_and_edit_closed_year_absence_justifications(): void
    {
        [, $year, $class, , , $enrollment] = $this->diaryScenario();
        $administrator = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, $year->school_id, 'admin-justificativas@ctjj.org');
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-ano-fechado@ctjj.org');
        $justification = DiaryAttendanceJustification::query()->create([
            'student_enrollment_id' => $enrollment->id, 'starts_at' => '2026-03-11',
            'ends_at' => '2026-03-11', 'reason' => 'Atestado de ano encerrado',
        ]);
        $year->update(['closed_at' => now(), 'active' => false]);
        $class->update(['active' => false]);

        $this->actingAs($administrator)->get(route('attendance-justifications.index', [
            'school' => $year->school_id, 'year' => $year->id, 'class' => $class->id,
        ]))->assertOk()
            ->assertSee('Atestado de ano encerrado')
            ->assertSee(route('attendance-justifications.edit', $justification), false);
        $this->get(route('attendance-justifications.edit', $justification))->assertOk();
        $this->put(route('attendance-justifications.update', $justification), [
            'student_enrollment_id' => $enrollment->id, 'starts_at' => '2026-03-11',
            'ends_at' => '2026-03-11', 'reason' => 'Correção administrativa',
        ])->assertRedirect();
        $this->assertSame(1, $justification->fresh()->modification_count);

        $this->actingAs($manager)->get(route('attendance-justifications.index', ['school' => $year->school_id]))
            ->assertOk()->assertDontSee('Correção administrativa');
        $this->get(route('attendance-justifications.edit', $justification))->assertForbidden();
    }

    public function test_teacher_can_issue_approval_overview_only_for_assigned_components(): void
    {
        [$teacher, $year, $class, $component] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'panorama@ctjj.org');
        $otherComponent = $component->course->components()->create(['name' => 'História', 'active' => true]);
        $otherTeacher = $this->userWithRole(PersonSchoolRole::ROLE_TEACHER, $year->school_id, 'outro-panorama@ctjj.org');
        SchoolClassComponent::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $otherComponent->id,
            'teacher_person_id' => $otherTeacher->person_id, 'active' => true,
        ]);
        $unassignedClass = $year->classes()->create(['name' => 'Turma não atribuída', 'active' => true]);
        $url = route('classes.approval-overview.pdf', $class);
        $componentIds = [];
        View::composer('reports.class-approval-overview', function ($view) use (&$componentIds): void {
            $componentIds = $view->getData()['reports']->flatMap(fn (array $report) => $report['annualComponents']->pluck('component.id'))->unique()->values()->all();
        });
        $this->actingAs($teacher)->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame([$component->id], $componentIds);
        $this->get(route('classes.approval-overview.pdf', $unassignedClass))->assertForbidden();
        $this->get(route('document-issuance.index'))->assertOk()->assertSee('value="class-approval-overview"', false);
        $this->getJson(route('document-issuance.targets', ['type' => 'class-approval-overview']))
            ->assertOk()->assertJsonCount(1, 'targets')->assertJsonPath('targets.0.id', $class->id);
        $this->get(route('document-issuance.issue', [
            'type' => 'class-approval-overview', 'target_id' => $unassignedClass->id,
        ]))->assertNotFound();
        $this->actingAs($manager)->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('issued_documents', ['type' => 'class-approval-overview', 'school_id' => $year->school_id]);
        $this->actingAs($manager)->get(route('document-issuance.index'))->assertOk()->assertSee('value="class-approval-overview"', false);
        $this->actingAs($manager)->get(route('document-issuance.issue', [
            'type' => 'class-approval-overview', 'target_id' => $class->id,
        ]))->assertRedirect($url);
    }

    public function test_teacher_can_issue_only_own_person_record_in_teacher_view(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $otherStudent = $this->userWithRole(PersonSchoolRole::ROLE_STUDENT, $year->school_id, 'fora-da-turma@ctjj.org');
        $teacher->person->schoolRoles()->create([
            'school_id' => $year->school_id,
            'role' => PersonSchoolRole::ROLE_MANAGER,
            'active' => true,
        ]);
        $teacher->update(['preferred_role_view' => PersonSchoolRole::ROLE_TEACHER]);

        $this->actingAs($teacher)->get(route('document-issuance.index'))
            ->assertOk()->assertSee('value="person-record"', false);
        $this->getJson(route('document-issuance.targets', ['type' => 'person-record']))
            ->assertOk()->assertJsonCount(1, 'targets')
            ->assertJsonPath('targets.0.id', $teacher->person_id);
        $this->getJson(route('document-issuance.targets', [
            'type' => 'person-record', 'school_id' => $year->school_id,
        ]))->assertOk()->assertJsonCount(1, 'targets');

        $this->get(route('document-issuance.issue', [
            'type' => 'person-record', 'target_id' => $teacher->person_id,
        ]))->assertRedirect(route('people.pdf', $teacher->person_id));
        $this->get(route('people.pdf', $teacher->person_id))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('document-issuance.issue', [
            'type' => 'person-record', 'target_id' => $enrollment->person_id,
        ]))->assertNotFound();
        $this->get(route('people.pdf', $enrollment->person_id))->assertForbidden();
        $this->get(route('document-issuance.issue', [
            'type' => 'person-record', 'target_id' => $otherStudent->person_id,
        ]))->assertNotFound();
        $this->get(route('people.pdf', $otherStudent->person_id))->assertForbidden();
    }

    public function test_teacher_dashboard_summarizes_only_current_diary_assignments(): void
    {
        [$teacher, $year, $class, $component] = $this->diaryScenario();
        $otherComponent = $component->course->components()->create(['name' => 'Português', 'active' => true]);
        SchoolClassComponent::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $otherComponent->id,
            'teacher_person_id' => $teacher->person_id, 'active' => true,
        ]);
        $year->classes()->create(['name' => 'Turma de outro docente', 'active' => true]);

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()
            ->assertViewHas('teacherOverview', fn (array $overview): bool => $overview['schools'] === 1 && $overview['classes'] === 1
                && $overview['components'] === 2 && $overview['students'] === 1)
            ->assertSee('Meus diários em andamento')
            ->assertSee(route('teacher-diaries.show', [$class, $component]));
    }

    public function test_management_can_issue_individual_progress_report_but_teacher_cannot(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'situacao@ctjj.org');
        $url = route('enrollments.progress.pdf', $enrollment);

        foreach (['2026-03-11', '2026-03-12'] as $date) {
            $record = DiaryAttendanceRecord::query()->create([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
                'class_date' => $date,
                'lesson_count' => 2,
            ]);
            $record->entries()->create([
                'student_enrollment_id' => $enrollment->id,
                'status' => DiaryAttendanceRecord::STATUS_ABSENT,
                'attended_lessons' => 0,
            ]);
        }
        DiaryAttendanceJustification::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'starts_at' => '2026-03-11',
            'ends_at' => '2026-03-11',
            'reason' => 'Atestado médico',
            'granted_by_person_id' => $manager->person_id,
        ]);

        $report = app(StudentReportCardBuilder::class)->build($enrollment->fresh());
        $summary = app(StudentProgressSummary::class)->fromReport($report);
        $this->assertSame(4, $summary['raw_absences']);
        $this->assertSame(2, $summary['justified_absences']);
        $this->assertSame(200, $summary['raw_absence_minutes']);
        $this->assertSame(100, $summary['justified_minutes']);
        $this->assertSame(100, $summary['absence_minutes']);
        $this->assertSame(2300, $summary['remaining_absence_minutes']);
        $this->assertSame(200, $summary['components'][0]['raw_absence_minutes']);

        $this->actingAs($teacher)->get($url)->assertForbidden();
        $this->getJson(route('document-issuance.targets', ['type' => 'student-progress']))->assertUnprocessable();

        $otherSchool = School::query()->create($this->officialSchoolData(['name' => 'Outra escola']));
        $otherManager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $otherSchool->id, 'outra-situacao@ctjj.org');
        $this->actingAs($otherManager)->get($url)->assertForbidden();
        $this->getJson(route('document-issuance.targets', ['type' => 'student-progress']))
            ->assertOk()->assertJsonCount(0, 'targets');

        $this->actingAs($manager)->get(route('document-issuance.index'))
            ->assertOk()->assertSee('value="student-progress"', false);
        $this->getJson(route('document-issuance.targets', ['type' => 'student-progress']))
            ->assertOk()->assertJsonPath('targets.0.id', $enrollment->id);
        $this->get(route('document-issuance.issue', ['type' => 'student-progress', 'target_id' => $enrollment->id]))
            ->assertRedirect($url);
        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('issued_documents', [
            'type' => 'student-progress', 'person_id' => $enrollment->person_id,
            'school_id' => $year->school_id,
        ]);
        $html = view('reports.student-progress', [
            'report' => $report,
            'progress' => $summary,
            'issuedDocument' => IssuedDocument::query()->where('type', 'student-progress')->firstOrFail(),
            'letterhead' => PdfLetterhead::make($year->school),
        ])->render();
        $this->assertStringContainsString('Horas de falta', $html);
        $this->assertStringContainsString('3 h 20 min', $html);
        $this->assertStringNotContainsString('Abatidas:', $html);
    }

    public function test_one_justified_school_day_is_abated_from_overall_absences_across_components(): void
    {
        [, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $second = $component->course->components()->create([
            'name' => 'Biologia', 'workload_hours' => 80, 'active' => true,
        ]);
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'atestados@ctjj.org');

        foreach ([$component, $second] as $subject) {
            $record = DiaryAttendanceRecord::query()->create([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $subject->id,
                'academic_period_id' => $period->id,
                'class_date' => '2026-03-11',
                'lesson_count' => 2,
            ]);
            $record->entries()->create([
                'student_enrollment_id' => $enrollment->id,
                'status' => DiaryAttendanceRecord::STATUS_ABSENT,
                'attended_lessons' => 0,
            ]);
        }
        DiaryAttendanceJustification::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'starts_at' => '2026-03-11',
            'ends_at' => '2026-03-11',
            'reason' => 'Atestado médico',
            'granted_by_person_id' => $manager->person_id,
        ]);

        $summary = app(StudentProgressSummary::class)->fromReport(
            app(StudentReportCardBuilder::class)->build($enrollment->fresh())
        );

        $this->assertSame(4, $summary['raw_absences']);
        $this->assertSame(4, $summary['justified_absences']);
        $this->assertSame(200, $summary['justified_minutes']);
        $this->assertSame(0, $summary['absence_minutes']);
        $this->assertSame(3600, $summary['remaining_absence_minutes']);
    }

    public function test_weight_change_preserves_existing_grades_without_delete_confirmation(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'pesos@ctjj.org');
        $url = route('academic-years.periods.assessment-rules.update', [$year, $period]);
        $payload = ['assessment_count' => 2, 'weights' => [10, 10], 'recovery_mode' => AcademicPeriod::RECOVERY_NONE];
        $this->actingAs($manager)->put($url, $payload)->assertRedirect();
        $assessments = DiaryAssessment::query()->orderBy('id')->get();
        $scores = [];
        foreach ($assessments as $index => $assessment) {
            $scores[$assessment->id] = [$enrollment->id => $index === 0 ? 8 : 6];
        }
        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), [
            'academic_period_id' => $period->id, 'scores' => $scores,
        ])->assertSessionHasNoErrors();
        $before = DB::table('diary_assessment_results')->orderBy('id')->get()->toJson();
        $this->assertNotSame('[]', $before);
        $payload['weights'] = [10, 5];
        $this->actingAs($manager)->put($url, $payload)->assertSessionHasNoErrors()
            ->assertSessionMissing('assessment_change_warning')
            ->assertSessionHas('status', 'Avaliações do período configuradas. As notas existentes foram preservadas.');
        $this->assertSame($before, DB::table('diary_assessment_results')->orderBy('id')->get()->toJson());
        $this->assertSame($assessments->pluck('id')->all(), DiaryAssessment::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame(5, (int) $assessments->last()->fresh()->weight);
        $this->assertSame(5, (int) $period->assessmentRules()->where('position', 2)->firstOrFail()->weight);
    }

    public function test_management_must_confirm_before_changing_assessment_configuration_with_grades(): void
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
            ->assertSessionHas('assessment_change_warning');

        $this->assertDatabaseHas('diary_assessment_results', ['diary_assessment_id' => $assessment->id, 'score' => 8]);

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [1, 1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
                'confirm_delete_assessment_data' => 1,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseMissing('diary_assessment_results', ['diary_assessment_id' => $assessment->id]);
        $this->assertSame(2, SchoolAssessmentRule::query()->where('academic_period_id', $period->id)->count());
    }

    public function test_management_can_remove_unused_assessment_after_grade_entry(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-remove-vazia@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [1, 1],
                'assessment_names' => ['Avaliação 1', 'Avaliação sem uso'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $assessment = DiaryAssessment::query()
            ->whereHas('rule', fn ($query) => $query->where('position', 1))
            ->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 8]],
            ])
            ->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'assessment_names' => ['Avaliação 1'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertSessionHas('assessment_change_warning');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'assessment_names' => ['Avaliação 1'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
                'confirm_delete_assessment_data' => 1,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 1,
            'name' => 'Avaliação 1',
        ]);
        $this->assertDatabaseMissing('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 2,
        ]);
    }

    public function test_management_can_rename_assessment_without_warning_when_grades_exist(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-renomeia-avaliacao@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [5, 5],
                'assessment_names' => ['Avaliação 1', 'Avaliação 2'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $assessment = DiaryAssessment::query()->where('is_recovery', false)->orderBy('id')->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 8]],
            ])
            ->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [5, 5],
                'assessment_names' => ['Nova avaliação 1', 'Nova avaliação 2'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year))
            ->assertSessionMissing('assessment_change_warning');

        $this->assertDatabaseHas('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 1,
            'name' => 'Nova avaliação 1',
        ]);
        $this->assertDatabaseHas('diary_assessment_results', [
            'diary_assessment_id' => $assessment->id,
            'score' => 8,
        ]);
    }

    public function test_management_can_change_recovery_mode_without_warning_when_grades_exist(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-altera-recuperacao@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [5, 5],
                'assessment_names' => ['Avaliação 1', 'Avaliação 2'],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year));

        $assessment = DiaryAssessment::query()->where('is_recovery', false)->orderBy('id')->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 8]],
            ])
            ->assertRedirect(route('teacher-diaries.show', [$class, $component, 'period' => $period->id]));

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 2,
                'weights' => [5, 5],
                'assessment_names' => ['Avaliação 1', 'Avaliação 2'],
                'recovery_mode' => AcademicPeriod::RECOVERY_WEIGHTED,
                'recovery_weight' => 2,
            ])
            ->assertRedirect(route('academic-years.periods.index', $year))
            ->assertSessionMissing('assessment_change_warning');

        $this->assertDatabaseHas('academic_periods', [
            'id' => $period->id,
            'recovery_mode' => AcademicPeriod::RECOVERY_WEIGHTED,
            'recovery_weight' => 2,
        ]);
        $this->assertDatabaseHas('diary_assessment_results', [
            'diary_assessment_id' => $assessment->id,
            'score' => 8,
        ]);
    }

    public function test_management_can_change_weights_and_recovery_without_erasing_regular_grades(): void
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
                'assessment_names' => ['Nova Avaliação 1', 'Nova Avaliação 2'],
                'recovery_mode' => AcademicPeriod::RECOVERY_REPLACE_LOWEST,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('assessment_change_warning')
            ->assertRedirect(route('academic-years.periods.index', $year));

        $this->assertDatabaseHas('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 1,
            'name' => 'Nova Avaliação 1',
            'weight' => 99,
        ]);
        $this->assertDatabaseHas('school_assessment_rules', [
            'academic_period_id' => $period->id,
            'position' => 2,
            'name' => 'Nova Avaliação 2',
            'weight' => 99,
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
        $this->assertDatabaseHas('diary_assessment_results', [
            'diary_assessment_id' => $assessment->id,
            'student_enrollment_id' => $enrollment->id,
            'score' => 7.5,
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

    public function test_administrative_reopening_allows_corrections_in_confirmed_and_consolidated_diary(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR, $year->school_id, 'admin-reopening@ctjj.org');
        $this->actingAs($admin)->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
            'assessment_count' => 1, 'weights' => [1], 'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
        ])->assertSessionHasNoErrors();
        $assessment = DiaryAssessment::query()->firstOrFail();
        DiaryPeriodConfirmation::query()->create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id, 'confirmed' => true,
        ]);
        AcademicPeriodDiaryConsolidation::query()->create([
            'academic_period_id' => $period->id, 'consolidated' => true,
        ]);
        $year->update(['active' => false, 'closed_at' => now()]);
        $this->assertNull($year->administrative_reopened_at);
        $this->assertTrue($year->isClosed());
        $data = ['academic_period_id' => $period->id, 'scores' => [$assessment->id => [$enrollment->id => 8]]];
        $this->put(route('teacher-diaries.grades.update', [$class, $component]), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('diary_assessment_results', ['diary_assessment_id' => $assessment->id, 'student_enrollment_id' => $enrollment->id, 'score' => 8]);
        $year->update(['active' => true]);
        $this->actingAs($teacher)->put(route('teacher-diaries.grades.update', [$class, $component]), $data)->assertSessionHasErrors('academic_period_id');
        $this->get(route('teacher-diaries.show', [$class, $component]))->assertOk();
    }

    public function test_closed_academic_year_blocks_diary_launches(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $year->school_id, 'gestao-fechamento@ctjj.org');

        $this->actingAs($manager)
            ->put(route('academic-years.periods.assessment-rules.update', [$year, $period]), [
                'assessment_count' => 1,
                'weights' => [1],
                'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
            ]);
        $assessment = DiaryAssessment::query()->firstOrFail();

        $year->update(['closed_at' => '2026-12-23 12:00:00']);

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.grades.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scores' => [$assessment->id => [$enrollment->id => 8]],
            ])
            ->assertSessionHasErrors('academic_period_id');

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scheduled_dates' => ['2026-03-11'],
                'page' => 1,
                'lesson_counts' => ['2026-03-11' => 1],
                'attendance' => ['2026-03-11' => [$enrollment->id => [1 => '1']]],
            ])
            ->assertSessionHasErrors('academic_period_id');
    }

    public function test_teacher_can_launch_attendance_and_content_outside_period_when_management_allows_it(): void
    {
        [$teacher, $year, $class, $component, $period, $enrollment] = $this->diaryScenario();
        $outsideDate = '2026-05-13';
        $year->days()->create([
            'date' => $outsideDate,
            'type' => CalendarDay::TYPE_SCHOOL_DAY,
            'counts_as_school_day' => true,
        ]);
        $period->update(['allow_diary_entries_outside_period' => true]);

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.attendance.batch-update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'scheduled_dates' => [$outsideDate],
                'page' => 1,
                'lesson_counts' => [$outsideDate => 1],
                'attendance' => [$outsideDate => [$enrollment->id => [1 => '1']]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($teacher)
            ->put(route('teacher-diaries.contents.update', [$class, $component]), [
                'academic_period_id' => $period->id,
                'selected_dates' => [$outsideDate],
                'page' => 1,
                'contents' => [$outsideDate => 'Revisão do conteúdo do período.'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diary_attendance_records', [
            'academic_period_id' => $period->id,
            'class_date' => $outsideDate.' 00:00:00',
        ]);
        $this->assertDatabaseHas('diary_contents', [
            'academic_period_id' => $period->id,
            'class_date' => $outsideDate.' 00:00:00',
            'content' => 'Revisão do conteúdo do período.',
        ]);
    }

    /**
     * @return array{0: User, 1: AcademicYear, 2: SchoolClass, 3: CurriculumComponent, 4: AcademicPeriod, 5: StudentEnrollment}
     */
    private function diaryScenario(array $yearOverrides = []): array
    {
        $school = School::query()->create($this->officialSchoolData(['name' => 'Escola A']));
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
            'birth_city' => 'Poxoreu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Maria da Silva',
            'phone' => '(65) 99999-0000',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'profile_completed_at' => now(),
            'active' => true,
        ]);

        $person->schoolRoles()->create([
            'school_id' => $schoolId,
            'role' => $role,
            'started_at' => '2026-01-01',
            'active' => true,
        ]);

        if ($role === PersonSchoolRole::ROLE_STUDENT) {
            $person->contacts()->create([
                'name' => 'Maria da Silva',
                'relationship_type' => PersonContact::TYPE_MOTHER,
                'cpf' => fake()->unique()->numerify('###########'),
            ]);
        }

        return User::factory()->create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $email,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function officialSchoolData(array $overrides = []): array
    {
        static $sequence = 0;

        $sequence++;

        return array_merge([
            'name' => 'Escola Oficial '.$sequence,
            'legal_name' => 'Centro Tecnico Juvenil de Jarudore',
            'cnpj' => str_pad((string) $sequence, 14, '0', STR_PAD_LEFT),
            'inep' => str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'founded_at' => '1990-10-04',
            'phone' => '(66) 99613-6796',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'email' => 'ctjj.mt@gmail.com',
            'website' => 'https://ctjj.org',
            'letterhead_text' => 'Credenciamento e autorizacao vigentes.',
            'address' => 'Av. Sao Joao',
            'district' => 'Jarudore',
            'number' => 's/n',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-970',
            'active' => true,
        ], $overrides);
    }
}
