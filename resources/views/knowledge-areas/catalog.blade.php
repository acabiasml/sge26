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
<section class="card sge-panel-card mb-4">
    <div class="card-body">
        <form method="GET" class="form-row align-items-end mb-3">
            <div class="form-group col-md-6"><label for="catalog-search">{{ __('Buscar componente ou área') }}</label><input id="catalog-search" name="q" type="search" maxlength="120" class="form-control" value="{{ $filters['q'] ?? '' }}"></div>
            <div class="form-group col-md-4"><label for="catalog-stage">{{ __('Etapa') }}</label><select id="catalog-stage" name="education_stage" class="form-control"><option value="">{{ __('Todas') }}</option>@foreach($stages as $stage => $label)<option value="{{ $stage }}" @selected(($filters['education_stage'] ?? '') === $stage)>{{ __($label) }}</option>@endforeach</select></div>
            <div class="form-group col-md-2"><button type="submit" class="btn btn-primary">{{ __('Filtrar') }}</button></div>
        </form>
        <div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('Catálogo de componentes') }}"><table class="table table-bordered table-hover mb-0">
            <thead><tr><th scope="col">{{ __('Componente') }}</th><th scope="col">{{ __('Área') }}</th><th scope="col">{{ __('Formação') }}</th><th scope="col">{{ __('Etapa') }}</th><th scope="col">{{ __('Situação') }}</th><th scope="col">{{ __('Ações') }}</th></tr></thead>
            <tbody>@forelse($components as $component)<tr>
                <th scope="row">{{ $component->name }}</th><td>{{ $component->area->name }}</td><td>{{ __($component->formation) }}</td><td>{{ __($stages[$component->education_stage]) }}</td><td>{{ $component->active ? __('Ativo') : __('Inativo') }}</td>
                <td><div class="d-flex sge-action-buttons"><button type="button" class="btn btn-outline-primary sge-icon-action" data-toggle="modal" data-target="#catalog-edit-{{ $component->id }}" aria-label="{{ __('Editar componente') }}: {{ $component->name }}" title="{{ __('Editar componente') }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
                <form method="POST" action="{{ route('history-curriculum.destroy', $component) }}" data-confirm-message="{{ __('Remover este componente do catálogo? Os registros dos históricos serão preservados.') }}">@csrf @method('DELETE')<button class="btn btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover componente') }}: {{ $component->name }}" title="{{ __('Remover componente') }}"><i class="fas fa-trash" aria-hidden="true"></i></button></form></div></td>
            </tr>@empty<tr><td colspan="6">{{ __('Nenhum componente encontrado.') }}</td></tr>@endforelse</tbody>
        </table></div>
        <div class="mt-3">{{ $components->links('pagination::bootstrap-4') }}</div>
    </div>
</section>
@foreach($components as $component)
<div class="modal fade" id="catalog-edit-{{ $component->id }}" tabindex="-1" aria-labelledby="catalog-title-{{ $component->id }}" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="catalog-title-{{ $component->id }}">{{ __('Editar componente') }}: {{ $component->name }}</h2><button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body"><form action="{{ route('history-curriculum.update', $component) }}" method="POST" data-catalog-form>@csrf @method('PUT') @include('knowledge-areas._catalog-form', ['catalogComponent' => $component])</form></div>
</div></div></div>
@endforeach
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
    document.querySelectorAll('[data-catalog-form]').forEach(form => form.querySelector('[data-catalog-stage]').addEventListener('change', event => {
        const catalog = catalogs[event.target.value], formation = form.querySelector('[data-catalog-formation]'), area = form.querySelector('[data-catalog-area]');
        formation.replaceChildren(...catalog.formations.map(value => new Option(labels[value] || value, value)));
        area.replaceChildren(new Option(@js(__('Selecione')), ''), ...catalog.areas.map(item => new Option(item.name, item.id)));
    }));
});
</script>
@endpush
