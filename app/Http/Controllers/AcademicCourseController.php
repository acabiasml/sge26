<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Support\AcademicStructureValidator;
use App\Support\CurriculumAreaStage;
use App\Support\CurriculumCatalog;
use App\Support\HistoryCurriculumCatalog;
use App\Support\MatrixTechnicalItinerary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicCourseController extends Controller
{
    public function create(Request $request, AcademicYear $academicYear): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-courses.create', [
            'academicYear' => $academicYear->load('school', 'periods'),
            'technicalOffers' => app(MatrixTechnicalItinerary::class)->offers($academicYear),
            'course' => new AcademicCourse([
                'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
                'modality' => AcademicCourse::MODALITY_REGULAR,
                'class_hour_minutes' => 50,
            ]),
        ]);
    }

    public function show(Request $request, AcademicYear $academicYear, AcademicCourse $course): View
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-courses.show', [
            'academicYear' => $academicYear->load('school', 'periods'),
            'course' => $course->load([
                'components.area',
                'components.startsPeriod',
                'components.endsPeriod',
                'classes.academicYear',
                'classes.courses',
                'classes.enrollments.student',
            ]),
            'technicalOffers' => app(MatrixTechnicalItinerary::class)->offers($academicYear, $course),
            'structureIssues' => AcademicStructureValidator::forCourse($course),
            'knowledgeAreas' => CurriculumCatalog::knowledgeAreasForCourse($course),
            'curriculumSuggestions' => CurriculumCatalog::suggestionsForCourse($course),
        ]);
    }

    public function edit(Request $request, AcademicYear $academicYear, AcademicCourse $course): View
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        return view('academic-courses.edit', [
            'academicYear' => $academicYear->load('school', 'periods'),
            'course' => $course,
            'technicalOffers' => app(MatrixTechnicalItinerary::class)->offers($academicYear, $course),
        ]);
    }

    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $this->validatedData($request, $academicYear);
        $technicalIds = $data['technical_itinerary_ids'];
        unset($data['technical_itinerary_ids']);
        $course = DB::transaction(function () use ($academicYear, $data, $technicalIds) {
            $course = $academicYear->courses()->create($data);
            $course->technicalItineraries()->sync($technicalIds);

            return $course;
        });

        return redirect()->route('academic-years.courses.show', [$academicYear, $course])
            ->with('status', __('Curso cadastrado com sucesso.'));
    }

    public function update(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $this->validatedData($request, $academicYear, $course);
        $technicalIds = $data['technical_itinerary_ids'];
        unset($data['technical_itinerary_ids']);
        DB::transaction(function () use ($course, $data, $technicalIds) {
            $course->update($data);
            $course->technicalItineraries()->sync($technicalIds);
        });

        return redirect()->route('academic-years.courses.show', [$academicYear, $course])
            ->with('status', __('Matriz atualizada com sucesso.'));
    }

    public function updateItinerary(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id && $course->stage === AcademicCourse::STAGE_HIGH_SCHOOL, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        // A identificação administrativa de uma oferta histórica não reabre notas ou diários.
        if (($academicYear->isReadOnly() || $academicYear->approved_at) && ! $request->user()->isAdministrator()) {
            throw ValidationException::withMessages(['itinerary_kind' => __('A identificação do itinerário de uma matriz aprovada ou encerrada requer a Administração.')]);
        }
        $data = $request->validate([
            'itinerary_kind' => ['required', Rule::in(['own', 'technical'])],
            'itinerary_name' => ['required_if:itinerary_kind,own', 'nullable', 'string', 'max:255'],
            'technical_itinerary_ids' => ['required_if:itinerary_kind,technical', 'nullable', 'array'],
            'technical_itinerary_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $ids = $data['itinerary_kind'] === 'technical' ? $data['technical_itinerary_ids'] : [];
        app(MatrixTechnicalItinerary::class)->validate($academicYear, $course, $ids);
        DB::transaction(function () use ($course, $data, $ids, $request, $academicYear): void {
            $old = $course->technicalItineraries()->pluck('academic_courses.id')->all();
            $course->update(['itinerary_name' => $data['itinerary_kind'] === 'own' ? $data['itinerary_name'] : null]);
            $course->technicalItineraries()->sync($ids);
            AuditLog::create(['actor_user_id' => $request->user()->id, 'actor_person_id' => $request->user()->person_id,
                'school_id' => $academicYear->school_id, 'auditable_type' => AcademicCourse::class, 'auditable_id' => $course->id,
                'action' => 'updated', 'old_values' => ['technical_itinerary_ids' => $old], 'new_values' => ['technical_itinerary_ids' => $ids],
                'metadata' => ['operation' => 'matrix-itinerary']]);
        });

        return redirect()->route('academic-years.courses.show', [$academicYear, $course, 'section' => 'itinerario'])
            ->with('status', __('Itinerário da matriz atualizado para todas as turmas vinculadas.'));
    }

    public function destroy(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $course->delete();

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', __('Curso removido com sucesso.'));
    }

    public function duplicate(Request $request, AcademicYear $academicYear, AcademicCourse $course): RedirectResponse
    {
        abort_unless($course->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $course->load('components');

        $duplicatedCourse = DB::transaction(function () use ($academicYear, $course): AcademicCourse {
            $duplicatedCourse = $academicYear->courses()->create([
                'name' => $this->nextDuplicateName($academicYear, $course->name),
                'itinerary_name' => $course->itinerary_name,
                'technical_legal_basis' => $course->technical_legal_basis,
                'accreditation_act' => $course->accreditation_act,
                'authorization_act' => $course->authorization_act,
                'regulatory_process' => $course->regulatory_process,
                'regulatory_opinion' => $course->regulatory_opinion,
                'technological_axis' => $course->technological_axis,
                'offer_forms' => $course->offer_forms,
                'official_gazette_reference' => $course->official_gazette_reference,
                'authorization_starts_at' => $course->authorization_starts_at,
                'authorization_ends_at' => $course->authorization_ends_at,
                'module_certifications' => $course->module_certifications,
                'stage' => $course->stage,
                'modality' => $course->modality,
                'status' => 'curricular',
                'workload_hours' => $course->workload_hours,
                'class_hour_minutes' => $course->class_hour_minutes,
                'notes' => $course->notes,
                'active' => true,
            ]);

            foreach ($course->components as $component) {
                $definition = $component->historyCatalogEntries()->first();
                if (! $definition) {
                    throw ValidationException::withMessages(['components' => __('Selecione um componente do catálogo com etapa, formação e área compatíveis.')]);
                }
                HistoryCurriculumCatalog::validateDefinition($definition, $duplicatedCourse->stage, 'components');
                $copy = $duplicatedCourse->components()->make([
                    'knowledge_area_id' => $component->knowledge_area_id,
                    'starts_period_id' => $component->starts_period_id,
                    'ends_period_id' => $component->ends_period_id,
                    'name' => $component->name,
                    'weekly_lessons' => $component->weekly_lessons,
                    'workload_hours' => $component->workload_hours,
                    'period_loads' => $component->period_loads,
                    'notes' => $component->notes,
                    'active' => $component->active,
                ]);
                $copy->adoptCatalogDefinition($definition);
            }

            $duplicatedCourse->technicalItineraries()->sync($course->technicalItineraries()->pluck('academic_courses.id'));
            $duplicatedCourse->refreshWorkloadHours();

            return $duplicatedCourse;
        });

        return redirect()->route('academic-years.courses.edit', [$academicYear, $duplicatedCourse])
            ->with('status', __('Matriz duplicada com sucesso. Ajuste o nome conforme necessário.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, AcademicYear $academicYear, ?AcademicCourse $course = null): array
    {
        if ($request->input('itinerary_kind') === 'own') {
            $request->merge(['technical_itinerary_ids' => []]);
        } elseif ($request->input('itinerary_kind') === 'technical') {
            $request->merge(['itinerary_name' => null]);
        }
        $request->merge([
            'modality' => $this->normalizeModalityInput($request->input('modality')),
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_courses')
                    ->where('academic_year_id', $academicYear->id)
                    ->ignore($course?->id),
            ],
            'stage' => ['required', Rule::in(array_keys(AcademicCourse::STAGE_LABELS))],
            'itinerary_name' => [Rule::requiredIf($request->input('stage') === AcademicCourse::STAGE_HIGH_SCHOOL && empty($request->input('technical_itinerary_ids', []))), 'nullable', 'string', 'max:255'],
            'technical_itinerary_ids' => ['nullable', 'array'],
            'technical_itinerary_ids.*' => ['integer', 'distinct', Rule::exists('academic_courses', 'id')->where('stage', AcademicCourse::STAGE_TECHNICAL)->whereIn('academic_year_id', AcademicYear::where('school_id', $academicYear->school_id)->select('id'))],
            'technical_legal_basis' => ['required_if:stage,'.AcademicCourse::STAGE_TECHNICAL, 'nullable', 'string', 'max:5000'],
            'accreditation_act' => ['required_if:stage,'.AcademicCourse::STAGE_TECHNICAL, 'nullable', 'string', 'max:5000'],
            'authorization_act' => ['required_if:stage,'.AcademicCourse::STAGE_TECHNICAL, 'nullable', 'string', 'max:5000'],
            'regulatory_process' => ['nullable', 'string', 'max:255'],
            'regulatory_opinion' => ['nullable', 'string', 'max:255'],
            'technological_axis' => ['nullable', 'string', 'max:255'],
            'offer_forms' => ['nullable', 'string', 'max:255'],
            'official_gazette_reference' => ['required_if:stage,'.AcademicCourse::STAGE_TECHNICAL, 'nullable', 'string', 'max:500'],
            'authorization_starts_at' => ['nullable', 'date'],
            'authorization_ends_at' => ['nullable', 'date', 'after_or_equal:authorization_starts_at'],
            'module_certifications' => ['nullable', 'string', 'max:5000'],
            'modality' => ['required', Rule::in(array_keys(AcademicCourse::MODALITY_LABELS))],
            'class_hour_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($course && $course->stage !== $data['stage']) {
            foreach ($course->components()->with('area')->get() as $component) {
                CurriculumAreaStage::validate($component->area?->name, $data['stage'], 'stage');
                $definition = $component->historyCatalogEntries()->first();
                if (! $definition) {
                    throw ValidationException::withMessages(['stage' => __('Selecione um componente do catálogo com etapa, formação e área compatíveis.')]);
                }
                HistoryCurriculumCatalog::validateDefinition($definition, $data['stage'], 'stage');
            }
        }

        $data['technical_itinerary_ids'] = $data['stage'] === AcademicCourse::STAGE_HIGH_SCHOOL ? ($data['technical_itinerary_ids'] ?? []) : [];
        app(MatrixTechnicalItinerary::class)->validate($academicYear, $course, $data['technical_itinerary_ids']);
        $data['status'] = 'curricular';
        $data['active'] = true;
        $data['workload_hours'] = $course?->calculatedWorkloadHours() ?? 0;

        if ($data['stage'] !== AcademicCourse::STAGE_TECHNICAL) {
            foreach (['technical_legal_basis', 'accreditation_act', 'authorization_act', 'regulatory_process', 'regulatory_opinion', 'technological_axis', 'offer_forms', 'official_gazette_reference', 'authorization_starts_at', 'authorization_ends_at', 'module_certifications'] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function normalizeModalityInput(mixed $value): string
    {
        $value = trim((string) $value);

        if (array_key_exists($value, AcademicCourse::MODALITY_LABELS)) {
            return $value;
        }

        $text = Str::of($value)->ascii()->lower()->toString();

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

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if ($academicYear->isReadOnly()) {
            throw ValidationException::withMessages([
                'closed_at' => __('Este ano letivo está fechado. Reabra o ano letivo antes de alterar matrizes.'),
            ]);
        }

        if (! $academicYear->approved_at || $request->user()->isAdministrator()) {
            return;
        }

        throw ValidationException::withMessages([
            'approved_at' => __('Ano letivo aprovado só pode ter sua estrutura acadêmica alterada pela Administração global.'),
        ]);
    }

    private function nextDuplicateName(AcademicYear $academicYear, string $name): string
    {
        $baseName = 'Cópia de '.$name;

        if (! $academicYear->courses()->where('name', $baseName)->exists()) {
            return $baseName;
        }

        $counter = 2;

        do {
            $candidate = $baseName.' '.$counter;
            $counter++;
        } while ($academicYear->courses()->where('name', $candidate)->exists());

        return $candidate;
    }
}
