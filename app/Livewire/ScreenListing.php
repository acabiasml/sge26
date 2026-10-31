<?php

namespace App\Livewire;

use App\Http\Controllers;
use App\Models\KnowledgeArea;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

    #[Url(as: 'f', except: [])]
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
        'historical-usages' => [Controllers\KnowledgeAreaController::class, 'historicalUsages', ['q', 'formation', 'area']],
        'justifications' => [Controllers\AttendanceJustificationController::class, 'index', ['q', 'school', 'year', 'class']],
        'diaries' => [Controllers\TeacherDiaryController::class, 'index', ['q', 'school', 'year', 'academic_year', 'period', 'class', 'component', 'teacher', 'status']],
        'quality' => [Controllers\DataQualityController::class, 'index', ['school_id', 'severity']],
        'enrollment-overview' => [Controllers\StudentEnrollmentController::class, 'overview', ['q']],
        'enrollments' => [Controllers\StudentEnrollmentController::class, 'index', ['q']],
        'announcements' => [Controllers\AnnouncementController::class, 'index', ['q']],
        'documents' => [Controllers\OfficialDocumentController::class, 'listing', ['q', 'visibility']],
        'student-diaries' => [Controllers\StudentDiaryController::class, 'index', ['q']],
        'teacher-schedules' => [Controllers\TeacherScheduleController::class, 'index', ['q']],
    ];

    public function mount(string $screen, array $context = []): void
    {
        abort_unless(isset(self::SOURCES[$screen]), 404);
        $this->screen = $screen;
        $this->context = $context;
        $allowed = self::SOURCES[$screen][2];
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
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
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
        $this->resetPage();
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
        $arguments = [$listingRequest];
        if ($this->screen === 'school-years') {
            $arguments[] = School::findOrFail($this->context['school'] ?? null);
        } elseif ($this->screen === 'area-usages') {
            $arguments[] = KnowledgeArea::findOrFail($this->context['area'] ?? null);
        } elseif ($this->screen === 'enrollments') {
            $arguments[] = SchoolClass::findOrFail($this->context['class'] ?? null);
        }
        $result = app($controller)->{$method}(...$arguments);
        $source = $result instanceof View ? $result : $result->getOriginalContent();
        $data = $source->getData();
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

        return view('livewire.listings.'.str_replace('.', '-', $source->name()), $data);
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
                $data['areas'] = $filter($data['areas'], ['name', 'formation']);
                $data['historicalAreas'] = $filter($data['historicalAreas'], ['knowledge_area', 'formation']);
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
