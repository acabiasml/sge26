<?php

namespace App\Console\Commands;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CalendarDay;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonRelationship;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolAssessmentRule;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Support\TextNormalizer;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import {--fresh : Remove dados trazidos das bases anteriores antes de importar}';

    protected $description = 'Importa escolas, pessoas, vínculos e responsáveis das três bases anteriores.';

    /**
     * @var array<string, string>
     */
    private array $sources = [
        'beaba' => 'storage/app/private/u810745753_beaba.sql',
        'lar' => 'storage/app/private/u810745753_lar.sql',
        'laura' => 'storage/app/private/u810745753_laura.sql',
    ];

    /**
     * @var array<string, array<int, int>>
     */
    private array $personIdMap = [];

    /**
     * @var array<string, int>
     */
    private array $schoolIdMap = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $academicYearIdMap = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $academicPeriodIdMap = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $courseIdMap = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $componentIdMap = [];

    /**
     * @var array<string, array<int, true>>
     */
    private array $activeLegacyUserIds = [];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->removePreviousImport();
        }

        Model::withoutEvents(function (): void {
            foreach ($this->sources as $source => $path) {
                $this->importSource($source, base_path($path));
            }
        });

        $this->info('Importação das bases anteriores concluída.');

        return self::SUCCESS;
    }

    private function removePreviousImport(): void
    {
        DB::transaction(function (): void {
            $academicYearIds = AcademicYear::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->pluck('id');

            $courseIds = AcademicCourse::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->pluck('id');

            $classIds = SchoolClass::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->pluck('id');

            $enrollmentIds = StudentEnrollment::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->pluck('id');

            DiaryAssessmentResult::query()
                ->whereHas('assessment', fn ($query) => $query->whereIn('legacy_source', array_keys($this->sources)))
                ->delete();

            DiaryAssessment::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            DiaryAttendanceEntry::query()
                ->whereHas('record', fn ($query) => $query->whereIn('legacy_source', array_keys($this->sources)))
                ->delete();

            DiaryAttendanceRecord::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            DiaryContent::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            Announcement::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            if ($enrollmentIds->isNotEmpty()) {
                DB::table('academic_course_student_enrollment')
                    ->whereIn('student_enrollment_id', $enrollmentIds)
                    ->delete();
            }

            StudentEnrollment::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            if ($courseIds->isNotEmpty()) {
                DB::table('academic_course_school_class')
                    ->whereIn('academic_course_id', $courseIds)
                    ->delete();
            }

            SchoolClassComponent::query()
                ->whereIn('school_class_id', $classIds)
                ->delete();

            SchoolClass::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            CurriculumComponent::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            AcademicCourse::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            AcademicPeriod::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            AcademicYear::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();

            $peopleIds = Person::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->whereDoesntHave('user')
                ->pluck('id');

            PersonRelationship::query()
                ->whereIn('person_id', $peopleIds)
                ->orWhereIn('related_person_id', $peopleIds)
                ->delete();

            PersonContact::query()
                ->whereIn('person_id', $peopleIds)
                ->orWhereIn('legacy_source', array_keys($this->sources))
                ->delete();

            PersonSchoolRole::query()
                ->whereIn('person_id', $peopleIds)
                ->delete();

            Person::query()
                ->whereIn('id', $peopleIds)
                ->delete();

            School::query()
                ->whereIn('legacy_source', array_keys($this->sources))
                ->delete();
        });
    }

    private function importSource(string $source, string $path): void
    {
        if (! is_file($path)) {
            $this->warn("Arquivo não encontrado: {$path}");

            return;
        }

        DB::transaction(function () use ($source, $path): void {
            $tables = $this->parseDump(file_get_contents($path) ?: '');
            $school = $this->importSchool($source, $tables['escolas'][0] ?? []);

            $this->schoolIdMap[$source] = $school->id;
            $this->personIdMap[$source] = [];
            $this->activeLegacyUserIds[$source] = $this->activeLegacyUserIds($tables);

            foreach ($tables['users'] ?? [] as $legacyUser) {
                $person = $this->importPerson($source, $legacyUser);
                $this->personIdMap[$source][(int) $legacyUser['id']] = $person->id;
                $this->importRoleFromType($person, $school, $legacyUser, $this->shouldActivateLegacyUser($source, $legacyUser));
            }

            $this->importSchoolManagementRoles($source, $school, $tables['escolas'][0] ?? []);
            $this->importTeacherRolesFor2026($source, $school, $tables);
            $this->importContacts($source, $tables['users'] ?? []);
            $this->importAcademicStructure($source, $school, $tables);
            $this->importAnnouncements($source, $school, $tables['avisos'] ?? []);
            $this->importLegacyDiaryContents($source, $tables['diarios'] ?? []);
            $this->importLegacyAttendance($source, $tables['frequencias'] ?? [], $tables['diarios'] ?? []);
            $this->importLegacyGrades($source, $tables['medias'] ?? []);

            $this->line("{$source}: ".count($tables['users'] ?? []).' pessoas processadas.');
        });
    }

    /**
     * @param  array<string, mixed>  $legacySchool
     */
    private function importSchool(string $source, array $legacySchool): School
    {
        $school = School::query()->firstOrNew([
            'legacy_source' => $source,
            'legacy_id' => (int) ($legacySchool['id'] ?? 0),
        ]);

        $school->fill([
            'name' => $this->title($legacySchool['nome'] ?? ucfirst($source)),
            'legal_name' => $this->title($legacySchool['razao'] ?? null),
            'cnpj' => $this->onlyDigits($legacySchool['cnpj'] ?? null),
            'founded_at' => $this->dateOrNull($legacySchool['fundacao'] ?? null),
            'phone' => $legacySchool['telefone'] ?? null,
            'email' => $this->validEmail($legacySchool['email'] ?? null),
            'website' => $this->urlOrNull($legacySchool['site'] ?? null),
            'letterhead_text' => $legacySchool['info'] ?? null,
            'address' => $this->title($legacySchool['endereco'] ?? null),
            'district' => $this->title($legacySchool['bairro'] ?? null),
            'number' => $legacySchool['numero'] ?? null,
            'city' => $this->title($legacySchool['cidade'] ?? null),
            'state' => $this->stateOrNull($legacySchool['estado'] ?? null),
            'postal_code' => $legacySchool['cep'] ?? null,
            'active' => true,
            'legacy_metadata' => [
                'diretor' => $legacySchool['diretor'] ?? null,
                'coordenador' => $legacySchool['coordenador'] ?? null,
                'secretario' => $legacySchool['secretario'] ?? null,
            ],
        ]);
        $school->save();

        return $school;
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     */
    private function importPerson(string $source, array $legacyUser): Person
    {
        $cpf = $this->validCpf($legacyUser['cpf'] ?? null);
        $legacyId = (int) ($legacyUser['id'] ?? 0);

        $person = Person::query()
            ->where('legacy_source', $source)
            ->where('legacy_id', $legacyId)
            ->first();

        if (! $person && $cpf) {
            $person = Person::query()->where('cpf', $cpf)->first();
        }

        if (! $person && $this->isAcabias($legacyUser)) {
            $person = Person::query()
                ->where('full_name', 'like', '%Acabias%')
                ->first();
        }

        $person ??= new Person;

        $email = $this->validEmail($legacyUser['email'] ?? null);
        $institutionalEmail = $email && str_ends_with(strtolower($email), '@ctjj.org') ? $email : null;
        $personalEmail = $email && ! $institutionalEmail ? $email : ($person->personal_email ?? null);

        $person->fill([
            'legacy_id' => $legacyId,
            'legacy_source' => $source,
            'legacy_code' => $legacyUser['codigo'] ?? null,
            'student_inep' => ($legacyUser['tipo'] ?? null) === 'estud' ? ($legacyUser['inep'] ?? null) : null,
            'full_name' => $this->title($legacyUser['nome'] ?? 'Pessoa sem nome '.$source.' #'.$legacyId),
            'social_name' => $this->title($legacyUser['nomesocial'] ?? null),
            'cpf' => $cpf,
            'birth_date' => $this->dateOrNull($legacyUser['nascimento'] ?? null),
            'birth_city' => $this->title($legacyUser['naturalidade'] ?? null),
            'birth_state' => $this->stateOrNull($legacyUser['naturaif'] ?? null),
            'nationality' => $this->title($legacyUser['nacionalidade'] ?? null),
            'mother_name' => $this->title($legacyUser['genitora'] ?? $person->mother_name),
            'father_name' => $this->title($legacyUser['genitor'] ?? $person->father_name),
            'institutional_email' => $institutionalEmail ?? $person->institutional_email,
            'personal_email' => $personalEmail,
            'phone' => $legacyUser['telefone1'] ?? $legacyUser['telefone2'] ?? null,
            'address' => $this->title($legacyUser['endereco'] ?? null),
            'number' => $legacyUser['endnumero'] ?? null,
            'district' => $this->title($legacyUser['endbairro'] ?? null),
            'city' => $this->title($legacyUser['endcidade'] ?? null),
            'state' => $this->stateOrNull($legacyUser['enduf'] ?? null),
            'postal_code' => $legacyUser['endcep'] ?? null,
            'address_complement' => $legacyUser['endcomplemento'] ?? null,
            'active' => false,
            'profile_completed_at' => null,
            'legacy_metadata' => [
                'tipo' => $legacyUser['tipo'] ?? null,
                'arquivado' => $legacyUser['arquivado'] ?? null,
                'sexo' => $legacyUser['sexo'] ?? null,
                'cor' => $legacyUser['cor'] ?? null,
                'gemeo' => $legacyUser['gemeo'] ?? null,
                'nacionalidade' => $legacyUser['nacionalidade'] ?? null,
                'naturalidade' => $this->title($legacyUser['naturalidade'] ?? null),
                'naturalidade_uf' => $legacyUser['naturaif'] ?? null,
                'identidade' => $legacyUser['identidade'] ?? null,
                'identidade_emissor' => $legacyUser['identemissor'] ?? null,
                'identidade_uf' => $legacyUser['identuf'] ?? null,
                'certidao' => $legacyUser['certidao'] ?? null,
                'cartao_sus' => $legacyUser['cartaosus'] ?? null,
                'nis' => $legacyUser['nis'] ?? null,
                'genitora' => $this->title($legacyUser['genitora'] ?? null),
                'genitor' => $this->title($legacyUser['genitor'] ?? null),
                'responsavel' => $this->title($legacyUser['responsavel'] ?? null),
                'responsavel_cpf' => $legacyUser['responcpf'] ?? null,
                'responsavel_telefone_1' => $legacyUser['respontel1'] ?? null,
                'responsavel_telefone_2' => $legacyUser['respontel2'] ?? null,
            ],
        ]);
        $person->save();

        return $person;
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     */
    private function importRoleFromType(Person $person, School $school, array $legacyUser, bool $active): void
    {
        $role = match ($legacyUser['tipo'] ?? null) {
            'admin' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'prof' => PersonSchoolRole::ROLE_TEACHER,
            'estud' => PersonSchoolRole::ROLE_STUDENT,
            'apoio' => PersonSchoolRole::ROLE_EMPLOYEE,
            default => null,
        };

        if (! $role) {
            return;
        }

        $schoolId = $role === PersonSchoolRole::ROLE_ADMINISTRATOR ? null : $school->id;

        PersonSchoolRole::query()->updateOrCreate(
            [
                'person_id' => $person->id,
                'school_id' => $schoolId,
                'role' => $role,
                'position' => null,
            ],
            [
                'active' => $role === PersonSchoolRole::ROLE_ADMINISTRATOR
                    ? $this->isAcabias($legacyUser)
                    : $active,
                'started_at' => null,
                'ended_at' => (
                    $role === PersonSchoolRole::ROLE_ADMINISTRATOR
                        ? $this->isAcabias($legacyUser)
                        : $active
                ) ? null : now()->toDateString(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $legacySchool
     */
    private function importSchoolManagementRoles(string $source, School $school, array $legacySchool): void
    {
        $positions = [
            'diretor' => PersonSchoolRole::POSITION_DIRECTOR,
            'coordenador' => PersonSchoolRole::POSITION_COORDINATOR,
            'secretario' => PersonSchoolRole::POSITION_SECRETARY,
        ];

        foreach ($positions as $legacyField => $position) {
            $legacyPersonId = (int) ($legacySchool[$legacyField] ?? 0);
            $personId = $this->personIdMap[$source][$legacyPersonId] ?? null;

            if (! $personId) {
                continue;
            }

            PersonSchoolRole::query()->updateOrCreate(
                [
                    'person_id' => $personId,
                    'school_id' => $school->id,
                    'role' => PersonSchoolRole::ROLE_MANAGER,
                    'position' => $position,
                ],
                [
                    'active' => true,
                    'started_at' => null,
                    'ended_at' => null,
                ]
            );
        }
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    private function importTeacherRolesFor2026(string $source, School $school, array $tables): void
    {
        foreach ($this->teacherLegacyUserIdsFor2026($tables) as $legacyPersonId) {
            $personId = $this->personIdMap[$source][$legacyPersonId] ?? null;

            if (! $personId) {
                continue;
            }

            PersonSchoolRole::query()->updateOrCreate(
                [
                    'person_id' => $personId,
                    'school_id' => $school->id,
                    'role' => PersonSchoolRole::ROLE_TEACHER,
                    'position' => null,
                ],
                [
                    'active' => true,
                    'started_at' => null,
                    'ended_at' => null,
                ]
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyUsers
     */
    private function importContacts(string $source, array $legacyUsers): void
    {
        foreach ($legacyUsers as $legacyUser) {
            $personId = $this->personIdMap[$source][(int) $legacyUser['id']] ?? null;

            if (! $personId || ($legacyUser['tipo'] ?? null) !== 'estud') {
                continue;
            }

            $this->importTextContact($source, $personId, $legacyUser['genitora'] ?? null, PersonContact::TYPE_MOTHER);
            $this->importTextContact($source, $personId, $legacyUser['genitor'] ?? null, PersonContact::TYPE_FATHER);
            $this->importTextContact(
                $source,
                $personId,
                $legacyUser['responsavel'] ?? null,
                PersonContact::TYPE_LEGAL_GUARDIAN,
                $legacyUser['responcpf'] ?? null,
                $legacyUser['respontel1'] ?? null,
                $legacyUser['respontel2'] ?? null
            );
        }
    }

    private function importTextContact(
        string $source,
        int $personId,
        ?string $name,
        string $type,
        ?string $cpf = null,
        ?string $phone = null,
        ?string $secondaryPhone = null
    ): void {
        $name = trim((string) $this->title($name));

        if ($name === '') {
            return;
        }

        PersonContact::query()->updateOrCreate(
            [
                'person_id' => $personId,
                'relationship_type' => $type,
                'name' => $name,
            ],
            [
                'cpf' => $this->validCpf($cpf),
                'phone' => $phone,
                'secondary_phone' => $secondaryPhone,
                'legal_guardian' => $type === PersonContact::TYPE_LEGAL_GUARDIAN,
                'emergency_contact' => $type === PersonContact::TYPE_LEGAL_GUARDIAN,
                'notes' => null,
                'legacy_source' => $source,
                'legacy_metadata' => [
                    'original_name' => $name,
                ],
            ]
        );
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    private function importAcademicStructure(string $source, School $school, array $tables): void
    {
        $this->academicYearIdMap[$source] = [];
        $this->academicPeriodIdMap[$source] = [];
        $this->courseIdMap[$source] = [];
        $this->componentIdMap[$source] = [];

        $areaMap = $this->importKnowledgeAreas($tables['areas'] ?? []);
        $this->importAcademicYears($source, $school, $tables['calendarios'] ?? []);
        $this->importAcademicPeriods($source, $tables['periodos'] ?? []);
        $this->generateImportedCalendarDays($source);
        $this->importAcademicCourses($source, $tables['cursos'] ?? []);
        $this->importCurriculumComponents($source, $areaMap, $tables['componentes'] ?? []);
        $this->refreshImportedCourseWorkloads($source);
        $this->importSchoolClassesAndAssignments($source, $tables['cursos'] ?? []);
        $this->importStudentEnrollments($source, $tables['turmas'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $legacyAreas
     * @return array<int, int>
     */
    private function importKnowledgeAreas(array $legacyAreas): array
    {
        $areaMap = [];

        foreach ($legacyAreas as $legacyArea) {
            $legacyId = (int) ($legacyArea['id'] ?? 0);
            $name = trim((string) ($legacyArea['nome'] ?? ''));

            if ($legacyId <= 0 || $name === '') {
                continue;
            }

            $area = KnowledgeArea::query()->firstOrCreate(
                ['name' => $this->title($name)],
                ['sort_order' => $legacyId * 10, 'active' => true]
            );

            $areaMap[$legacyId] = $area->id;
        }

        return $areaMap;
    }

    /**
     * @param  list<array<string, mixed>>  $legacyCalendars
     */
    private function importAcademicYears(string $source, School $school, array $legacyCalendars): void
    {
        foreach ($legacyCalendars as $legacyCalendar) {
            $legacyId = (int) ($legacyCalendar['id'] ?? 0);
            $referenceYear = (int) ($legacyCalendar['ano'] ?? 0);

            if ($legacyId <= 0 || $referenceYear <= 0) {
                continue;
            }

            $isOperationalYear = $referenceYear === 2026;

            $year = AcademicYear::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => $legacyId,
                ],
                [
                    'school_id' => $school->id,
                    'name' => $this->title($legacyCalendar['nome'] ?: (string) $referenceYear),
                    'reference_year' => $referenceYear,
                    'starts_at' => $referenceYear.'-01-01',
                    'ends_at' => $referenceYear.'-12-31',
                    'approved_at' => $isOperationalYear ? ($referenceYear - 1).'-12-31' : null,
                    'class_hour_minutes' => 50,
                    'minimum_school_days' => 200,
                    'passing_points' => 24,
                    'minimum_attendance_percentage' => 75,
                    'notes' => 'Revise datas, aprovação e regras antes de usar oficialmente.',
                    'active' => $isOperationalYear,
                    'legacy_metadata' => $legacyCalendar,
                ]
            );

            $this->academicYearIdMap[$source][$legacyId] = $year->id;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyPeriods
     */
    private function importAcademicPeriods(string $source, array $legacyPeriods): void
    {
        $positions = [];

        foreach ($legacyPeriods as $legacyPeriod) {
            $legacyId = (int) ($legacyPeriod['id'] ?? 0);
            $legacyCalendarId = (int) ($legacyPeriod['calendarios_id'] ?? 0);
            $academicYearId = $this->academicYearIdMap[$source][$legacyCalendarId] ?? null;
            $startsAt = $this->dateOrNull($legacyPeriod['inicio'] ?? null);
            $endsAt = $this->dateOrNull($legacyPeriod['fim'] ?? null);

            if ($legacyId <= 0 || ! $academicYearId || ! $startsAt || ! $endsAt) {
                continue;
            }

            $positions[$academicYearId] = ($positions[$academicYearId] ?? 0) + 1;

            $period = AcademicPeriod::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => $legacyId,
                ],
                [
                    'academic_year_id' => $academicYearId,
                    'name' => $this->title($legacyPeriod['nome'] ?: $positions[$academicYearId].'º Período'),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'ignore_saturdays' => true,
                    'ignore_sundays' => true,
                    'position' => $positions[$academicYearId],
                    'notes' => null,
                    'recovery_mode' => AcademicPeriod::RECOVERY_NONE,
                    'legacy_metadata' => $legacyPeriod,
                ]
            );

            $this->academicPeriodIdMap[$source][$legacyId] = $period->id;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyCourses
     */
    private function importAcademicCourses(string $source, array $legacyCourses): void
    {
        foreach ($legacyCourses as $legacyCourse) {
            $legacyId = (int) ($legacyCourse['id'] ?? 0);
            $legacyCalendarId = (int) ($legacyCourse['calendarios_id'] ?? 0);
            $academicYearId = $this->academicYearIdMap[$source][$legacyCalendarId] ?? null;

            if ($legacyId <= 0 || ! $academicYearId) {
                continue;
            }

            $name = trim((string) $this->title($legacyCourse['nome'] ?? 'Curso '.$legacyId));

            $course = AcademicCourse::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => $legacyId,
                ],
                [
                    'academic_year_id' => $academicYearId,
                    'starts_period_id' => null,
                    'ends_period_id' => null,
                    'name' => $name,
                    'stage' => $this->stageFromCourseName($name, $legacyCourse['modalidade'] ?? null),
                    'modality' => $this->modalityFromCourseName($name, $legacyCourse['modalidade'] ?? null),
                    'status' => 'curricular',
                    'class_hour_minutes' => 50,
                    'notes' => null,
                    'active' => true,
                    'legacy_metadata' => $legacyCourse,
                ]
            );

            $this->courseIdMap[$source][$legacyId] = $course->id;
        }
    }

    /**
     * @param  array<int, int>  $areaMap
     * @param  list<array<string, mixed>>  $legacyComponents
     */
    private function importCurriculumComponents(string $source, array $areaMap, array $legacyComponents): void
    {
        foreach ($legacyComponents as $legacyComponent) {
            $legacyId = (int) ($legacyComponent['id'] ?? 0);
            $legacyCourseId = (int) ($legacyComponent['cursos_id'] ?? 0);
            $courseId = $this->courseIdMap[$source][$legacyCourseId] ?? null;

            if ($legacyId <= 0 || ! $courseId) {
                continue;
            }

            $course = AcademicCourse::query()->find($courseId);
            $legacyWorkloadHours = $this->legacyWorkloadHours($legacyComponent['horas'] ?? null);
            $weeklyLessons = $course
                ? $this->weeklyLessonsFromLegacyWorkload($legacyWorkloadHours, (int) $course->class_hour_minutes)
                : null;

            $component = CurriculumComponent::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => $legacyId,
                ],
                [
                    'academic_course_id' => $courseId,
                    'knowledge_area_id' => $areaMap[(int) ($legacyComponent['area_id'] ?? 0)] ?? null,
                    'name' => $this->title($legacyComponent['nome'] ?? 'Componente '.$legacyId),
                    'weekly_lessons' => $weeklyLessons,
                    'workload_hours' => $weeklyLessons && $course
                        ? (int) round(($weeklyLessons * (int) $course->class_hour_minutes * 40) / 60)
                        : null,
                    'notes' => null,
                    'active' => true,
                    'legacy_metadata' => $legacyComponent,
                ]
            );

            $this->componentIdMap[$source][$legacyId] = $component->id;
        }
    }

    private function refreshImportedCourseWorkloads(string $source): void
    {
        AcademicCourse::query()
            ->where('legacy_source', $source)
            ->with('components')
            ->each(function (AcademicCourse $course): void {
                $course->forceFill([
                    'workload_hours' => (int) round($course->calculatedWorkloadHours()),
                ])->save();
            });
    }

    /**
     * @param  list<array<string, mixed>>  $legacyCourses
     */
    private function importSchoolClassesAndAssignments(string $source, array $legacyCourses): void
    {
        foreach ($legacyCourses as $legacyCourse) {
            $legacyCourseId = (int) ($legacyCourse['id'] ?? 0);
            $courseId = $this->courseIdMap[$source][$legacyCourseId] ?? null;

            if (! $courseId) {
                continue;
            }

            $course = AcademicCourse::query()->with('components')->find($courseId);

            if (! $course) {
                continue;
            }

            $class = SchoolClass::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => $legacyCourseId,
                ],
                [
                    'academic_year_id' => $course->academic_year_id,
                    'starts_period_id' => $this->periodIdForDate($source, $course->academic_year_id, $legacyCourse['inicio'] ?? null),
                    'ends_period_id' => $this->periodIdForDate($source, $course->academic_year_id, $legacyCourse['fim'] ?? null),
                    'name' => $course->name,
                    'shift' => null,
                    'notes' => null,
                    'active' => true,
                    'legacy_metadata' => $legacyCourse,
                ]
            );

            $class->courses()->syncWithoutDetaching([$course->id]);

            foreach ($course->components as $component) {
                $legacyTeacherId = (int) ($component->legacy_metadata['professor'] ?? 0);

                SchoolClassComponent::query()->updateOrCreate(
                    [
                        'school_class_id' => $class->id,
                        'curriculum_component_id' => $component->id,
                    ],
                    [
                        'teacher_person_id' => $this->personIdMap[$source][$legacyTeacherId] ?? null,
                        'active' => true,
                    ]
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyEnrollments
     */
    private function importStudentEnrollments(string $source, array $legacyEnrollments): void
    {
        foreach ($legacyEnrollments as $legacyEnrollment) {
            $legacyId = (int) ($legacyEnrollment['id'] ?? 0);
            $legacyCourseId = (int) ($legacyEnrollment['cursos_id'] ?? 0);
            $legacyPersonId = (int) ($legacyEnrollment['users_id'] ?? 0);
            $courseId = $this->courseIdMap[$source][$legacyCourseId] ?? null;
            $course = $courseId ? AcademicCourse::query()->find($courseId) : null;
            $class = $course ? SchoolClass::query()->where('legacy_source', $source)->where('legacy_id', $legacyCourseId)->first() : null;
            $personId = $this->personIdMap[$source][$legacyPersonId] ?? null;

            if ($legacyId <= 0 || ! $course || ! $class || ! $personId) {
                continue;
            }

            $status = $this->enrollmentStatus($legacyEnrollment['status'] ?? null);
            $enrollment = StudentEnrollment::query()
                ->where('legacy_source', $source)
                ->where('legacy_id', $legacyId)
                ->first()
                ?? StudentEnrollment::query()
                    ->where('school_class_id', $class->id)
                    ->where('person_id', $personId)
                    ->first()
                ?? new StudentEnrollment([
                    'legacy_source' => $source,
                    'legacy_id' => $legacyId,
                ]);

            $enrollment->fill([
                'school_class_id' => $class->id,
                'person_id' => $personId,
                'enrolled_by_person_id' => $this->personIdMap[$source][(int) ($legacyEnrollment['usermatricula'] ?? 0)] ?? null,
                'transferred_by_person_id' => $this->personIdMap[$source][(int) ($legacyEnrollment['usertransf'] ?? 0)] ?? null,
                'enrolled_at' => $this->dateOrNull($legacyEnrollment['datamatricula'] ?? null),
                'transferred_at' => $this->dateOrNull($legacyEnrollment['datatransf'] ?? null),
                'status' => $status,
                'type' => strtolower((string) ($legacyEnrollment['tipo'] ?? '')) === 'ouvinte'
                    ? StudentEnrollment::TYPE_LISTENER
                    : StudentEnrollment::TYPE_REGULAR,
                'notes' => null,
                'legacy_source' => $enrollment->legacy_source ?? $source,
                'legacy_id' => $enrollment->legacy_id ?? $legacyId,
                'legacy_metadata' => array_merge($enrollment->legacy_metadata ?? [], [
                    'latest_imported_row' => $legacyEnrollment,
                ]),
            ]);
            $enrollment->save();

            $enrollment->courses()->syncWithoutDetaching([$course->id]);
        }
    }

    private function generateImportedCalendarDays(string $source): void
    {
        $years = AcademicYear::query()
            ->with('periods')
            ->where('legacy_source', $source)
            ->get();

        foreach ($years as $year) {
            foreach (CarbonPeriod::create($year->starts_at, $year->ends_at) as $date) {
                $type = $date->isWeekend() ? CalendarDay::TYPE_WEEKEND : CalendarDay::TYPE_FINAL_VACATION;
                $counts = false;
                $title = null;

                $period = $year->periods->first(function (AcademicPeriod $period) use ($date): bool {
                    return $date->betweenIncluded($period->starts_at, $period->ends_at);
                });

                if ($period && ! $date->isWeekend()) {
                    $type = CalendarDay::TYPE_SCHOOL_DAY;
                    $counts = true;
                    $title = $period->name;
                }

                CalendarDay::query()->updateOrCreate(
                    [
                        'academic_year_id' => $year->id,
                        'date' => $date->toDateTimeString(),
                    ],
                    [
                        'type' => $type,
                        'counts_as_school_day' => $counts,
                        'title' => $title,
                        'description' => null,
                    ]
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyAnnouncements
     */
    private function importAnnouncements(string $source, School $school, array $legacyAnnouncements): void
    {
        foreach ($legacyAnnouncements as $legacyAnnouncement) {
            $legacyId = (int) ($legacyAnnouncement['id'] ?? 0);
            $date = $this->dateOrNull($legacyAnnouncement['dia'] ?? null);
            $body = trim((string) ($legacyAnnouncement['aviso'] ?? ''));

            if ($legacyId <= 0 || ! $date || $body === '') {
                continue;
            }

            Announcement::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => $legacyId,
                ],
                [
                    'school_id' => $school->id,
                    'created_by_user_id' => null,
                    'title' => 'Aviso',
                    'body' => $body,
                    'starts_at' => Carbon::parse($date)->startOfDay(),
                    'ends_at' => Carbon::parse($date)->endOfDay(),
                    'highlight' => false,
                    'active' => false,
                    'legacy_metadata' => $legacyAnnouncement,
                ]
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyDiaries
     */
    private function importLegacyDiaryContents(string $source, array $legacyDiaries): void
    {
        foreach ($legacyDiaries as $legacyDiary) {
            $legacyId = (int) ($legacyDiary['id'] ?? 0);
            $component = $this->componentFromLegacy($source, (int) ($legacyDiary['componentes_id'] ?? 0));
            $date = $this->dateOrNull($legacyDiary['data'] ?? null);
            $content = trim((string) ($legacyDiary['conteudo'] ?? ''));

            if ($legacyId <= 0 || ! $component || ! $date || $content === '') {
                continue;
            }

            $class = $this->classForComponent($source, $component);
            $period = $this->periodForComponentDate($component, $date);

            if (! $class || ! $period) {
                continue;
            }

            $assignment = SchoolClassComponent::query()
                ->where('school_class_id', $class->id)
                ->where('curriculum_component_id', $component->id)
                ->first();

            DiaryContent::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => $legacyId,
                ],
                [
                    'school_class_id' => $class->id,
                    'curriculum_component_id' => $component->id,
                    'academic_period_id' => $period->id,
                    'class_date' => $date,
                    'content' => $content,
                    'created_by_person_id' => $assignment?->teacher_person_id,
                    'updated_by_person_id' => $assignment?->teacher_person_id,
                    'legacy_metadata' => $legacyDiary,
                ]
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyAttendances
     * @param  list<array<string, mixed>>  $legacyDiaries
     */
    private function importLegacyAttendance(string $source, array $legacyAttendances, array $legacyDiaries): void
    {
        $diariesById = collect($legacyDiaries)->keyBy(fn (array $diary): int => (int) ($diary['id'] ?? 0));
        $absencesByDiaryId = collect($legacyAttendances)
            ->filter(fn (array $attendance): bool => (int) ($attendance['diarios_id'] ?? 0) > 0 && (int) ($attendance['users_id'] ?? 0) > 0)
            ->groupBy(fn (array $attendance): int => (int) ($attendance['diarios_id'] ?? 0));

        foreach ($diariesById as $legacyDiaryId => $legacyDiary) {
            $legacyDiaryId = (int) $legacyDiaryId;
            $legacyAbsences = $absencesByDiaryId->get($legacyDiaryId, collect());

            $component = $this->componentFromLegacy($source, (int) ($legacyDiary['componentes_id'] ?? 0));
            $date = $this->dateOrNull($legacyDiary['data'] ?? null);

            if (! $component || ! $date) {
                continue;
            }

            $class = $this->classForComponent($source, $component);
            $period = $this->periodForComponentDate($component, $date);

            if (! $class || ! $period) {
                continue;
            }

            $assignment = SchoolClassComponent::query()
                ->where('school_class_id', $class->id)
                ->where('curriculum_component_id', $component->id)
                ->first();

            $record = DiaryAttendanceRecord::query()
                ->where('school_class_id', $class->id)
                ->where('curriculum_component_id', $component->id)
                ->whereDate('class_date', $date)
                ->first()
                ?? new DiaryAttendanceRecord([
                    'legacy_source' => $source,
                    'legacy_id' => $legacyDiaryId,
                ]);

            $record->fill([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
                'teacher_person_id' => $assignment?->teacher_person_id,
                'updated_by_person_id' => $assignment?->teacher_person_id,
                'class_date' => $date,
                'lesson_count' => max(1, (int) ($legacyDiary['geminada'] ?? 1)),
                'notes' => null,
                'legacy_source' => $record->legacy_source ?? $source,
                'legacy_id' => $record->legacy_id ?? $legacyDiaryId,
                'legacy_metadata' => array_merge($record->legacy_metadata ?? [], [
                    'diary' => $legacyDiary,
                ]),
            ]);
            $record->save();

            $absentEnrollmentIds = $legacyAbsences
                ->map(fn (array $legacyAttendance): ?int => $this->enrollmentForLegacyUser($source, (int) ($legacyAttendance['users_id'] ?? 0), $class->id)?->id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $class->enrollments()
                ->where(function ($query) use ($date): void {
                    $query
                        ->whereNull('enrolled_at')
                        ->orWhereDate('enrolled_at', '<=', $date);
                })
                ->where(function ($query) use ($date): void {
                    $query
                        ->whereNull('transferred_at')
                        ->orWhereDate('transferred_at', '>=', $date);
                })
                ->where(function ($query) use ($date): void {
                    $query
                        ->whereNull('cancelled_at')
                        ->orWhereDate('cancelled_at', '>=', $date);
                })
                ->chunkById(100, function ($enrollments) use ($record, $absentEnrollmentIds): void {
                    foreach ($enrollments as $enrollment) {
                        $absent = in_array($enrollment->id, $absentEnrollmentIds, true);

                        DiaryAttendanceEntry::query()->updateOrCreate(
                            [
                                'diary_attendance_record_id' => $record->id,
                                'student_enrollment_id' => $enrollment->id,
                            ],
                            [
                                'status' => $absent ? DiaryAttendanceRecord::STATUS_ABSENT : DiaryAttendanceRecord::STATUS_PRESENT,
                                'attended_lessons' => $absent ? 0 : $record->lesson_count,
                                'lesson_presence' => array_fill(0, $record->lesson_count, ! $absent),
                            ]
                        );
                    }
                });
        }
    }

    /**
     * @param  list<array<string, mixed>>  $legacyGrades
     */
    private function importLegacyGrades(string $source, array $legacyGrades): void
    {
        foreach ($legacyGrades as $legacyGrade) {
            $legacyId = (int) ($legacyGrade['id'] ?? 0);
            $legacyPeriodId = (int) ($legacyGrade['periodos_id'] ?? 0);
            $component = $this->componentFromLegacy($source, (int) ($legacyGrade['componentes_id'] ?? 0));
            $periodId = $this->academicPeriodIdMap[$source][$legacyPeriodId] ?? null;
            $score = $this->numericScore($legacyGrade['nota'] ?? null);

            if ($legacyId <= 0 || ! $component || ! $periodId || $score === null) {
                continue;
            }

            $class = $this->classForComponent($source, $component);
            $enrollment = $class ? $this->enrollmentForLegacyUser($source, (int) ($legacyGrade['users_id'] ?? 0), $class->id) : null;

            if (! $class || ! $enrollment) {
                continue;
            }

            $assignment = SchoolClassComponent::query()
                ->where('school_class_id', $class->id)
                ->where('curriculum_component_id', $component->id)
                ->first();
            $period = AcademicPeriod::query()->with('academicYear')->find($periodId);
            $assessmentRule = $period ? $this->legacyPeriodAverageRule($period) : null;

            $assessment = DiaryAssessment::query()->updateOrCreate(
                [
                    'legacy_source' => $source,
                    'legacy_id' => (int) ($component->legacy_id ?? 0) * 100000 + $legacyPeriodId,
                ],
                [
                    'school_class_id' => $class->id,
                    'curriculum_component_id' => $component->id,
                    'academic_period_id' => $periodId,
                    'school_assessment_rule_id' => $assessmentRule?->id,
                    'is_recovery' => false,
                    'teacher_person_id' => $assignment?->teacher_person_id,
                    'title' => 'Média do período',
                    'weight' => 10,
                    'maximum_score' => 10,
                    'assessment_date' => null,
                    'notes' => null,
                    'legacy_metadata' => [
                        'source' => 'medias',
                        'component_legacy_id' => $component->legacy_id,
                        'legacy_period_id' => $legacyPeriodId,
                        'academic_period_id' => $periodId,
                    ],
                ]
            );

            DiaryAssessmentResult::query()->updateOrCreate(
                [
                    'diary_assessment_id' => $assessment->id,
                    'student_enrollment_id' => $enrollment->id,
                ],
                [
                    'updated_by_person_id' => $assignment?->teacher_person_id,
                    'score' => $score,
                    'notes' => null,
                ]
            );
        }
    }

    private function legacyPeriodAverageRule(AcademicPeriod $period): SchoolAssessmentRule
    {
        $period->loadMissing('academicYear');
        $schoolId = (int) $period->academicYear->school_id;

        $existingRule = SchoolAssessmentRule::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $period->id)
            ->where('name', 'Média do período')
            ->first();

        if ($existingRule) {
            return $existingRule;
        }

        $reusableRule = SchoolAssessmentRule::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $period->id)
            ->whereDoesntHave('assessments.results')
            ->orderBy('position')
            ->first();

        if ($reusableRule) {
            $reusableRule->update([
                'name' => 'Média do período',
                'weight' => 10,
                'maximum_score' => 10,
            ]);

            return $reusableRule;
        }

        $position = ((int) SchoolAssessmentRule::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $period->id)
            ->max('position')) + 1;

        return SchoolAssessmentRule::query()->create([
            'school_id' => $schoolId,
            'academic_period_id' => $period->id,
            'name' => 'Média do período',
            'position' => max(1, $position),
            'weight' => 10,
            'maximum_score' => 10,
        ]);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function parseDump(string $sql): array
    {
        preg_match_all('/INSERT INTO `([^`]+)` \((.*?)\) VALUES\s*(.*?);/s', $sql, $matches, PREG_SET_ORDER);

        $tables = [];

        foreach ($matches as $match) {
            $columns = array_map(fn (string $column): string => trim($column, " `\r\n\t"), explode(',', $match[2]));

            foreach ($this->splitRows(trim($match[3])) as $row) {
                $fields = str_getcsv($row, ',', "'", '\\');
                $record = [];

                foreach ($columns as $index => $column) {
                    $value = $fields[$index] ?? null;
                    $value = is_string($value) ? trim($value) : $value;
                    $record[$column] = ($value === '' || $value === 'NULL') ? null : $value;
                }

                $tables[$match[1]][] = $record;
            }
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private function splitRows(string $values): array
    {
        $rows = [];
        $depth = 0;
        $quote = false;
        $escape = false;
        $buffer = '';

        for ($i = 0, $length = strlen($values); $i < $length; $i++) {
            $char = $values[$i];

            if ($quote) {
                $buffer .= $char;

                if ($escape) {
                    $escape = false;
                } elseif ($char === '\\') {
                    $escape = true;
                } elseif ($char === "'") {
                    $quote = false;
                }

                continue;
            }

            if ($char === "'") {
                $quote = true;
                $buffer .= $char;

                continue;
            }

            if ($char === '(') {
                if ($depth > 0) {
                    $buffer .= $char;
                }

                $depth++;

                continue;
            }

            if ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $rows[] = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }

                continue;
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $rows;
    }

    private function validCpf(?string $value): ?string
    {
        $digits = $this->onlyDigits($value);

        return strlen($digits) === 11 ? $digits : null;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return array<int, true>
     */
    private function activeLegacyUserIds(array $tables): array
    {
        $calendar2026Ids = collect($tables['calendarios'] ?? [])
            ->filter(fn (array $calendar): bool => (int) ($calendar['ano'] ?? 0) === 2026)
            ->map(fn (array $calendar): int => (int) $calendar['id'])
            ->all();

        $course2026Ids = collect($tables['cursos'] ?? [])
            ->filter(fn (array $course): bool => in_array((int) ($course['calendarios_id'] ?? 0), $calendar2026Ids, true))
            ->map(fn (array $course): int => (int) $course['id'])
            ->all();

        $activeIds = [];

        foreach ($tables['turmas'] ?? [] as $enrollment) {
            if (! in_array((int) ($enrollment['cursos_id'] ?? 0), $course2026Ids, true)) {
                continue;
            }

            if (($enrollment['status'] ?? null) === 'transferido') {
                continue;
            }

            $activeIds[(int) $enrollment['users_id']] = true;
        }

        foreach ($this->teacherLegacyUserIdsFor2026($tables) as $teacherId) {
            $activeIds[$teacherId] = true;
        }

        foreach (($tables['escolas'][0] ?? []) as $field => $value) {
            if (! in_array($field, ['diretor', 'coordenador', 'secretario'], true)) {
                continue;
            }

            $managerId = (int) $value;

            if ($managerId > 0) {
                $activeIds[$managerId] = true;
            }
        }

        return $activeIds;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return list<int>
     */
    private function teacherLegacyUserIdsFor2026(array $tables): array
    {
        $calendar2026Ids = collect($tables['calendarios'] ?? [])
            ->filter(fn (array $calendar): bool => (int) ($calendar['ano'] ?? 0) === 2026)
            ->map(fn (array $calendar): int => (int) $calendar['id'])
            ->all();

        $course2026Ids = collect($tables['cursos'] ?? [])
            ->filter(fn (array $course): bool => in_array((int) ($course['calendarios_id'] ?? 0), $calendar2026Ids, true))
            ->map(fn (array $course): int => (int) $course['id'])
            ->all();

        return collect($tables['componentes'] ?? [])
            ->filter(fn (array $component): bool => in_array((int) ($component['cursos_id'] ?? 0), $course2026Ids, true))
            ->map(fn (array $component): int => (int) ($component['professor'] ?? 0))
            ->filter(fn (int $teacherId): bool => $teacherId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     */
    private function shouldActivateLegacyUser(string $source, array $legacyUser): bool
    {
        return $this->isAcabias($legacyUser)
            || isset($this->activeLegacyUserIds[$source][(int) ($legacyUser['id'] ?? 0)]);
    }

    private function periodIdForDate(string $source, int $academicYearId, ?string $date): ?int
    {
        $date = $this->dateOrNull($date);

        if (! $date) {
            return null;
        }

        return AcademicPeriod::query()
            ->where('academic_year_id', $academicYearId)
            ->where('legacy_source', $source)
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->value('id');
    }

    private function stageFromCourseName(string $name, ?string $modality): string
    {
        $text = Str::lower($name.' '.$modality);

        if (str_contains($text, 'técnico') || str_contains($text, 'tecnico') || str_contains($text, 'móveis') || str_contains($text, 'moveis')) {
            return AcademicCourse::STAGE_TECHNICAL;
        }

        if (str_contains($text, 'médio') || str_contains($text, 'medio') || preg_match('/\b[123]º?\s*ano\b/u', $text)) {
            return AcademicCourse::STAGE_HIGH_SCHOOL;
        }

        if (str_contains($text, 'fundamental') || preg_match('/\b[6-9]º?\s*ano\b/u', $text)) {
            return AcademicCourse::STAGE_ELEMENTARY;
        }

        return AcademicCourse::STAGE_OTHER;
    }

    private function modalityFromCourseName(string $name, ?string $modality): string
    {
        $text = Str::of($name.' '.$modality)->ascii()->lower()->toString();

        return match (true) {
            str_contains($text, 'eja') || str_contains($text, 'jovens e adultos') => AcademicCourse::MODALITY_EJA,
            str_contains($text, 'especial') => AcademicCourse::MODALITY_SPECIAL,
            str_contains($text, 'indigena') => AcademicCourse::MODALITY_INDIGENOUS,
            str_contains($text, 'quilombola') => AcademicCourse::MODALITY_QUILOMBOLA,
            str_contains($text, 'campo') => AcademicCourse::MODALITY_RURAL,
            str_contains($text, 'distancia') || str_contains($text, 'ead') => AcademicCourse::MODALITY_DISTANCE,
            str_contains($text, 'tecnico') || str_contains($text, 'profissional') || str_contains($text, 'tecnologica') || str_contains($text, 'moveis') => AcademicCourse::MODALITY_PROFESSIONAL_TECHNOLOGICAL,
            $text === '' || str_contains($text, 'regular') || str_contains($text, 'fundamental') || str_contains($text, 'medio') => AcademicCourse::MODALITY_REGULAR,
            default => AcademicCourse::MODALITY_OTHER,
        };
    }

    private function enrollmentStatus(?string $status): string
    {
        return match (Str::lower(trim((string) $status))) {
            'transferido', 'transferida' => StudentEnrollment::STATUS_TRANSFERRED,
            'reclassificado', 'reclassificada' => StudentEnrollment::STATUS_RECLASSIFIED,
            'cancelado', 'cancelada', 'desistente' => StudentEnrollment::STATUS_CANCELLED,
            default => StudentEnrollment::STATUS_ENROLLED,
        };
    }

    private function componentFromLegacy(string $source, int $legacyComponentId): ?CurriculumComponent
    {
        $componentId = $this->componentIdMap[$source][$legacyComponentId] ?? null;

        return $componentId ? CurriculumComponent::query()->with('course.academicYear.periods')->find($componentId) : null;
    }

    private function classForComponent(string $source, CurriculumComponent $component): ?SchoolClass
    {
        return SchoolClass::query()
            ->where('legacy_source', $source)
            ->where('legacy_id', $component->course?->legacy_id)
            ->first();
    }

    private function periodForComponentDate(CurriculumComponent $component, string $date): ?AcademicPeriod
    {
        $course = $component->course;

        if (! $course) {
            return null;
        }

        return $course->academicYear
            ?->periods
            ->first(fn (AcademicPeriod $period): bool => Carbon::parse($date)->betweenIncluded($period->starts_at, $period->ends_at));
    }

    private function enrollmentForLegacyUser(string $source, int $legacyUserId, int $schoolClassId): ?StudentEnrollment
    {
        $personId = $this->personIdMap[$source][$legacyUserId] ?? null;

        if (! $personId) {
            return null;
        }

        return StudentEnrollment::query()
            ->where('school_class_id', $schoolClassId)
            ->where('person_id', $personId)
            ->first();
    }

    private function numericScore(mixed $value): ?float
    {
        $value = str_replace(',', '.', trim((string) $value));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function legacyWorkloadHours(mixed $value): ?float
    {
        $value = str_replace(',', '.', trim((string) $value));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function weeklyLessonsFromLegacyWorkload(?float $workloadHours, int $classHourMinutes): ?int
    {
        if ($workloadHours === null || $workloadHours <= 0 || $classHourMinutes <= 0) {
            return null;
        }

        return max(1, (int) round(($workloadHours * 60) / ($classHourMinutes * 40)));
    }

    private function title(?string $value): ?string
    {
        return TextNormalizer::titleCase($value);
    }

    private function onlyDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function validEmail(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function dateOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function stateOrNull(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{2}$/', $value) ? $value : null;
    }

    private function urlOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            $value = 'https://'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     */
    private function isAcabias(array $legacyUser): bool
    {
        return str_contains(Str::lower($legacyUser['nome'] ?? ''), 'acabias');
    }
}
