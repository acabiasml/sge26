@extends('layouts.app')

@section('title', 'Diários')
@section('page-title', $isManagement ? 'Diários das escolas' : 'Meus diários')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm" href="{{ route('teacher-schedules.index') }}">
        <i class="fas fa-calendar-alt mr-1" aria-hidden="true"></i> Meu horário
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-schedules.pdf') }}" aria-label="Imprimir meu horário docente" title="Imprimir meu horário">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <section class="card shadow mb-4" aria-labelledby="diaries-title">
        <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
            <h2 id="diaries-title" class="h6 m-0 font-weight-bold text-primary">{{ $isManagement ? 'Diários disponíveis para gestão' : 'Diários disponíveis' }}</h2>
            <span class="badge badge-light">{{ $diaries->count() }} registro(s)</span>
        </div>
        <div class="card-body">
            @forelse ($diaries as $diary)
                <article class="sge-diary-list-card">
                    <div class="sge-diary-list-main">
                        <h3>{{ $diary['component']->name }}</h3>
                        <p>{{ $diary['class']->name }} · {{ $diary['academicYear']->school?->name }}</p>
                        <small>{{ $diary['academicYear']->name }} · {{ $diary['course']->name }} · {{ $diary['component']->area?->name ?? 'Área não definida' }}</small>
                    </div>
                    <div class="sge-diary-list-actions" aria-label="Ações do diário de {{ $diary['component']->name }}">
                        <a class="btn btn-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.show', [$diary['class'], $diary['component']]) }}" aria-label="Abrir diário de {{ $diary['component']->name }} da turma {{ $diary['class']->name }}" title="Abrir diário">
                            <i class="fas fa-book-open" aria-hidden="true"></i>
                        </a>
                        <a class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('academic-years.classes.schedules.pdf', [$diary['academicYear'], $diary['class']]) }}" aria-label="Imprimir horário da turma {{ $diary['class']->name }}" title="Imprimir horário da turma">
                            <i class="fas fa-calendar-week" aria-hidden="true"></i>
                        </a>
                        <a class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.attendance-sheet.pdf', [$diary['class'], $diary['component']]) }}" aria-label="Imprimir lista de chamada mensal de {{ $diary['component']->name }}" title="Imprimir lista de chamada">
                            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>
            @empty
                <p class="mb-0">Nenhum diário disponível. Os diários aparecem quando o ano letivo está aprovado e há turma vinculada ao componente curricular.</p>
            @endforelse
        </div>
    </section>
@endsection
