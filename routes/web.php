<?php

use App\Http\Controllers\AcademicCalendarPdfController;
use App\Http\Controllers\AcademicCourseController;
use App\Http\Controllers\AcademicMatricesPdfController;
use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\AcademicYearClosureController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AcademicYearFinalResultsPdfController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceJustificationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CalendarDayController;
use App\Http\Controllers\ClassAcademicDocumentsController;
use App\Http\Controllers\ClassFinalResultsPdfController;
use App\Http\Controllers\CurriculumComponentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\DocumentIssuanceController;
use App\Http\Controllers\EnrollmentPdfController;
use App\Http\Controllers\OfficialDocumentController;
use App\Http\Controllers\PersonContactController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\GoogleWorkspaceController;
use App\Http\Controllers\PersonRoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordPdfController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolAcademicYearController;
use App\Http\Controllers\SchoolClassComponentController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolClassScheduleController;
use App\Http\Controllers\SchoolClassSchedulePdfController;
use App\Http\Controllers\SchoolConceptController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\StudentAcademicHistoryController;
use App\Http\Controllers\StudentDiaryController;
use App\Http\Controllers\StudentEnrollmentCertificateController;
use App\Http\Controllers\StudentEnrollmentController;
use App\Http\Controllers\StudentMapController;
use App\Http\Controllers\StudentPeriodConvalidationController;
use App\Http\Controllers\StudentReportCardController;
use App\Http\Controllers\TeacherDiaryController;
use App\Http\Controllers\TeacherScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('login');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
Route::get('/documentos/verificar', [ReportController::class, 'verifyForm'])->name('documents.verify.form');
Route::post('/documentos/verificar', [ReportController::class, 'lookup'])->name('documents.verify.lookup');
Route::get('/documentos/verificar/{code}', [ReportController::class, 'verify'])->name('documents.verify');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'profile.complete'])->name('dashboard');

Route::get('/meu-cadastro', [ProfileController::class, 'edit'])->middleware('auth')->name('profile.edit');
Route::put('/meu-cadastro', [ProfileController::class, 'update'])->middleware('auth')->name('profile.update');

