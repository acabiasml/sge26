@extends('layouts.app')
@section('title', __('Áreas e formações'))
@section('page-title', __('Áreas e formações'))
@section('content')
    <p class="text-muted">{{ __('Organize as áreas, defina sua formação e acompanhe os componentes vinculados em todas as escolas.') }}</p>
    <details class="card shadow-sm mb-4" @if($errors->any()) open @endif>
        <summary class="card-header font-weight-bold"><i class="fas fa-plus mr-2" aria-hidden="true"></i>{{ __('Nova área') }}</summary>
        <form class="card-body" method="POST" action="{{ route('knowledge-areas.store') }}">
            @csrf
            <div class="row align-items-end">
                <div class="col-md-5 form-group"><label for="new-area-name">{{ __('Nome') }}</label><input id="new-area-name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255"></div>
                <div class="col-md-5 form-group"><label for="new-area-formation">{{ __('Formação') }}</label><select id="new-area-formation" name="formation" class="form-control" required><option value="">{{ __('Selecione') }}</option>@foreach($formations as $formation)<option value="{{ $formation }}" @selected(old('formation') === $formation)>{{ __($formation) }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><button class="btn btn-primary">{{ __('Cadastrar') }}</button></div>
            </div>
        </form>
    </details>
    <section class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center"><strong>{{ __('Áreas cadastradas') }}</strong><span class="badge badge-light">{{ $areas->count() }}</span></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>{{ __('Área') }}</th><th>{{ __('Formação') }}</th><th class="text-center">{{ __('Componentes vinculados') }}</th><th class="text-right">{{ __('Ações') }}</th></tr></thead>
                <tbody>@forelse($areas as $area)
                    <tr>
                        <td class="align-middle font-weight-bold"><a href="{{ route('knowledge-areas.usages', $area) }}">{{ $area->name }}</a></td>
                        <td class="align-middle">{{ __($area->formation ?: 'Formação não definida') }}</td>
                        <td class="align-middle text-center"><a class="btn btn-sm btn-outline-primary" href="{{ route('knowledge-areas.usages', $area) }}">{{ $area->components_count }} · {{ __('Ver usos') }}</a></td>
                        <td class="align-middle text-right text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#edit-area-{{ $area->id }}">{{ __('Editar') }}</button>
                            @if($area->components_count === 0)
                                <form class="d-inline" method="POST" action="{{ route('knowledge-areas.destroy', $area) }}" onsubmit="return confirm(@js(__('Excluir esta área sem vínculos?')))">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Excluir') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @empty<tr><td colspan="4">{{ __('Nenhuma área cadastrada.') }}</td></tr>@endforelse</tbody>
            </table>
        </div>
    </section>
    <details class="card shadow-sm mt-4">
        <summary class="card-header font-weight-bold">{{ __('Áreas registradas nos históricos') }}</summary>
        <div class="card-body"><p>{{ __('Os históricos preservam as áreas e formações do documento de origem. Esses nomes são independentes do catálogo das matrizes. Consulte os usos para editar o histórico correspondente.') }}</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>{{ __('Área') }}</th><th>{{ __('Formação') }}</th><th>{{ __('Usos') }}</th></tr></thead><tbody>
        @foreach($historicalAreas as $historicalArea)
            <tr><td>{{ $historicalArea->knowledge_area ?: '—' }}</td><td>{{ $historicalArea->formation ?: '—' }}</td><td><a href="{{ route('knowledge-areas.historical-usages', ['formation' => $historicalArea->formation, 'area' => $historicalArea->knowledge_area]) }}">{{ __('Ver usos') }} ({{ $historicalArea->uses_count }})</a></td></tr>
        @endforeach
        </tbody></table></div></div>
    </details>
    @foreach($areas as $area)
        <div class="modal fade" id="edit-area-{{ $area->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-area-title-{{ $area->id }}" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><form class="modal-content" method="POST" action="{{ route('knowledge-areas.update', $area) }}">
            @csrf @method('PUT')
            <div class="modal-header"><h2 class="modal-title h5" id="edit-area-title-{{ $area->id }}">{{ __('Editar área') }}</h2><button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <label for="area-name-{{ $area->id }}">{{ __('Área') }}</label><input id="area-name-{{ $area->id }}" name="name" class="form-control mb-3" value="{{ $area->name }}" required maxlength="255">
                <label for="area-formation-{{ $area->id }}">{{ __('Formação') }}</label><select id="area-formation-{{ $area->id }}" name="formation" class="form-control mb-3" required><option value="">{{ __('Formação não definida') }}</option>@foreach($formations as $formation)<option value="{{ $formation }}" @selected($area->formation === $formation)>{{ __($formation) }}</option>@endforeach</select>
                <p class="small text-muted mb-0">{{ __('A formação da área é utilizada por todos os componentes vinculados a ela, em todas as matrizes. Alterações valem para os próximos relatórios gerados.') }}</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('Cancelar') }}</button><button class="btn btn-primary">{{ __('Salvar') }}</button></div>
        </form></div></div>
    @endforeach
@endsection
