<?php

namespace App\Http\Controllers;

use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Models\School;
use App\Models\TechnicalProgram;
use App\Support\HistoryCurriculumCatalog;
use App\Support\TechnicalProgramMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TechnicalProgramController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $ids = $request->user()->isAdministrator() ? School::pluck('id')->all() : $request->user()->manageableSchoolIds();

        $data = $request->validate(['school_id' => ['nullable', 'integer', 'exists:schools,id']]);
        $selectedSchool = filled($data['school_id'] ?? null) ? School::findOrFail($data['school_id']) : null;
        if ($selectedSchool) {
            abort_unless($request->user()->canManageSchool($selectedSchool->id), 403);
        }

        return view('technical-programs.index', [
            'programs' => TechnicalProgram::with('school')->whereIn('school_id', $ids)
                ->when($selectedSchool, fn ($query) => $query->where('school_id', $selectedSchool->id))->orderBy('name')->get(),
            'schools' => School::whereIn('id', $ids)->orderBy('name')->get(), 'selectedSchool' => $selectedSchool,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $program = new TechnicalProgram(['school_id' => $request->integer('school_id') ?: null, 'active' => true,
            'modules' => [], 'education_mode' => 'Presencial', 'offer_modes' => ['concomitante', 'subsequente']]);

        return $this->form($request, $program);
    }

    public function edit(Request $request, TechnicalProgram $program)
    {
        abort_unless($request->user()->canManageSchool($program->school_id), 403);

        return $this->form($request, $program);
    }

    private function form(Request $request, TechnicalProgram $program)
    {
        if ($program->school_id) {
            abort_unless($request->user()->canManageSchool($program->school_id), 403);
        }
        $ids = $request->user()->isAdministrator() ? School::pluck('id')->all() : $request->user()->manageableSchoolIds();

        return view('technical-programs.form', ['program' => $program, 'schools' => School::whereIn('id', $ids)->orderBy('name')->get(),
            'areas' => KnowledgeArea::where('active', true)->orderBy('name')->get(),
            'catalog' => HistoryCurriculumComponent::with('area')->where('education_stage', 'tecnico')->where('formation', 'Formação Técnica Profissional')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new TechnicalProgram);
    }

    public function update(Request $request, TechnicalProgram $program)
    {
        abort_unless($request->user()->canManageSchool($program->school_id), 403);

        return $this->save($request, $program);
    }

    private function save(Request $request, TechnicalProgram $program)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $rules = ['school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('technical_programs')->where('school_id', $request->integer('school_id'))->ignore($program->id)],
            'graduate_title' => ['required', 'string', 'max:255'], 'knowledge_area_id' => ['nullable', 'integer', 'exists:knowledge_areas,id'],
            'offer_modes' => ['required', 'array', 'min:1'], 'offer_modes.*' => ['required', Rule::in(array_keys(TechnicalProgram::OFFER_MODES))],
            'active' => ['nullable', 'boolean'], 'modules' => ['nullable', 'array', 'max:12'],
            'modules.*.key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'modules.*.name' => ['required', 'string', 'max:255'], 'modules.*.qualification' => ['nullable', 'string', 'max:255'],
            'modules.*.components' => ['nullable', 'array', 'max:50'],
            'modules.*.components.*.key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'modules.*.components.*.catalog_component_id' => ['required', 'integer', 'exists:history_curriculum_components,id'],
            'modules.*.components.*.hours' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'authorization_starts_at' => ['nullable', 'date'], 'authorization_ends_at' => ['nullable', 'date', 'after_or_equal:authorization_starts_at']];
        foreach (['authorization_act', 'legal_basis', 'registry_resolution', 'regulatory_process', 'regulatory_opinion', 'official_gazette_reference', 'source_project'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:2000'];
        }
        $rules['technological_axis'] = ['nullable', 'string', 'max:255'];
        $data = $request->validate($rules);
        if ($program->exists && ! array_key_exists('knowledge_area_id', $data)) {
            $data['knowledge_area_id'] = $program->knowledge_area_id;
        }
        abort_unless($request->user()->canManageSchool($data['school_id']), 403);
        if ($program->exists && (int) $program->school_id !== (int) $data['school_id']) {
            throw ValidationException::withMessages(['school_id' => 'O curso não pode ser movido para outra escola.']);
        }
        if (! $program->exists && ! empty($data['modules'])) {
            throw ValidationException::withMessages(['modules' => 'Salve primeiro os dados do curso técnico. Depois vincule os componentes já cadastrados.']);
        }
        $modules = [];
        $submittedModules = $request->has('modules') || $request->boolean('modules_submitted');
        $moduleInput = $submittedModules ? ($data['modules'] ?? []) : ($program->exists ? $program->modules : []);
        foreach (array_values($moduleInput) as $i => $module) {
            $items = [];
            foreach ($module['components'] ?? [] as $j => $component) {
                $entry = HistoryCurriculumComponent::with('area')->findOrFail($component['catalog_component_id']);
                HistoryCurriculumCatalog::validateDefinition($entry, 'tecnico', "modules.$i.components.$j.catalog_component_id");
                if (! ($data['knowledge_area_id'] ?? null) || $entry->knowledge_area_id !== (int) $data['knowledge_area_id']) {
                    throw ValidationException::withMessages(["modules.$i.components.$j.catalog_component_id" => 'Escolha um componente já cadastrado na área deste curso técnico.']);
                }
                $items[] = ['key' => ($component['key'] ?? null) ?: 'component-'.Str::uuid(), 'catalog_component_id' => $entry->id, 'name' => $entry->name, 'hours' => (float) $component['hours']];
            }
            $modules[] = ['key' => ($module['key'] ?? null) ?: 'module-'.Str::uuid(), 'position' => $i + 1, 'name' => trim($module['name']), 'qualification' => $module['qualification'] ?? null, 'components' => $items];
        }
        $keys = collect($modules)->pluck('key')->concat(collect($modules)->flatMap(fn ($m) => collect($m['components'])->pluck('key')));
        if ($keys->unique()->count() !== $keys->count()) {
            throw ValidationException::withMessages(['modules' => 'Cada módulo e vínculo de componente deve ter uma identificação própria.']);
        }
        $data['modules'] = $modules;
        $data['offer_modes'] = array_values(array_unique($data['offer_modes']));
        $data['active'] = $request->boolean('active');
        if (! $program->exists) {
            $data['education_mode'] = 'Presencial';
        }
        DB::transaction(function () use ($program, $data): void {
            if ($program->exists) {
                TechnicalProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            }
            $changed = $program->exists && $program->modules != $data['modules'];
            if ($changed) {
                foreach ($program->matrices()->with('academicYear')->get() as $matrix) {
                    if ($matrix->academicYear->isReadOnly() || ($matrix->academicYear->approved_at && ! auth()->user()->isAdministrator())) {
                        throw ValidationException::withMessages(['modules' => 'Há matrizes em anos fechados ou aprovados. Reabra os anos ou solicite à Administração antes de alterar módulos e cargas.']);
                    }
                }
            }
            $program->fill($data)->save();
            $program->unsetRelation('area');
            if ($changed) {
                app(TechnicalProgramMatrix::class)->synchronize($program);
            }
        });

        return redirect()->route('technical-programs.edit', $program)->with('status', 'Dados do curso técnico salvos. Os componentes são vinculados na seção Módulos e componentes.');
    }
}
