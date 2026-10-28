@extends('layouts.app')

@section('title', __('Editar justificativa de ausência'))
@section('page-title', __('Editar justificativa de ausência'))
@section('page-actions')
    <a href="{{ route('attendance-justifications.index', ['school' => $academicYear->school_id, 'year' => $academicYear->id, 'class' => $justification->enrollment?->school_class_id]) }}" class="btn btn-sm btn-outline-primary shadow-sm" aria-label="{{ __('Voltar para justificativas de ausência') }}"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>{{ __('Voltar') }}</a>
@endsection

@section('content')
    <section class="card shadow mb-4" aria-labelledby="edit-justification-title">
        <div class="card-header py-3"><h2 id="edit-justification-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Editar justificativa') }}</h2></div>
        <div class="card-body">
            <p class="small text-muted">{{ __('Criada por') }} {{ $justification->grantedBy?->full_name ?? __('Sistema') }} · {{ $justification->modification_count }} {{ __('alteração(ões)') }}</p>
            @include('attendance-justifications.form')
        </div>
    </section>
@endsection
