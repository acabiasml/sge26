<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArea;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\CurriculumComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\CurriculumCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeAreaController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('knowledge-areas.index', [
            'historicalAreas' => StudentAcademicHistoryComponent::query()->selectRaw('formation, knowledge_area, COUNT(*) as uses_count')->groupBy('formation', 'knowledge_area')->orderBy('formation')->orderBy('knowledge_area')->get(),
            'areas' => KnowledgeArea::query()->withCount('components')->orderBy('sort_order')->orderBy('name')->get(),
            'formations' => [CurriculumCatalog::FORMATION_FGB, CurriculumCatalog::FORMATION_ITINERARY, CurriculumCatalog::FORMATION_COMPLEMENTARY],
        ]);
    }

    public function historicalUsages(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $filters = $request->validate(['formation' => ['nullable', 'string', 'max:255'], 'area' => ['nullable', 'string', 'max:255']]);

        return view('knowledge-areas.historical-usages', [
            'components' => StudentAcademicHistoryComponent::query()
                ->where('formation', $filters['formation'] ?? null)
                ->where('knowledge_area', $filters['area'] ?? null)
                ->with(['history.student', 'history.school'])->orderBy('student_academic_history_id')->orderBy('name')
                ->paginate(25)->withQueryString(),
            'formation' => $filters['formation'] ?? '—',
            'area' => $filters['area'] ?? '—',
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

    public function usages(Request $request, KnowledgeArea $area)
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('knowledge-areas.usages', [
            'area' => $area->loadCount('components'),
            'components' => $area->components()->with('course.academicYear.school')->orderBy('name')->paginate(25),
        ]);
    }

    public function detach(Request $request, KnowledgeArea $area, CurriculumComponent $component)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        DB::transaction(function () use ($area, $component): void {
            $area->newQuery()->whereKey($area->id)->lockForUpdate()->firstOrFail();
            $component = $component->newQuery()->whereKey($component->id)->lockForUpdate()->firstOrFail();
            abort_unless($component->knowledge_area_id === $area->id, 404);
            $component->update(['knowledge_area_id' => null]);
        });

        return back()->with('status', __('Componente desvinculado da área.'));
    }

    public function detachAll(Request $request, KnowledgeArea $area)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        DB::transaction(function () use ($area): void {
            $area->newQuery()->whereKey($area->id)->lockForUpdate()->firstOrFail();
            $area->components()->lockForUpdate()->get()->each->update(['knowledge_area_id' => null]);
        });

        return redirect()->route('knowledge-areas.usages', $area)->with('status', __('Todos os componentes foram desvinculados da área.'));
    }

    public function destroy(Request $request, KnowledgeArea $area)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        DB::transaction(function () use ($area): void {
            $area = $area->newQuery()->whereKey($area->id)->lockForUpdate()->firstOrFail();
            if ($area->components()->exists()) {
                throw ValidationException::withMessages(['area' => __('Desvincule os componentes antes de excluir a área.')]);
            }
            $area->delete();
        });

        return redirect()->route('knowledge-areas.index')->with('status', __('Área excluída com sucesso.'));
    }

    private function validated(Request $request, ?KnowledgeArea $area = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('knowledge_areas')->ignore($area?->id)],
            'formation' => ['required', Rule::in([CurriculumCatalog::FORMATION_FGB, CurriculumCatalog::FORMATION_ITINERARY, CurriculumCatalog::FORMATION_COMPLEMENTARY])],
        ]);
    }
}
