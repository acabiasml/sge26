<?php

namespace App\Livewire;

use App\Http\Controllers;
use App\Models\AuditLog;
use App\Models\CredentialBook;
use App\Models\CredentialRegistration;
use App\Models\IssuedDocument;
use App\Models\KnowledgeArea;
use App\Models\School;
use App\Models\SchoolClass;
use App\Support\ListingInitialData;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\PaginationState;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only reactive lists; all access checks remain in their existing controllers. */
class ScreenListing extends Component
{
    use WithPagination;

    #[Locked]
    public string $screen;

    #[Locked]
    public array $context = [];

    public array $filters = [];

    #[Url(as: 'turma', except: '')]
    public string $diaryClassTab = '';

    #[Url(as: 'escola', except: '')]
    public string $enrollmentSchoolTab = '';

    #[Url(as: 'ano', except: '')]
    public string $enrollmentYearTab = '';

    protected $paginationTheme = 'bootstrap';

    private const SOURCES = [
        'histories' => [Controllers\StudentAcademicHistoryController::class, 'index', ['q', 'school']],
        'school-years' => [Controllers\SchoolAcademicYearController::class, 'index', ['q']],
        'knowledge-areas' => [Controllers\KnowledgeAreaController::class, 'index', ['q']],
        'area-usages' => [Controllers\KnowledgeAreaController::class, 'usages', ['q']],
        'justifications' => [Controllers\AttendanceJustificationController::class, 'index', ['q', 'school', 'year', 'class']],
        'diaries' => [Controllers\TeacherDiaryController::class, 'index', ['q', 'school', 'year', 'academic_year', 'period', 'class', 'component', 'teacher', 'status']],
        'quality' => [Controllers\DataQualityController::class, 'index', ['school_id', 'severity']],
        'enrollment-overview' => [Controllers\StudentEnrollmentController::class, 'overview', ['q']],
        'enrollments' => [Controllers\StudentEnrollmentController::class, 'index', ['q']],
        'announcements' => [Controllers\AnnouncementController::class, 'index', ['q']],
        'documents' => [Controllers\OfficialDocumentController::class, 'listing', ['q', 'visibility']],
        'student-diaries' => [Controllers\StudentDiaryController::class, 'index', ['q']],
        'teacher-schedules' => [Controllers\TeacherScheduleController::class, 'index', ['q']],
        'credential-books' => [Controllers\CredentialRegistryController::class, 'index', ['school_id', 'year']],
        'credential-book-entries' => [Controllers\CredentialRegistryController::class, 'bookEntries', ['q']],
        'credential-book-events' => [Controllers\CredentialRegistryController::class, 'bookEvents', []],
        'credential-entry-events' => [Controllers\CredentialRegistryController::class, 'entryEvents', []],
        'pending-deliveries' => [Controllers\InstitutionalSchoolRecordController::class, 'pendingDeliveries', []],
        'management-substitutions' => [Controllers\ManagementSubstitutionController::class, 'index', ['school_id']],
        'document-signatures' => [Controllers\DocumentSignatureController::class, 'index', []],
        'signature-versions' => [Controllers\DocumentSignatureController::class, 'show', []],
        'audit-failures' => [Controllers\UserOperationFailureController::class, 'index', ['q', 'category', 'school_id', 'from', 'to']],
        'audit-group' => [Controllers\AuditLogController::class, 'group', []],
        'courses' => [Controllers\TechnicalProgramController::class, 'index', ['school_id']],
        'issued-certificates' => [Controllers\CredentialArchiveController::class, 'batch', []],
        'teacher-access' => [Controllers\DashboardController::class, 'teacherAccess', ['docente', 'escola', 'acesso']],
    ];

    private const CONTEXT_MODELS = [
        'school-years' => ['school' => School::class],
        'area-usages' => ['area' => KnowledgeArea::class],
        'enrollments' => ['class' => SchoolClass::class],
        'credential-book-entries' => ['book' => CredentialBook::class],
        'credential-book-events' => ['book' => CredentialBook::class],
        'credential-entry-events' => ['entry' => CredentialRegistration::class],
        'pending-deliveries' => ['school' => School::class],
        'signature-versions' => ['document' => IssuedDocument::class],
        'audit-group' => ['auditLog' => AuditLog::class],
        'issued-certificates' => ['batch' => null],
    ];

