@extends('layouts.app')
@section('title', __('Áreas e componentes'))
@section('page-title', __('Áreas e componentes'))
@section('content')
<nav class="sge-section-nav mb-4" aria-label="{{ __('Cadastro curricular') }}">
    <a class="sge-section-nav-item" href="{{ route('knowledge-areas.index') }}"><i class="fas fa-layer-group" aria-hidden="true"></i><span>{{ __('Áreas e formações') }}</span></a>
    <a class="sge-section-nav-item active" aria-current="page" href="{{ route('history-curriculum.index') }}"><i class="fas fa-book" aria-hidden="true"></i><span>{{ __('Componentes') }}</span></a>
</nav>
<p class="text-muted">{{ __('Este cadastro fornece as opções de formação, área e componente dos históricos escolares, conforme a etapa de ensino.') }}</p>
<details class="card sge-panel-card mb-4" @if(old('catalog_form') === 'catalog-new') open @endif>
    <summary class="sge-panel-header sge-history-section-summary"><h2>{{ __('Cadastrar componente') }}</h2><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
    <div class="card-body"><form action="{{ route('history-curriculum.store') }}" method="POST" data-catalog-form>@csrf @include('knowledge-areas._catalog-form', ['catalogComponent' => new \App\Models\HistoryCurriculumComponent])</form></div>
</details>
<section class="card sge-panel-card sge-livewire-table-card mb-4">
    <div class="card-body"><livewire:history-curriculum-components-table /></div>
</section>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const submitted = @json(old('catalog_form'));
    if (submitted && /^catalog-[0-9]+$/.test(submitted)) {
        window.jQuery('#catalog-edit-' + submitted.slice('catalog-'.length)).modal('show');
    }
    const catalogs = @json($config);
    const labels = @json($formationLabels);
    // Os formulários de edição são recriados pelo Livewire ao paginar ou filtrar.
    document.addEventListener('change', event => {
        if (!event.target.matches('[data-catalog-stage]')) return;
        const form = event.target.closest('[data-catalog-form]');
        if (!form) return;
        const catalog = catalogs[event.target.value], formation = form.querySelector('[data-catalog-formation]'), area = form.querySelector('[data-catalog-area]');
        formation.replaceChildren(...catalog.formations.map(value => new Option(labels[value] || value, value)));
        area.replaceChildren(new Option(@js(__('Selecione')), ''), ...catalog.areas.map(item => new Option(item.name, item.id)));
    });
});
</script>
@endpush
