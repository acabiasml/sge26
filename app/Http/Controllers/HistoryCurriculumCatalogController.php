<?php

namespace App\Http\Controllers;

use App\Models\HistoryCurriculumAlias;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Support\CurriculumAreaStage;
use App\Support\HistoryCurriculumCatalog;
use App\Support\HistoryCurriculumMerger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HistoryCurriculumCatalogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('knowledge-areas.catalog', HistoryCurriculumCatalog::formOptions());
    }

    public function mergeForm(Request $request, HistoryCurriculumComponent $catalogComponent)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $candidates = HistoryCurriculumComponent::where('education_stage', $catalogComponent->education_stage)
            ->where('formation', $catalogComponent->formation)->where('knowledge_area_id', $catalogComponent->knowledge_area_id)
            ->whereKeyNot($catalogComponent->id)->withCount('historyComponents')->orderBy('name')->get();

        return view('knowledge-areas.merge', ['catalogComponent' => $catalogComponent->load('area'), 'candidates' => $candidates]);
    }

    public function merge(Request $request, HistoryCurriculumComponent $catalogComponent, HistoryCurriculumMerger $merger)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['other_component_id' => ['required', 'integer'], 'keep' => ['required', Rule::in(['current', 'other'])]]);
        $keepId = $data['keep'] === 'current' ? $catalogComponent->id : (int) $data['other_component_id'];
        $kept = $merger->merge($catalogComponent->id, (int) $data['other_component_id'], $keepId, $request->user());

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
        $catalogComponent->update($this->data($request, $catalogComponent));

        return back()->with('status', __('Componente do catálogo atualizado.'));
    }

    public function destroy(Request $request, HistoryCurriculumComponent $catalogComponent)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        if ($catalogComponent->historyComponents()->exists()) {
            throw ValidationException::withMessages(['catalog_component' => __('Este componente é utilizado em históricos. Inative-o para preservar os vínculos.')]);
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
            'active' => ['required', 'boolean'],
        ]);
        if (HistoryCurriculumAlias::where('education_stage', $data['education_stage'])->where('formation', $data['formation'])
            ->where('knowledge_area_id', $data['knowledge_area_id'])->where('name', $data['name'])
            ->when($component, fn ($query) => $query->where('history_curriculum_component_id', '!=', $component->id))->exists()) {
            throw ValidationException::withMessages(['name' => __('Esta nomenclatura já foi incorporada a outro componente do catálogo.')]);
        }
        CurriculumAreaStage::validate(KnowledgeArea::findOrFail($data['knowledge_area_id'])->name, $data['education_stage'], 'knowledge_area_id');

        return $data;
    }
}