    private const TEMPLATES = [
        'credential-book-events' => 'credential-registry-events',
        'credential-entry-events' => 'credential-registry-events',
        'pending-deliveries' => 'institutional-pending-deliveries',
        'signature-versions' => 'document-signature-versions',
        'teacher-access' => 'teacher-last-access',
    ];

    private const COLLECTION_PAGES = [
        'courses' => ['programs' => 'page', 'freeCourses' => 'free_courses_page'],
        'issued-certificates' => ['documents' => 'page'],
    ];

    private const QUERY_PAGES = [
        'credential-books' => 'page',
        'credential-book-entries' => 'page',
        'credential-book-events' => 'events_page',
        'credential-entry-events' => 'events_page',
        'pending-deliveries' => 'page',
        'management-substitutions' => 'page',
        'document-signatures' => 'page',
        'signature-versions' => 'page',
        'audit-failures' => 'page',
        'audit-group' => 'page',
        'teacher-access' => 'teacher_access_page',
    ];

    public function mount(string $screen, array $context = []): void
    {
        abort_unless(isset(self::SOURCES[$screen]), 404);
        $this->screen = $screen;
        $this->context = $context;
        $allowed = self::SOURCES[$screen][2];
        if ($allowed === []) {
            return;
        }
        if ($screen === 'teacher-access') {
            $this->filters = array_replace(array_fill_keys($allowed, ''), request()->only($allowed));
            foreach ($allowed as $key) {
                $this->setPropertyAttribute('filters.'.$key, new Url(as: $key, except: ''));
            }

            return;
        }
        $this->setPropertyAttribute('filters', new Url(as: 'f', except: []));
        $initial = request()->query('f', []);
        $this->filters = array_replace(array_fill_keys($allowed, ''), request()->only($allowed), is_array($initial) ? array_intersect_key($initial, array_flip($allowed)) : []);
    }

    public function updatedFilters(mixed $value, string $key): void
    {
        $dependents = match ($key) {
            'school' => ['year', 'academic_year', 'period', 'class', 'component', 'teacher'],
            'year', 'academic_year' => ['period', 'class', 'component', 'teacher'],
            default => [],
        };
        foreach ($dependents as $dependent) {
            if (array_key_exists($dependent, $this->filters)) {
                $this->filters[$dependent] = '';
            }
        }
        $this->applyFilters();
    }

    public function applyFilters(): void
    {
        $pageNames = self::COLLECTION_PAGES[$this->screen] ?? [self::QUERY_PAGES[$this->screen] ?? 'page'];
        foreach ($pageNames as $pageName) {
            $this->resetPage($pageName);
        }
    }

    #[On('diary-alerts-changed')]
    public function refreshDiaryAlerts(): void {}

    public function selectDiaryClass(int $id): void
    {
        abort_unless($this->screen === 'diaries', 404);
        $this->diaryClassTab = (string) $id;
    }

    public function clearFilters(): void
    {
        $this->diaryClassTab = '';
        $this->enrollmentSchoolTab = '';
        $this->enrollmentYearTab = '';
        // Historical-use identity and document visibility are context, not search terms.
        foreach ($this->filters as $key => $value) {
            if (! in_array($key, ['formation', 'area', 'visibility'], true)) {
                $this->filters[$key] = '';
            }
        }
        $this->applyFilters();
    }

    public function selectFilter(string $key, string $value): void
    {
        abort_unless(in_array($key, self::SOURCES[$this->screen][2], true), 422);
        $this->filters[$key] = $value;
        $this->updatedFilters($value, $key);
    }

    public function selectEnrollmentSchool(int $id): void
    {
        abort_unless($this->screen === 'enrollment-overview', 404);
        if ($this->enrollmentSchoolTab !== (string) $id) {
            $this->enrollmentSchoolTab = (string) $id;
            $this->enrollmentYearTab = '';
        }
    }

