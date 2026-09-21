@extends('layouts.app')
@section('title', __('Áreas registradas nos históricos'))
@section('page-title', __('Áreas registradas nos históricos'))
@section('content')
    <a class="btn btn-outline-primary mb-3" href="{{ route('knowledge-areas.index') }}" aria-label="{{ __('Voltar para áreas') }}" title="{{ __('Voltar para áreas') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
    <p><strong>{{ $area }}</strong> · {{ $formation }}</p>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>{{ __('Estudante') }}</th><th>{{ __('Escola') }}</th><th>{{ __('Componente') }}</th><th>{{ __('Ações') }}</th></tr></thead>
        <tbody>@forelse($components as $component)
            <tr><td>{{ $component->history->student?->full_name }}</td><td>{{ $component->history->school?->name }}</td><td>{{ $component->name }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('people.histories.edit', [$component->history->person_id, $component->history]) }}">{{ __('Editar histórico') }}</a></td></tr>
        @empty<tr><td colspan="4">{{ __('Nenhum registro encontrado.') }}</td></tr>@endforelse</tbody>
    </table></div><div class="card-body">{{ $components->links() }}</div></div>
@endsection