Route::middleware(['auth', 'profile.complete'])->group(function (): void {
    Route::resource('escolas', SchoolController::class)
        ->parameters(['escolas' => 'school'])
        ->names('schools')
        ->except(['show']);
    Route::get('escolas/{school}/pdf', [RecordPdfController::class, 'school'])->name('schools.pdf');
    Route::get('escolas/{school}/conceitos', [SchoolConceptController::class, 'index'])->name('schools.concepts.index');
    Route::put('escolas/{school}/criterios-academicos', [SchoolConceptController::class, 'updateCriteria'])->name('schools.academic-criteria.update');
    Route::post('escolas/{school}/conceitos/padrao', [SchoolConceptController::class, 'storeDefault'])->name('schools.concepts.default');
    Route::post('escolas/{school}/conceitos', [SchoolConceptController::class, 'store'])->name('schools.concepts.store');
    Route::put('escolas/{school}/conceitos/{concept}', [SchoolConceptController::class, 'update'])->name('schools.concepts.update');
    Route::delete('escolas/{school}/conceitos/{concept}', [SchoolConceptController::class, 'destroy'])->name('schools.concepts.destroy');
    Route::get('escolas/{school}/anos-letivos', [SchoolAcademicYearController::class, 'index'])->name('schools.academic-years.index');
    Route::get('escolas/{school}/anos-letivos/create', [AcademicYearController::class, 'create'])->name('schools.academic-years.create');
    Route::post('escolas/{school}/anos-letivos', [AcademicYearController::class, 'store'])->name('schools.academic-years.store');

    Route::resource('pessoas', PersonController::class)
        ->parameters(['pessoas' => 'person'])
        ->names('people');
    Route::get('pessoas/{person}/pdf', [RecordPdfController::class, 'person'])->name('people.pdf');
    Route::post('pessoas/{person}/google-workspace', [GoogleWorkspaceController::class, 'store'])->name('people.google-workspace.store');
    Route::get('pessoas/{person}/vida-escolar', [StudentMapController::class, 'show'])->name('people.student-map.show');
    Route::get('pessoas/{person}/mapa-do-estudante', fn ($person) => redirect()->route('people.student-map.show', $person));
    Route::get('pessoas/{person}/historicos/create', [StudentAcademicHistoryController::class, 'create'])->name('people.histories.create');
    Route::post('pessoas/{person}/historicos', [StudentAcademicHistoryController::class, 'store'])->name('people.histories.store');
    Route::get('pessoas/{person}/historicos/{history}', [StudentAcademicHistoryController::class, 'show'])->name('people.histories.show');
    Route::get('pessoas/{person}/historicos/{history}/edit', [StudentAcademicHistoryController::class, 'edit'])->name('people.histories.edit');
    Route::put('pessoas/{person}/historicos/{history}', [StudentAcademicHistoryController::class, 'update'])->name('people.histories.update');
    Route::delete('pessoas/{person}/historicos/{history}', [StudentAcademicHistoryController::class, 'destroy'])->name('people.histories.destroy');
    Route::get('pessoas/{person}/historicos/{history}/pdf', [StudentAcademicHistoryController::class, 'pdf'])->name('people.histories.pdf');
    Route::post('pessoas/{person}/contatos', [PersonContactController::class, 'store'])->name('people.contacts.store');
    Route::put('pessoas/{person}/contatos/{contact}', [PersonContactController::class, 'update'])->name('people.contacts.update');
    Route::delete('pessoas/{person}/contatos/{contact}', [PersonContactController::class, 'destroy'])->name('people.contacts.destroy');

    Route::post('pessoas/{person}/vinculos', [PersonRoleController::class, 'store'])->name('people.roles.store');
    Route::patch('pessoas/{person}/vinculos/{role}/ativar', [PersonRoleController::class, 'activate'])->name('people.roles.activate');
    Route::patch('pessoas/{person}/vinculos/{role}/desativar', [PersonRoleController::class, 'deactivate'])->name('people.roles.deactivate');
    Route::put('pessoas/{person}/vinculos/{role}', [PersonRoleController::class, 'update'])->name('people.roles.update');
    Route::delete('pessoas/{person}/vinculos/{role}', [PersonRoleController::class, 'destroy'])->name('people.roles.destroy');

    Route::redirect('pendencias', 'conformidade');
    Route::get('conformidade', [DataQualityController::class, 'index'])->name('data-quality.index');
    Route::get('conformidade/pdf', [DataQualityController::class, 'pdf'])->name('data-quality.pdf');

    Route::get('documentos-oficiais', [OfficialDocumentController::class, 'create'])->name('official-documents.create');
    Route::post('documentos-oficiais/pdf', [OfficialDocumentController::class, 'store'])->name('official-documents.store');
    Route::get('emissao-de-documentos', [DocumentIssuanceController::class, 'index'])->name('document-issuance.index');
    Route::get('emissao-de-documentos/destinatarios', [DocumentIssuanceController::class, 'targets'])->name('document-issuance.targets');
    Route::get('emissao-de-documentos/emitir', [DocumentIssuanceController::class, 'issue'])->name('document-issuance.issue');

    Route::get('diarios', [TeacherDiaryController::class, 'index'])->name('teacher-diaries.index');
    Route::get('diarios/horario', [TeacherScheduleController::class, 'index'])->name('teacher-schedules.index');
    Route::get('diarios/horario/pdf', [TeacherScheduleController::class, 'pdf'])->name('teacher-schedules.pdf');
    Route::get('diarios/{schoolClass}/{component}', [TeacherDiaryController::class, 'show'])->name('teacher-diaries.show');
    Route::get('diarios/{schoolClass}/{component}/pdf', [TeacherDiaryController::class, 'print'])->name('teacher-diaries.pdf');
    Route::get('diarios/{schoolClass}/{component}/lista-chamada-pdf', [TeacherDiaryController::class, 'attendanceSheet'])->name('teacher-diaries.attendance-sheet.pdf');
    Route::put('diarios/{schoolClass}/{component}/notas', [TeacherDiaryController::class, 'updateGrades'])->name('teacher-diaries.grades.update');
    Route::get('diarios/{schoolClass}/{component}/frequencia', [TeacherDiaryController::class, 'attendance'])->name('teacher-diaries.attendance');
    Route::put('diarios/{schoolClass}/{component}/frequencia', [TeacherDiaryController::class, 'storeAttendanceBatch'])->name('teacher-diaries.attendance.batch-update');
    Route::get('diarios/{schoolClass}/{component}/conteudos', [TeacherDiaryController::class, 'contents'])->name('teacher-diaries.contents');
    Route::put('diarios/{schoolClass}/{component}/conteudos', [TeacherDiaryController::class, 'updateContents'])->name('teacher-diaries.contents.update');
    Route::post('diarios/{schoolClass}/{component}/confirmacao', [TeacherDiaryController::class, 'confirmPeriod'])->name('teacher-diaries.confirmation.confirm');
    Route::post('diarios/{schoolClass}/{component}/reabertura', [TeacherDiaryController::class, 'reopenPeriod'])->name('teacher-diaries.confirmation.reopen');
    Route::post('diarios/{schoolClass}/{component}/alertas', [TeacherDiaryController::class, 'storeAlert'])->name('teacher-diaries.alerts.store');
    Route::patch('diarios/alertas/{alert}/dispensar', [TeacherDiaryController::class, 'dismissAlert'])->name('teacher-diaries.alerts.dismiss');
    Route::get('meu-diario', [StudentDiaryController::class, 'index'])->name('student-diaries.index');
    Route::get('meu-diario/{enrollment}/horario', [StudentDiaryController::class, 'schedule'])->name('student-diaries.schedule');
    Route::get('meu-diario/{enrollment}/horario-pdf', [StudentDiaryController::class, 'schedulePdf'])->name('student-diaries.schedule-pdf');
    Route::get('meu-diario/{enrollment}/{component}', [StudentDiaryController::class, 'show'])->name('student-diaries.show');

    Route::resource('anos-letivos', AcademicYearController::class)
        ->parameters(['anos-letivos' => 'academicYear'])
        ->names('academic-years')
        ->except(['index', 'create', 'store']);
    Route::patch('anos-letivos/{academicYear}/aprovar', [AcademicYearController::class, 'approve'])->name('academic-years.approve');
    Route::patch('anos-letivos/{academicYear}/fechar', [AcademicYearController::class, 'close'])->name('academic-years.close');
    Route::patch('anos-letivos/{academicYear}/reabrir', [AcademicYearController::class, 'reopen'])->name('academic-years.reopen');
    Route::get('anos-letivos/{academicYear}/fechamento', AcademicYearClosureController::class)->name('academic-years.closure');
    Route::get('anos-letivos/{academicYear}/calendario-pdf', AcademicCalendarPdfController::class)->name('academic-years.calendar-pdf');
    Route::get('anos-letivos/{academicYear}/matrizes-pdf', AcademicMatricesPdfController::class)->name('academic-years.matrices-pdf');
    Route::get('anos-letivos/{academicYear}/horarios-pdf', [SchoolClassSchedulePdfController::class, 'academicYear'])->name('academic-years.schedules-pdf');
    Route::get('anos-letivos/{academicYear}/resultados-finais-pdf', AcademicYearFinalResultsPdfController::class)->name('academic-years.final-results.pdf');
    Route::get('anos-letivos/{academicYear}/periodos', [AcademicPeriodController::class, 'index'])->name('academic-years.periods.index');
    Route::post('anos-letivos/{academicYear}/periodos', [AcademicPeriodController::class, 'store'])->name('academic-years.periods.store');
    Route::put('anos-letivos/{academicYear}/periodos/{period}', [AcademicPeriodController::class, 'update'])->name('academic-years.periods.update');
    Route::post('anos-letivos/{academicYear}/periodos/{period}/consolidar-diarios', [AcademicPeriodController::class, 'consolidate'])->name('academic-years.periods.diaries.consolidate');
    Route::post('anos-letivos/{academicYear}/periodos/{period}/reabrir-diarios', [AcademicPeriodController::class, 'reopenConsolidation'])->name('academic-years.periods.diaries.reopen');
    Route::put('anos-letivos/{academicYear}/periodos/{period}/comportamento', [AcademicPeriodController::class, 'updateBehaviorGrades'])->name('academic-years.periods.behavior.update');
    Route::put('anos-letivos/{academicYear}/periodos/{period}/avaliacoes', [AcademicPeriodController::class, 'updateAssessmentRules'])->name('academic-years.periods.assessment-rules.update');
    Route::delete('anos-letivos/{academicYear}/periodos/{period}', [AcademicPeriodController::class, 'destroy'])->name('academic-years.periods.destroy');
    Route::post('anos-letivos/{academicYear}/dias', [CalendarDayController::class, 'store'])->name('academic-years.days.store');
    Route::delete('anos-letivos/{academicYear}/dias/{day}', [CalendarDayController::class, 'destroy'])->name('academic-years.days.destroy');
    Route::get('anos-letivos/{academicYear}/cursos/create', [AcademicCourseController::class, 'create'])->name('academic-years.courses.create');
    Route::post('anos-letivos/{academicYear}/cursos', [AcademicCourseController::class, 'store'])->name('academic-years.courses.store');
    Route::get('anos-letivos/{academicYear}/cursos/{course}/matriz-pdf', AcademicMatricesPdfController::class)->name('academic-years.courses.matrix-pdf');
    Route::post('anos-letivos/{academicYear}/cursos/{course}/duplicar', [AcademicCourseController::class, 'duplicate'])->name('academic-years.courses.duplicate');
    Route::get('anos-letivos/{academicYear}/cursos/{course}', [AcademicCourseController::class, 'show'])->name('academic-years.courses.show');
    Route::get('anos-letivos/{academicYear}/cursos/{course}/edit', [AcademicCourseController::class, 'edit'])->name('academic-years.courses.edit');
    Route::put('anos-letivos/{academicYear}/cursos/{course}', [AcademicCourseController::class, 'update'])->name('academic-years.courses.update');
    Route::delete('anos-letivos/{academicYear}/cursos/{course}', [AcademicCourseController::class, 'destroy'])->name('academic-years.courses.destroy');
    Route::post('anos-letivos/{academicYear}/cursos/{course}/componentes', [CurriculumComponentController::class, 'store'])->name('academic-years.courses.components.store');
    Route::get('anos-letivos/{academicYear}/cursos/{course}/componentes/{component}', [CurriculumComponentController::class, 'show'])->name('academic-years.courses.components.show');
    Route::put('anos-letivos/{academicYear}/cursos/{course}/componentes/{component}', [CurriculumComponentController::class, 'update'])->name('academic-years.courses.components.update');
    Route::delete('anos-letivos/{academicYear}/cursos/{course}/componentes/{component}', [CurriculumComponentController::class, 'destroy'])->name('academic-years.courses.components.destroy');
    Route::get('anos-letivos/{academicYear}/turmas/create', [SchoolClassController::class, 'create'])->name('academic-years.classes.create');
    Route::post('anos-letivos/{academicYear}/turmas', [SchoolClassController::class, 'store'])->name('academic-years.classes.store');
    Route::get('anos-letivos/{academicYear}/turmas/{class}', [SchoolClassController::class, 'show'])->name('academic-years.classes.show');
    Route::get('anos-letivos/{academicYear}/turmas/{class}/edit', [SchoolClassController::class, 'edit'])->name('academic-years.classes.edit');
    Route::put('anos-letivos/{academicYear}/turmas/{class}', [SchoolClassController::class, 'update'])->name('academic-years.classes.update');
    Route::delete('anos-letivos/{academicYear}/turmas/{class}', [SchoolClassController::class, 'destroy'])->name('academic-years.classes.destroy');
    Route::get('anos-letivos/{academicYear}/turmas/{class}/horarios', [SchoolClassScheduleController::class, 'index'])->name('academic-years.classes.schedules.index');
    Route::get('anos-letivos/{academicYear}/turmas/{class}/horarios-pdf', [SchoolClassSchedulePdfController::class, 'schoolClass'])->name('academic-years.classes.schedules.pdf');
    Route::post('anos-letivos/{academicYear}/turmas/{class}/horarios', [SchoolClassScheduleController::class, 'store'])->name('academic-years.classes.schedules.store');
    Route::delete('anos-letivos/{academicYear}/turmas/{class}/horarios/{schedule}', [SchoolClassScheduleController::class, 'destroy'])->name('academic-years.classes.schedules.destroy');
    Route::post('anos-letivos/{academicYear}/turmas/{class}/horarios/{schedule}/blocos', [SchoolClassScheduleController::class, 'storeSlot'])->name('academic-years.classes.schedules.slots.store');
    Route::put('anos-letivos/{academicYear}/turmas/{class}/horarios/{schedule}/blocos/{slot}', [SchoolClassScheduleController::class, 'updateSlot'])->name('academic-years.classes.schedules.slots.update');
    Route::delete('anos-letivos/{academicYear}/turmas/{class}/horarios/{schedule}/blocos/{slot}', [SchoolClassScheduleController::class, 'destroySlot'])->name('academic-years.classes.schedules.slots.destroy');
    Route::put('anos-letivos/{academicYear}/turmas/{class}/componentes/{classComponent}', [SchoolClassComponentController::class, 'update'])->name('academic-years.classes.components.update');
    Route::post('anos-letivos/{academicYear}/turmas/{class}/componentes/{classComponent}/substituicoes', [SchoolClassComponentController::class, 'storeSubstitution'])->name('academic-years.classes.components.substitutions.store');
    Route::delete('anos-letivos/{academicYear}/turmas/{class}/componentes/{classComponent}/substituicoes/{substitution}', [SchoolClassComponentController::class, 'destroySubstitution'])->name('academic-years.classes.components.substitutions.destroy');
    Route::get('matriculas', [StudentEnrollmentController::class, 'overview'])->name('enrollments.index');
    Route::get('matriculas/justificativas-de-ausencia', [AttendanceJustificationController::class, 'index'])->name('attendance-justifications.index');
    Route::post('matriculas/justificativas-de-ausencia', [AttendanceJustificationController::class, 'store'])->name('attendance-justifications.store');
    Route::delete('matriculas/justificativas-de-ausencia/{justification}', [AttendanceJustificationController::class, 'destroy'])->name('attendance-justifications.destroy');
    Route::get('turmas/{class}/matriculas', [StudentEnrollmentController::class, 'index'])->name('classes.enrollments.index');
    Route::post('turmas/{class}/matriculas', [StudentEnrollmentController::class, 'store'])->name('classes.enrollments.store');
    Route::post('turmas/{class}/resultados-finais', [StudentEnrollmentController::class, 'calculateFinalResults'])->name('classes.final-results.calculate');
    Route::get('turmas/{class}/resultados-finais-pdf', ClassFinalResultsPdfController::class)->name('classes.final-results.pdf');
    Route::get('turmas/{class}/boletins-pdf', [ClassAcademicDocumentsController::class, 'reportCards'])->name('classes.report-cards.pdf');
    Route::get('turmas/{class}/atestados-frequencia-pdf', [ClassAcademicDocumentsController::class, 'attendanceCertificates'])->name('classes.attendance-certificates.pdf');
    Route::get('turmas/{class}/espelho-de-notas-pdf', [ClassAcademicDocumentsController::class, 'gradeMirror'])->name('classes.grade-mirror.pdf');
    Route::patch('matriculas/{enrollment}/transferir', [StudentEnrollmentController::class, 'transfer'])->name('enrollments.transfer');
    Route::post('matriculas/{enrollment}/reclassificar', [StudentEnrollmentController::class, 'reclassify'])->name('enrollments.reclassify');
    Route::patch('matriculas/{enrollment}/cancelar', [StudentEnrollmentController::class, 'cancel'])->name('enrollments.cancel');
    Route::patch('matriculas/{enrollment}/desfazer-transferencia', [StudentEnrollmentController::class, 'restoreTransfer'])->name('enrollments.restore-transfer');
    Route::patch('matriculas/{enrollment}/desfazer-cancelamento', [StudentEnrollmentController::class, 'restoreCancellation'])->name('enrollments.restore-cancellation');
    Route::get('matriculas/{enrollment}/documentos', [StudentEnrollmentCertificateController::class, 'documents'])->name('enrollments.documents');
    Route::get('matriculas/{enrollment}/pdf', EnrollmentPdfController::class)->name('enrollments.pdf');
    Route::get('matriculas/{enrollment}/declaracao-matricula-pdf', [StudentEnrollmentCertificateController::class, 'enrollmentDeclaration'])->name('enrollments.enrollment-declaration.pdf');
    Route::get('matriculas/{enrollment}/declaracao-escolaridade-pdf', [StudentEnrollmentCertificateController::class, 'schoolingDeclaration'])->name('enrollments.schooling-declaration.pdf');
    Route::get('matriculas/{enrollment}/declaracao-conclusao-pdf', [StudentEnrollmentCertificateController::class, 'completionDeclaration'])->name('enrollments.completion-declaration.pdf');
    Route::get('matriculas/{enrollment}/atestado-frequencia-pdf', [StudentEnrollmentCertificateController::class, 'attendance'])->name('enrollments.attendance-certificate.pdf');
    Route::get('matriculas/{enrollment}/atestado-transferencia-pdf', [StudentEnrollmentCertificateController::class, 'transfer'])->name('enrollments.transfer-certificate.pdf');
    Route::get('matriculas/{enrollment}/boletim', [StudentReportCardController::class, 'show'])->name('enrollments.report-card.show');
    Route::get('matriculas/{enrollment}/boletim-pdf', [StudentReportCardController::class, 'pdf'])->name('enrollments.report-card.pdf');
    Route::get('matriculas/{enrollment}/ficha-individual-pdf', [StudentReportCardController::class, 'individualRecordPdf'])->name('enrollments.individual-record.pdf');
    Route::post('matriculas/{enrollment}/convalidacoes', [StudentPeriodConvalidationController::class, 'store'])->name('enrollments.convalidations.store');
    Route::delete('matriculas/{enrollment}/convalidacoes/{convalidation}', [StudentPeriodConvalidationController::class, 'destroy'])->name('enrollments.convalidations.destroy');

    Route::get('recados', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('recados', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('recados/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::get('auditoria', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::patch('auditoria/fuso-horario', [AuditLogController::class, 'updateTimezone'])->name('audit-logs.timezone.update');
    Route::get('auditoria/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    Route::get('relatorios/{type}/excel', [ReportController::class, 'excel'])->name('reports.excel');
    Route::get('relatorios/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
});

$logout = function () {
    auth()->logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
};

Route::post('/logout', $logout)->middleware('auth')->name('logout');