    public function selectEnrollmentYear(int $id): void
    {
        abort_unless($this->screen === 'enrollment-overview', 404);
        $this->enrollmentYearTab = (string) $id;
    }

    public function dehydrate(): void
    {
        // Livewire installs a paginator resolver bound to this instance.
        // Restore normal Laravel pagination for components and pages rendered after it.
        PaginationState::resolveUsing(app());
    }

    public function render(): View
    {
        abort_unless(auth()->check(), 403);
        abort_unless(isset(self::SOURCES[$this->screen]), 404);
        [$controller, $method, $allowed] = self::SOURCES[$this->screen];
        abort_if(array_diff(array_keys($this->filters), $allowed), 422);
        foreach ($this->filters as $value) {
            abort_unless(is_scalar($value) || $value === null, 422);
            abort_if(mb_strlen((string) $value) > 255, 422);
        }
        $query = array_filter($this->filters, fn ($value) => $value !== '' && $value !== null);
        $listingRequest = Request::create(url()->current(), 'GET', $query);
        $listingRequest->setUserResolver(fn () => auth()->user());
        $listingRequest->setLaravelSession(request()->hasSession() ? request()->session() : app('session.store'));
        if (isset(self::QUERY_PAGES[$this->screen])) {
            $this->getPage(self::QUERY_PAGES[$this->screen]);
        }
        $source = ListingInitialData::get($this->screen, $listingRequest, $allowed, $this->context);
        if (! $source) {
            $arguments = [$listingRequest];
            foreach (self::CONTEXT_MODELS[$this->screen] ?? [] as $key => $model) {
                $value = $this->context[$key] ?? abort(404);
                $arguments[] = $model ? $model::findOrFail($value) : $value;
            }
            $result = app($controller)->{$method}(...$arguments);
            $source = $result instanceof View ? $result : $result->getOriginalContent();
        }
        $data = $source->getData();
        foreach (self::COLLECTION_PAGES[$this->screen] ?? [] as $key => $pageName) {
            $page = $this->getPage($pageName);
            $rows = $data[$key];
            $data[$key] = new LengthAwarePaginator($rows->forPage($page, 10)->values(), $rows->count(), 10, $page, ['pageName' => $pageName]);
        }
        $data['listingRequest'] = $listingRequest;
        $data['listingScreen'] = $this->screen;
        if ($this->screen === 'diaries' && isset($data['groupedClasses'])) {
            // Os selects precisam refletir o contexto efetivo, inclusive o período padrão.
            $this->filters['academic_year'] = (string) ($data['academicYear']?->id ?? '');
            $this->filters['period'] = (string) ($data['period']?->id ?? '');
            $classes = $data['groupedClasses']->map(fn ($group): string => (string) $group['class']->id);
            if (! $classes->contains($this->diaryClassTab)) {
                $this->diaryClassTab = $classes->first() ?? '';
            }
        }
        if (isset($data['diaries'])) {
            $data['availableDiaries'] = $data['diaries'];
            $data['diaries'] = $data['diaries']->filter(fn ($diary) => (empty($this->filters['school']) || (string) $diary['academicYear']->school_id === (string) $this->filters['school'])
                && (empty($this->filters['academic_year']) || (string) $diary['academicYear']->id === (string) $this->filters['academic_year'])
                && (empty($this->filters['class']) || (string) $diary['class']->id === (string) $this->filters['class'])
            )->values();
        }
        $this->filterCollections($data);
        $collectionKey = match ($this->screen) {
            'knowledge-areas' => 'areas',
            'enrollment-overview' => 'schools',
            'student-diaries' => 'enrollments',
            default => null,
        };
        $rows = match ($this->screen) {
            'school-years' => $data['school']->academicYears,
            'enrollments' => $data['class']->enrollments->sortBy(fn ($enrollment) => $enrollment->student?->full_name)->values(),
            default => $collectionKey ? $data[$collectionKey] : null,
        };
        if ($rows instanceof Collection) {
            $page = $this->getPage();
            $pages = new LengthAwarePaginator($rows->forPage($page, 10)->values(), $rows->count(), 10, $page);
            $data['listingPages'] = $pages;
            $data['listingRows'] = $pages;
            if ($collectionKey) {
                $data[$collectionKey] = $pages;
            }
        }

        if ($this->screen === 'enrollment-overview') {
            $schools = $data['schools']->getCollection();
            $school = $schools->first(fn ($school) => (string) $school->id === $this->enrollmentSchoolTab)
                ?? $schools->first();
            $this->enrollmentSchoolTab = (string) ($school?->id ?? '');
            $years = $school?->academicYears->filter(fn ($year) => $year->classes->isNotEmpty()) ?? collect();
            if (! $years->contains(fn ($year) => (string) $year->id === $this->enrollmentYearTab)) {
                $this->enrollmentYearTab = (string) ($years->first()?->id ?? '');
            }
        }

        $results = $data['listingRows'] ?? match ($this->screen) {
            'documents' => $data['recentDocuments'],
            'announcements' => $data['announcements'],
            'justifications' => $data['justifications'],
            'histories' => $data['students'],
            'area-usages' => $data['components'],
            'teacher-schedules' => $data['slots'],
            default => null,
        };
        $data['listingResultCount'] = $results instanceof LengthAwarePaginator ? $results->total() : $results?->count();

        return view('livewire.listings.'.(self::TEMPLATES[$this->screen] ?? str_replace('.', '-', $source->name())), $data);
    }

