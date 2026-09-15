<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArea;
use App\Support\CurriculumCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeAreaController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('knowledge-areas.index', [
            'areas' => KnowledgeArea::query()->orderBy('sort_order')->orderBy('name')->get(),
            'formations' => [CurriculumCatalog::FORMATION_FGB, CurriculumCatalog::FORMATION_ITINERARY, CurriculumCatalog::FORMATION_COMPLEMENTARY],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        KnowledgeArea::query()->create($this->validated($request));

        return back()->with('status', __('Área cadastrada com sucesso.'));
    }

    public function update(Request $request, KnowledgeArea $area)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $area->update($this->validated($request, $area));

        return back()->with('status', __('Área atualizada com sucesso.'));
    }

    private function validated(Request $request, ?KnowledgeArea $area = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('knowledge_areas')->ignore($area?->id)],
            'formation' => ['required', Rule::in([CurriculumCatalog::FORMATION_FGB, CurriculumCatalog::FORMATION_ITINERARY, CurriculumCatalog::FORMATION_COMPLEMENTARY])],
        ]);
    }
}
