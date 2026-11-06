@extends('layouts.app')
@section('title', __('Mesclar componentes'))
@section('page-title', __('Mesclar componentes'))
@section('content')
@include('knowledge-areas._navigation')
<section class="card sge-panel-card mb-4">
    <div class="card-body">
        <p><strong>{{ __('Origem') }}: {{ $catalogComponent->name }}</strong> · {{ $catalogComponent->area->name }} · {{ __($catalogComponent->formation) }} · {{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$catalogComponent->education_stage]) }}</p>
        <p class="text-muted">{{ __('Escolha o destino em uma matriz do sistema. Todos os registros históricos da origem adotarão o nome, a etapa, a formação e a área do destino. Notas, faltas e cargas horárias serão preservadas.') }}</p>
        <form method="POST" action="{{ route('history-curriculum.merge', $catalogComponent) }}" data-catalog-merge-form>
            @csrf
            <div class="form-group">
                <label for="merge-target">{{ __('Componente de destino na matriz') }}</label>
                <select id="merge-target" name="target_component_id" class="form-control" required aria-describedby="merge-context">
                    <option value="">{{ __('Selecione') }}</option>
                    @foreach($candidates as $candidate)
                        <option value="{{ $candidate->id }}" data-name="{{ $candidate->name }}" data-stage="{{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$candidate->education_stage]) }}" data-formation="{{ __($candidate->formation) }}" data-area="{{ $candidate->area->name }}" @selected((string) old('target_component_id') === (string) $candidate->id)>{{ $candidate->name }} · {{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$candidate->education_stage]) }} · {{ __($candidate->formation) }} · {{ $candidate->area->name }}</option>
                    @endforeach
                </select>
                <small id="merge-context" class="form-text text-muted">{{ __('Somente componentes já cadastrados em matrizes podem receber a mesclagem. As matrizes permanecem intactas.') }}</small>
            </div>
            <div class="border rounded p-3 mb-3" data-merge-preview hidden aria-live="polite">
                <strong data-merge-name></strong>
                <p class="mb-0" data-merge-classification></p>
                <ul class="mb-0 mt-2" data-merge-matrices>
                    @foreach($candidates as $candidate)
                        @foreach($candidate->matrixComponents as $matrixComponent)
                            <li data-target-id="{{ $candidate->id }}" hidden>{{ $matrixComponent->course->name }} · {{ $matrixComponent->course->academicYear?->school?->name }} · {{ $matrixComponent->course->academicYear?->reference_year }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
            @if($candidates->isEmpty())<p role="status">{{ __('Não há componentes de matriz disponíveis para receber a mesclagem.') }}</p>@endif
            <div class="sge-action-buttons">
                <button class="btn btn-primary" type="submit" @disabled($candidates->isEmpty())><i class="fas fa-object-group mr-1" aria-hidden="true"></i>{{ __('Mesclar componentes') }}</button>
                <a data-navigation-return class="btn btn-outline-secondary" href="{{ route('history-curriculum.index') }}">{{ __('Cancelar') }}</a>
            </div>
        </form>
    </div>
</section>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-catalog-merge-form]');
    const select = form.querySelector('#merge-target');
    const update = () => {
        const option = select.selectedOptions[0], data = option?.dataset;
        form.querySelector('[data-merge-preview]').hidden = !select.value;
        form.querySelector('[data-merge-name]').textContent = data?.name || '';
        form.querySelector('[data-merge-classification]').textContent = [data?.stage, data?.formation, data?.area].filter(Boolean).join(' · ');
        form.querySelectorAll('[data-target-id]').forEach(row => { row.hidden = row.dataset.targetId !== select.value; });
    };
    select.addEventListener('change', update);
    update();
});
</script>
@endpush
