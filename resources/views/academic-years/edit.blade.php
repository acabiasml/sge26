@extends('layouts.app')

@section('title', 'Editar ano letivo')
@section('page-title', 'Editar ano letivo')

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="Voltar ao ano letivo {{ $academicYear->name }}" title="Voltar ao ano letivo">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="row">
        <aside class="col-xl-4 mb-4">
            <section class="card shadow h-100 sge-academic-context" aria-labelledby="academic-context-title">
                <div class="card-header py-3"><h2 id="academic-context-title" class="h6 m-0 font-weight-bold text-primary">Contexto do calendário</h2></div>
                <div class="card-body">
                    <div class="sge-context-school-mark"><i class="fas fa-school" aria-hidden="true"></i></div>
                    <h3 class="h6 font-weight-bold mb-1">{{ $academicYear->school?->name }}</h3>
                    <p class="small text-muted mb-4">As alterações nesta tela definem a vigência geral do calendário acadêmico.</p>
                    <dl class="mb-0">
                        <dt>Período atual</dt>
                        <dd>{{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}</dd>
                        <dt>Situação</dt>
                        <dd><span class="badge badge-{{ $academicYear->active ? 'success' : 'secondary' }}">{{ $academicYear->active ? 'Ativo' : 'Inativo' }}</span></dd>
                        <dt>Calendário</dt>
                        <dd>{{ $academicYear->approved_at ? 'Aprovado em '.$academicYear->approved_at->format('d/m/Y') : 'Em elaboração' }}</dd>
                    </dl>
                </div>
            </section>
        </aside>
        <div class="col-xl-8 mb-4">
            <section class="card shadow sge-academic-edit-card" aria-labelledby="academic-edit-title">
                <div class="card-header py-3"><h2 id="academic-edit-title" class="h6 m-0 font-weight-bold text-primary">Dados do ano letivo</h2></div>
                <div class="card-body">
                    <p class="text-muted mb-4">Atualize os dados gerais. Períodos avaliativos, matrizes, turmas e calendário visual são gerenciados na página principal do ano letivo.</p>
                    <form method="POST" action="{{ route('academic-years.update', $academicYear) }}">
                        @include('academic-years._form', ['school' => $academicYear->school])
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
