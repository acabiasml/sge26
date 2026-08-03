@extends('layouts.app')

@section('title', 'Documentos da matrícula')
@section('page-title', 'Documentos da matrícula')

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('classes.enrollments.index', $class) }}" aria-label="Voltar às matrículas da turma {{ $class->name }}" title="Voltar às matrículas">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <section class="sge-student-profile mb-4" aria-labelledby="documents-title">
        <div class="sge-student-profile-main">
            <div class="sge-avatar-lg" aria-hidden="true">{{ mb_substr($enrollment->student?->social_name ?: $enrollment->student?->full_name ?: 'E', 0, 1) }}</div>
            <div>
                <div class="sge-page-kicker">Conferência documental</div>
                <h2 id="documents-title">{{ $enrollment->student?->social_name ?: $enrollment->student?->full_name }}</h2>
                <div class="sge-student-meta">
                    <span><i class="fas fa-school" aria-hidden="true"></i>{{ $academicYear->school?->name }}</span>
                    <span><i class="fas fa-graduation-cap" aria-hidden="true"></i>{{ \App\Support\AcademicContextLabel::classWithStages($class->name, $class->courses) }}</span>
                    <span><i class="fas fa-calendar" aria-hidden="true"></i>{{ $academicYear->name }}</span>
                    <span><i class="fas fa-user-check" aria-hidden="true"></i>{{ $enrollment->statusLabel() }}</span>
                </div>
            </div>
        </div>
    </section>

    <div class="row">
        <div class="col-xl-5 mb-4">
            <section class="card shadow h-100" aria-labelledby="checks-title">
                <div class="card-header py-3">
                    <h2 id="checks-title" class="h6 m-0 font-weight-bold text-primary">Conferência antes da emissão</h2>
                </div>
                <div class="card-body">
                    @foreach ($checks as $check)
                        <article class="sge-side-card mb-3">
                            <div class="sge-side-card-icon text-{{ $check['severity'] }}">
                                <i class="fas {{ $check['ok'] ? 'fa-check' : 'fa-exclamation-triangle' }}" aria-hidden="true"></i>
                            </div>
                            <div>
                                <strong>{{ $check['label'] }}</strong>
                                <span>{{ $check['message'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="col-xl-7 mb-4">
            <section class="card shadow h-100" aria-labelledby="documents-list-title">
                <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                    <h2 id="documents-list-title" class="h6 m-0 font-weight-bold text-primary">Documentos disponíveis</h2>
                    <span class="badge badge-light">{{ collect($documents)->where('enabled', true)->count() }} liberado(s)</span>
                </div>
                <div class="card-body">
                    <div class="sge-document-grid">
                        @foreach ($documents as $document)
                            <article class="sge-document-card {{ $document['enabled'] ? '' : 'is-disabled' }}">
                                <div class="sge-document-card-icon" aria-hidden="true">
                                    <i class="fas {{ $document['icon'] }}"></i>
                                </div>
                                <div>
                                    <h3>{{ $document['title'] }}</h3>
                                    <p>{{ $document['description'] }}</p>
                                    @if ($document['reason'])
                                        <small>{{ $document['reason'] }}</small>
                                    @endif
                                </div>
                                @if ($document['enabled'])
                                    <a class="btn btn-sm btn-primary sge-icon-action" href="{{ $document['route'] }}" aria-label="Emitir {{ $document['title'] }} de {{ $enrollment->student?->full_name }}" title="Emitir {{ $document['title'] }}">
                                        <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                    </a>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary sge-icon-action" type="button" disabled aria-label="{{ $document['title'] }} bloqueado" title="{{ $document['reason'] }}">
                                        <i class="fas fa-lock" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
