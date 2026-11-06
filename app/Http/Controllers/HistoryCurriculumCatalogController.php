<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\HistoryCurriculumComponent;
use App\Models\KnowledgeArea;
use App\Support\CurriculumAreaStage;
use App\Support\HistoryCurriculumCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HistoryCurriculumCatalogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'education_stage' => ['nullable', Rule::in(['fundamental', 'medio', 'tecnico'])]]);
        $components = HistoryCurriculumComponent::with('area')
            ->when($filters['education_stage'] ?? null, fn ($query, $stage) => $query->where('education_stage', $stage))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhereHas('area', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))))
            ->orderBy('name')->paginate(10)->withQueryString();
        $areas = KnowledgeArea::where('active', true)->orderBy('name')->get();
        $stages = array_intersect_key(AcademicCourse::STAGE_LABELS, array_flip(['fundamental', 'medio', 'tecnico']));
        $config = [];
        foreach ($stages as $stage => $label) {
            $config[$stage] = ['formations' => HistoryCurriculumCatalog::formations($stage), 'areas' => $areas->filter(fn ($area) => CurriculumAreaStage::compatible($area->name, $stage))->map->only(['id', 'name'])->values()];
        }

        $formationLabels = collect(['Formação Geral Básica', 'Parte Complementar', 'Parte Diversificada', 'Itinerário Formativo', 'Formação Técnica Profissional'])->mapWithKeys(fn ($value) => [$value => __($value)])->all();

        return view('knowledge-areas.catalog', compact('components', 'stages', 'config', 'filters', 'formationLabels'));
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
        CurriculumAreaStage::validate(KnowledgeArea::findOrFail($data['knowledge_area_id'])->name, $data['education_stage'], 'knowledge_area_id');

        return $data;
    }
}
