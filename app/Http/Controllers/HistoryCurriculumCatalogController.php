<?php

namespace App\Http\Controllers;

use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Support\CurriculumAreaStage;
use App\Support\HistoryCurriculumCatalog;
use App\Support\HistoryCurriculumMerger;
use App\Support\HistoryCurriculumRenamer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HistoryCurriculumCatalogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('knowledge-areas.catalog', HistoryCurriculumCatalog::formOptions());
    }

    public function mergeForm(Request $request, HistoryCurriculumComponent $catalogComponent, HistoryCurriculumMerger $merger)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $catalogComponent->load(['area', 'matrixComponents.course.academicYear.school']);
        $candidates = HistoryCurriculumComponent::whereKeyNot($catalogComponent->id)
            ->where('education_stage', $catalogComponent->education_stage)
            ->with(['area', 'matrixComponents.course.academicYear.school'])->orderBy('name')->get()
            ->filter(fn ($candidate) => $merger->supportsMatrixDefinition($catalogComponent, $candidate)
                || $merger->supportsMatrixDefinition($candidate, $catalogComponent))
            ->sortBy(fn ($candidate) => \App\Support\CurriculumCatalog::alphabeticalKey($candidate->name), SORT_NATURAL)->values();

        return view('knowledge-areas.merge', ['catalogComponent' => $catalogComponent->load('area'), 'candidates' => $candidates]);
    }

    public function merge(Request $request, HistoryCurriculumComponent $catalogComponent, HistoryCurriculumMerger $merger)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['target_component_id' => ['required', 'integer'], 'keep' => ['nullable', Rule::in(['source', 'target'])]]);
        $keepSource = ($data['keep'] ?? 'target') === 'source';
        $kept = $merger->merge($keepSource ? (int) $data['target_component_id'] : $catalogComponent->id, $keepSource ? $catalogComponent->id : (int) $data['target_component_id'], $request->user());

        return redirect()->route('history-curriculum.index')->with('status', __('Componentes mesclados. Nomenclatura mantida: :name.', ['name' => $kept->name]));
    }

    public function usages(Request $request, HistoryCurriculumComponent $catalogComponent)
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('knowledge-areas.catalog-usages', ['catalogComponent' => $catalogComponent->load('area')]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        HistoryCurriculumComponent::create($this->data($request));

        return back()->with('status', __('Componente do catálogo cadastrado.'));
    }

    public function update(Request $request, HistoryCurriculumComponent $catalogComponent)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        abort_if($catalogComponent->isMatrixDerived(), 403, __('Componentes de matrizes devem ser alterados na matriz ou pela mesclagem de componentes.'));
        $data = $this->data($request, $catalogComponent);
        DB::transaction(fn () => $catalogComponent->update($data));

        return back()->with('status', __('Componente do catálogo atualizado.'));
    }

    public function rename(Request $request, HistoryCurriculumComponent $catalogComponent, HistoryCurriculumRenamer $renamer)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $renamer->rename($catalogComponent->id, $data['name'], $request->user());

        return back()->with('status', __('Nome atualizado no catálogo, nas matrizes e nos históricos vinculados.'));
    }

    public function destroy(Request $request, HistoryCurriculumComponent $catalogComponent)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        abort_if($catalogComponent->matrixComponents()->exists(), 403, __('Este componente está vinculado a matrizes. Revise os vínculos nas matrizes antes de excluí-lo do catálogo.'));
        if ($catalogComponent->historyComponents()->exists()) {
            throw ValidationException::withMessages(['catalog_component' => __('Este componente é utilizado em históricos. Os vínculos precisam ser preservados.')]);
        }
        $catalogComponent->delete();

        return back()->with('status', __('Componente removido do catálogo. Os históricos emitidos e cadastrados foram preservados.'));
    }

    private function data(Request $request, ?HistoryCurriculumComponent $component = null): array
    {
        $data = $request->validate([
            'education_stage' => ['required', Rule::in(['fundamental', 'medio', 'tecnico'])],
            'formation' => ['required', Rule::in(HistoryCurriculumCatalog::formations($request->input('education_stage')))],
            'knowledge_area_id' => ['required', 'integer', Rule::exists('knowledge_areas', 'id')],
            'name' => ['required', 'string', 'max:255', Rule::unique('history_curriculum_components')->where('education_stage', $request->input('education_stage'))->where('formation', $request->input('formation'))->where('knowledge_area_id', $request->input('knowledge_area_id'))->ignore($component?->id)],
        ]);
        if (HistoryCurriculumAlias::where('education_stage', $data['education_stage'])->where('formation', $data['formation'])
            ->where('knowledge_area_id', $data['knowledge_area_id'])->where('name', $data['name'])
            ->when($component, fn ($query) => $query->where('history_curriculum_component_id', '!=', $component->id))->exists()) {
            throw ValidationException::withMessages(['name' => __('Esta nomenclatura já foi incorporada a outro componente do catálogo.')]);
        }
        CurriculumAreaStage::validate(KnowledgeArea::findOrFail($data['knowledge_area_id'])->name, $data['education_stage'], 'knowledge_area_id');
        if ($component && $component->education_stage !== $data['education_stage']) {
            foreach ($component->historyComponents()->with(['history', 'records.year'])->get() as $usage) {
                foreach ($usage->records as $record) {
                    $stage = $usage->history->education_stage ?: CurriculumAreaStage::stageFromLabel($record->year?->stage);
                    if ($stage && $stage !== $data['education_stage']) {
                        throw ValidationException::withMessages(['education_stage' => __('A etapa não pode ser alterada porque o componente já é utilizado em históricos de outra etapa.')]);
                    }
                }
            }
        }
        $data['active'] = true;

        return $data;
    }
}
