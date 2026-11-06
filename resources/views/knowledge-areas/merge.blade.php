@extends('layouts.app')
@section('title', __('Mesclar componentes'))
@section('page-title', __('Mesclar componentes'))
@section('content')
@include('knowledge-areas._navigation')
<section class="card sge-panel-card mb-4">
    <div class="card-body">
        <p><strong>{{ __('Origem') }}: {{ $catalogComponent->name }}</strong> · {{ $catalogComponent->area->name }} · {{ __($catalogComponent->formation) }} · {{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$catalogComponent->education_stage]) }}</p>
        <p class="text-muted">{{ __('Escolha outro componente do catálogo e o cadastro que vai prevalecer. A mesclagem atualiza nome, área e formação nos históricos e nas matrizes vinculadas, preservando notas, faltas, cargas horárias e vínculos dos diários.') }}</p>
        @if($catalogComponent->matrixComponents->isNotEmpty())
            <details class="mb-3">
                <summary>{{ __('Matrizes vinculadas a este componente') }}</summary>
                <ul class="mt-2">
                    @foreach($catalogComponent->matrixComponents->unique('academic_course_id') as $matrixComponent)
                        <li><a href="{{ route('academic-years.courses.show', [$matrixComponent->course->academicYear, $matrixComponent->course]) }}">{{ $matrixComponent->course->name }} · {{ $matrixComponent->course->academicYear->school?->name }} · {{ $matrixComponent->course->academicYear->reference_year }}</a></li>
                    @endforeach
                </ul>
            </details>
        @endif
        <form method="POST" action="{{ route('history-curriculum.merge', $catalogComponent) }}" data-catalog-merge-form
              data-source-name="{{ $catalogComponent->name }}" data-source-stage="{{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$catalogComponent->education_stage]) }}" data-source-formation="{{ __($catalogComponent->formation) }}" data-source-area="{{ $catalogComponent->area->name }}"
              data-confirm-message="{{ __('Confirmar a mesclagem? Os históricos e as matrizes vinculadas, quando houver, adotarão o cadastro escolhido. Os lançamentos escolares serão preservados.') }}">
            @csrf
            <div class="form-group">
                <label for="merge-target">{{ __('Selecione o outro componente') }}</label>
                <select id="merge-target" name="target_component_id" class="form-control" required aria-describedby="merge-context">
                    <option value="">{{ __('Selecione') }}</option>
                    @foreach($candidates as $candidate)
                        <option value="{{ $candidate->id }}" data-name="{{ $candidate->name }}" data-stage="{{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$candidate->education_stage]) }}" data-formation="{{ __($candidate->formation) }}" data-area="{{ $candidate->area->name }}" @selected((string) old('target_component_id') === (string) $candidate->id)>{{ $candidate->name }} · {{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$candidate->education_stage]) }} · {{ __($candidate->formation) }} · {{ $candidate->area->name }}</option>
                    @endforeach
                </select>
                <small id="merge-context" class="form-text text-muted">{{ __('A classificação escolhida deve ser compatível com a etapa de ensino das matrizes afetadas.') }}</small>
            </div>
                <fieldset class="form-group">
                    <legend class="h6">{{ __('Qual cadastro deve prevalecer?') }}</legend>
                    <div class="custom-control custom-radio mb-2">
                        <input class="custom-control-input" type="radio" id="merge-keep-source" name="keep" value="source" @checked(old('keep', 'target') === 'source')>
                        <label class="custom-control-label" for="merge-keep-source">{{ $catalogComponent->name }} · {{ __($catalogComponent->formation) }} · {{ $catalogComponent->area->name }}</label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" id="merge-keep-target" name="keep" value="target" @checked(old('keep', 'target') === 'target')>
                        <label class="custom-control-label" for="merge-keep-target" data-merge-target-label>{{ __('Manter o outro componente selecionado') }}</label>
                    </div>
                </fieldset>
            <div class="border rounded p-3 mb-3" data-merge-preview hidden aria-live="polite">
                <p class="small text-muted mb-1">{{ __('Cadastro que será mantido') }}</p>
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
            @if($candidates->isEmpty())<p role="status">{{ __('Não há outro componente disponível para mesclar.') }}</p>@endif
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
        const option = select.selectedOptions[0];
        const keepSource = form.querySelector('input[name="keep"]:checked')?.value === 'source';
        const data = keepSource ? { name: form.dataset.sourceName, stage: form.dataset.sourceStage, formation: form.dataset.sourceFormation, area: form.dataset.sourceArea } : option?.dataset;
        const targetLabel = form.querySelector('[data-merge-target-label]');
        if (targetLabel && select.value) targetLabel.textContent = [option.dataset.name, option.dataset.formation, option.dataset.area].join(' · ');
        form.querySelector('[data-merge-preview]').hidden = !select.value;
        form.querySelector('[data-merge-name]').textContent = data?.name || '';
        form.querySelector('[data-merge-classification]').textContent = [data?.stage, data?.formation, data?.area].filter(Boolean).join(' · ');
        form.querySelectorAll('[data-target-id]').forEach(row => { row.hidden = row.dataset.targetId !== select.value; });
    };
    select.addEventListener('change', update);
    form.querySelectorAll('input[name="keep"]').forEach(radio => radio.addEventListener('change', update));
    update();
});
</script>
@endpush
