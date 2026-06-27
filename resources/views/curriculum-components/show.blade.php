@extends('layouts.app')

@php($canChangeAcademicStructure = ! $academicYear->approved_at || auth()->user()->isAdministrator())

@section('title', 'Componente - '.$component->name)
@section('page-title', 'Componente: '.$component->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.show', [$academicYear, $course]) }}" aria-label="Voltar à matriz {{ $course->name }}" title="Voltar à matriz">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@push('scripts')
    <script>
        const resetCurriculumComponentScroll = () => {
            requestAnimationFrame(() => window.scrollTo({ top: 0, left: 0, behavior: 'auto' }));
        };

        window.addEventListener('load', resetCurriculumComponentScroll, { once: true });
        window.addEventListener('pageshow', resetCurriculumComponentScroll, { once: true });
    </script>
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Resumo</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Matriz</dt>
                        <dd>{{ $course->name }}</dd>
                        <dt>Área</dt>
                        <dd>{{ $component->area?->name ?? 'Não definida' }}</dd>
                        <dt>Duração</dt>
                        <dd>{{ $component->startsPeriod?->name ?? $course->startsPeriod?->name ?? 'início da matriz' }} até {{ $component->endsPeriod?->name ?? $course->endsPeriod?->name ?? 'fim da matriz' }}</dd>
                        <dt>Carga horária calculada</dt>
                        <dd>{{ $component->formattedCalculatedWorkloadHours($course) }} horas</dd>
                        <dt>Aulas semanais</dt>
                        <dd>{{ $component->weekly_lessons ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Gerenciar componente</h2>
                </div>
                <div class="card-body">
                    @if ($canChangeAcademicStructure)
                        <form method="POST" action="{{ route('academic-years.courses.components.update', [$academicYear, $course, $component]) }}">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="component_name">Componente</label>
                                    <input id="component_name" name="name" class="form-control" value="{{ old('name', $component->name) }}" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="component_weekly_lessons">Aulas semanais</label>
                                    <input id="component_weekly_lessons" name="weekly_lessons" type="number" step="1" min="0" class="form-control" value="{{ old('weekly_lessons', $component->weekly_lessons) }}">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="component_area">Área</label>
                                    <input id="component_area" class="form-control" value="{{ $component->area?->name ?? 'Não definida' }}" disabled>
                                    <input type="hidden" name="knowledge_area_id" value="{{ $component->knowledge_area_id }}">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="component_starts_period_id">Período inicial</label>
                                    <select id="component_starts_period_id" name="starts_period_id" class="form-control">
                                        <option value="">Início da matriz</option>
                                        @foreach ($periods as $period)
                                            <option value="{{ $period->id }}" @selected((int) old('starts_period_id', $component->starts_period_id) === $period->id)>{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="component_ends_period_id">Período final</label>
                                    <select id="component_ends_period_id" name="ends_period_id" class="form-control">
                                        <option value="">Fim da matriz</option>
                                        @foreach ($periods as $period)
                                            <option value="{{ $period->id }}" @selected((int) old('ends_period_id', $component->ends_period_id) === $period->id)>{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="component_notes">Observações</label>
                                <textarea id="component_notes" name="notes" class="form-control" rows="3">{{ old('notes', $component->notes) }}</textarea>
                            </div>
                            <input type="hidden" name="active" value="1">
                            <button class="btn btn-primary" type="submit">Salvar componente</button>
                        </form>
                    @else
                        <p class="mb-0 text-gray-600">Ano letivo aprovado. O componente está bloqueado para edição estrutural.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
