@extends('layouts.app')
@section('title', $area->name)
@section('page-title', $area->name)
@section('content')
    <a class="btn btn-outline-secondary mb-3" href="{{ route('knowledge-areas.index') }}"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>{{ __('Áreas e formações') }}</a>
    <section class="card shadow-sm mb-4">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
            <div><span class="text-muted">{{ __('Formação') }}</span><h2 class="h5">{{ __($area->formation ?: 'Formação não definida') }}</h2><span>{{ $area->components_count }} {{ __('componentes vinculados') }}</span></div>
            @if($area->components_count)
                <form method="POST" action="{{ route('knowledge-areas.detach-all', $area) }}" onsubmit="return confirm(@js(__('Desvincular todos os componentes desta área? Os componentes serão mantidos, sem área definida.')))">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('Desvincular todos') }}</button></form>
            @else
                <form method="POST" action="{{ route('knowledge-areas.destroy', $area) }}" onsubmit="return confirm(@js(__('Excluir esta área sem vínculos?')))">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('Excluir área') }}</button></form>
            @endif
        </div>
    </section>
    <p class="text-muted">{{ __('Desvincular remove somente a associação com a área. O componente, suas notas e seus diários permanecem cadastrados.') }}</p>
    <section class="card shadow-sm">
        <div class="card-header font-weight-bold">{{ __('Onde esta área é usada') }}</div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>{{ __('Componente') }}</th><th>{{ __('Matriz') }}</th><th>{{ __('Escola') }}</th><th>{{ __('Ano letivo') }}</th><th>{{ __('Ações') }}</th></tr></thead>
            <tbody>@forelse($components as $component)
                @php($course = $component->course)
                @php($year = $course->academicYear)
                <tr>
                    <td><a href="{{ route('academic-years.courses.components.show', [$year, $course, $component]) }}">{{ $component->name }}</a></td>
                    <td><a href="{{ route('academic-years.courses.show', [$year, $course]) }}">{{ $course->name }}</a></td>
                    <td>{{ $year->school?->name }}</td><td><a href="{{ route('academic-years.show', $year) }}">{{ $year->reference_year }} · {{ $year->name }}</a></td>
                    <td><form method="POST" action="{{ route('knowledge-areas.detach', [$area, $component]) }}" onsubmit="return confirm(@js(__('Desvincular este componente da área?')))">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Desvincular') }}</button></form></td>
                </tr>
            @empty<tr><td colspan="5" class="text-muted p-4">{{ __('Esta área não possui vínculos e pode ser excluída.') }}</td></tr>@endforelse</tbody>
        </table></div>
        @if($components->hasPages())<div class="card-footer">{{ $components->links() }}</div>@endif
    </section>
@endsection
