@extends('layouts.app')
@section('title', __('Mesclar componentes'))
@section('page-title', __('Mesclar componentes'))
@section('content')
@include('knowledge-areas._navigation')
<section class="card sge-panel-card mb-4">
    <div class="card-body">
        <p><strong>{{ $catalogComponent->name }}</strong> · {{ $catalogComponent->area->name }} · {{ __($catalogComponent->formation) }} · {{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$catalogComponent->education_stage]) }}</p>
        <p class="text-muted">{{ __('Reúna os vínculos no catálogo e escolha a nomenclatura final. As notas, faltas e cargas horárias dos históricos serão preservadas.') }}</p>
        <form method="POST" action="{{ route('history-curriculum.merge', $catalogComponent) }}" data-catalog-merge-form>
            @csrf
            <div class="form-group">
                <label for="merge-other">{{ __('Outro componente') }}</label>
                <select id="merge-other" name="other_component_id" class="form-control" required aria-describedby="merge-context">
                    <option value="">{{ __('Selecione') }}</option>
                    @foreach($candidates as $candidate)<option value="{{ $candidate->id }}" data-name="{{ $candidate->name }}" @selected((string) old('other_component_id') === (string) $candidate->id)>{{ $candidate->name }} · {{ $candidate->active ? __('Ativo') : __('Inativo') }} · {{ $candidate->history_components_count }} {{ __('Usos') }}</option>@endforeach
                </select>
                <small id="merge-context" class="form-text text-muted">{{ __('Selecione outro componente da mesma etapa, formação e área.') }}</small>
            </div>
            <fieldset class="form-group">
                <legend class="h6">{{ __('Qual nomenclatura deseja manter?') }}</legend>
                <label class="d-flex align-items-center border rounded p-3 mb-2"><input type="radio" name="keep" value="current" class="mr-2" @checked(old('keep') === 'current') required> {{ $catalogComponent->name }}</label>
                <label class="d-flex align-items-center border rounded p-3"><input type="radio" name="keep" value="other" class="mr-2" @checked(old('keep') === 'other') required><span data-merge-other-name>{{ __('Selecione o outro componente') }}</span></label>
            </fieldset>
            @if($candidates->isEmpty())<p role="status">{{ __('Não há outro componente compatível para mesclar.') }}</p>@endif
            <div class="sge-action-buttons">
                <button class="btn btn-primary" type="submit" @disabled($candidates->isEmpty())><i class="fas fa-object-group mr-1" aria-hidden="true"></i>{{ __('Mesclar componentes') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('history-curriculum.index') }}">{{ __('Cancelar') }}</a>
            </div>
        </form>
    </div>
</section>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-catalog-merge-form]');
    const select = form.querySelector('#merge-other');
    const update = () => {
        const name = select.selectedOptions[0]?.dataset.name;
        form.querySelector('[data-merge-other-name]').textContent = name || @js(__('Selecione o outro componente'));
        form.querySelector('[value="other"]').disabled = !name;
    };
    select.addEventListener('change', update);
    update();
});
</script>
@endpush