    private function filterCollections(array &$data): void
    {
        $search = mb_strtolower(trim((string) ($this->filters['q'] ?? '')));
        if ($search === '') {
            return;
        }
        $matches = fn ($item, array $paths): bool => collect($paths)->contains(fn ($path) => str_contains(mb_strtolower((string) data_get($item, $path)), $search));
        $filter = fn (Collection $items, array $paths) => $items->filter(fn ($item) => $matches($item, $paths))->values();
        switch ($this->screen) {
            case 'school-years':
                $data['school']->setRelation('academicYears', $filter($data['school']->academicYears, ['name', 'reference_year']));
                break;
            case 'knowledge-areas':
                $data['areas'] = $filter($data['areas'], ['name', 'subtitle', 'formation']);
                break;
            case 'enrollments':
                $data['class']->setRelation('enrollments', $filter($data['class']->enrollments, ['student.full_name', 'student.cpf', 'enrollment_number']));
                break;
            case 'teacher-schedules':
                $data['slots'] = $filter($data['slots'], ['schedule.schoolClass.name', 'schedule.schoolClass.academicYear.school.name', 'componentAssignment.component.name']);
                break;
            case 'student-diaries':
                $data['enrollments'] = $data['enrollments']->filter(function ($enrollment) use ($matches, $search): bool {
                    return $matches($enrollment, ['schoolClass.name', 'schoolClass.academicYear.name', 'schoolClass.academicYear.school.name'])
                        || $enrollment->schoolClass->courses->contains(fn ($course) => $course->components->contains(fn ($component) => str_contains(mb_strtolower($component->name), $search)));
                })->values();
                break;
            case 'diaries':
                if (isset($data['diaries'])) {
                    $data['diaries'] = $filter($data['diaries'], ['component.name', 'class.name', 'academicYear.school.name']);
                }
                break;
            case 'enrollment-overview':
                $data['schools'] = $data['schools']->filter(function ($school) use ($filter, $matches): bool {
                    if ($matches($school, ['name'])) {
                        return true;
                    }
                    $years = $school->academicYears->filter(function ($year) use ($filter, $matches): bool {
                        if ($matches($year, ['name', 'reference_year'])) {
                            return true;
                        }
                        $year->setRelation('classes', $filter($year->classes, ['name']));

                        return $year->classes->isNotEmpty();
                    })->values();
                    $school->setRelation('academicYears', $years);

                    return $years->isNotEmpty();
                })->values();
                break;
        }
    }
}
