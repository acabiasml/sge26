@extends('layouts.app')

@section('title', 'Conteúdos - '.$component->name)
@section('page-title', 'Conteúdos: '.$component->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.show', [$schoolClass, $component, 'period' => $period->id]) }}" aria-label="Voltar ao diário" title="Voltar ao diário"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
    @php($diaryStartsAt = $period->allow_diary_entries_outside_period ? $academicYear->starts_at : $period->starts_at)
    @php($diaryEndsAt = $period->allow_diary_entries_outside_period ? $academicYear->ends_at : $period->ends_at)
    <section class="card shadow mb-4" aria-labelledby="content-context-title">
        <div class="card-header py-3"><h2 id="content-context-title" class="h6 m-0 font-weight-bold text-primary">{{ $schoolClass->name }} · {{ $period->name }}</h2></div>
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <p class="mb-0 mr-3">Registre um único conteúdo por dia de aula. Dias com conteúdo e sem frequência, ou o contrário, serão sinalizados como pendência.</p>
            <span class="small text-muted">{{ $component->name }} · {{ $course->name }}</span>
        </div>
    </section>

    <section class="card shadow mb-4" aria-labelledby="content-range-title">
        <div class="card-header py-3 d-flex justify-content-between align-items-center"><h2 id="content-range-title" class="h6 m-0 font-weight-bold text-primary">{{ $usesScheduledDiary ? 'Dias previstos pelo horário' : 'Adicionar dia para lançamento' }}</h2><span class="small text-muted">{{ $days->count() }} {{ $usesScheduledDiary ? 'nesta página' : 'selecionados' }}</span></div>
        <div class="card-body">
            @if($usesScheduledDiary)
                <p class="mb-0 small text-muted">Os dias abaixo são gerados automaticamente a partir do horário da turma e do calendário letivo. Registre um único conteúdo para cada data em que este componente está previsto.</p>
            @else
                <form method="GET" action="{{ route('teacher-diaries.contents', [$schoolClass, $component]) }}" class="row align-items-end"><input type="hidden" name="period" value="{{ $period->id }}"><input type="hidden" name="dates" value="{{ implode(',', $selectedDates) }}"><div class="col-md-5 form-group mb-md-0"><label for="content_add_date">Dia letivo</label><input id="content_add_date" name="add_date" type="date" min="{{ $diaryStartsAt->toDateString() }}" max="{{ $diaryEndsAt->toDateString() }}" class="form-control"></div><div class="col-md-7 form-group mb-0"><button class="btn btn-outline-primary" type="submit"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Adicionar dia à lista</button></div></form><p class="small text-muted mb-0 mt-3">Esta turma não possui horário para este componente. Adicione apenas os dias letivos que deseja registrar.</p>
                @if($period->allow_diary_entries_outside_period)<p class="small text-info mb-0 mt-2"><i class="fas fa-info-circle mr-1" aria-hidden="true"></i>A gestão autorizou lançamentos em dias letivos fora das datas deste período.</p>@endif
            @endif
        </div>
    </section>

    <form method="POST" action="{{ route('teacher-diaries.contents.update', [$schoolClass, $component]) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
        <input type="hidden" name="page" value="{{ $page }}">
        @foreach($selectedDates as $selectedDate)<input type="hidden" name="selected_dates[]" value="{{ $selectedDate }}">@endforeach
        <section class="card shadow" aria-labelledby="content-list-title">
            <div class="card-header py-3 d-flex justify-content-between align-items-center"><h2 id="content-list-title" class="h6 m-0 font-weight-bold text-primary">Conteúdo por dia</h2><span class="small text-muted">{{ $days->count() }} dias letivos selecionados</span></div>
            <div class="card-body">
                @forelse ($days as $day)
                    @php($date = $day->date->toDateString())
                    @php($content = $contents->get($date))
                    @php($hasAttendance = in_array($date, $attendanceDates, true))
                    <article class="sge-diary-content-row {{ $hasAttendance ? 'sge-diary-content-has-attendance' : '' }}">
                        <div class="sge-diary-content-date"><strong>{{ $day->date->format('d/m') }}</strong><span>{{ $day->date->translatedFormat('D') }}</span></div>
                        <div class="flex-grow-1">
                            <label class="sr-only" for="content_{{ $date }}">Conteúdo de {{ $day->date->format('d/m/Y') }}</label>
                            <textarea id="content_{{ $date }}" name="contents[{{ $date }}]" rows="2" class="form-control" placeholder="Conteúdo ministrado neste dia">{{ old('contents.'.$date, $content?->content) }}</textarea>
                            <div class="d-flex justify-content-between align-items-center flex-wrap mt-1 small text-muted">
                                <span>@if($hasAttendance)<i class="fas fa-clipboard-check text-success" aria-hidden="true"></i> Frequência lançada @else <i class="fas fa-exclamation-circle text-warning" aria-hidden="true"></i> Frequência pendente @endif</span>
                                <span>@if($content?->updatedBy)Última alteração: {{ $content->updatedBy->full_name }}@endif @if($content)<button class="btn btn-link btn-sm text-danger p-0 ml-2" name="delete_dates[]" value="{{ $date }}" type="submit" formnovalidate onclick="return confirm('Excluir o conteúdo lançado para este dia?')"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="sr-only">Excluir conteúdo de {{ $day->date->format('d/m/Y') }}</span></button>@endif</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="sge-empty-state"><i class="fas {{ $usesScheduledDiary ? 'fa-clock' : 'fa-calendar-plus' }}" aria-hidden="true"></i><p>{{ $usesScheduledDiary ? 'Não há dias previstos para este componente neste período. Cadastre o horário da turma para gerar os lançamentos automaticamente.' : 'Nenhum dia selecionado. Use o campo acima para incluir um dia letivo nesta folha.' }}</p></div>
                @endforelse
            </div>
            @if($days->isNotEmpty())<div class="card-footer bg-white text-right"><button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i>Salvar conteúdos</button></div>@endif
        </section>
    </form>
    @if($usesScheduledDiary && $totalPages > 1)<nav class="d-flex justify-content-between align-items-center mt-4" aria-label="Navegação dos dias previstos">@if($page > 1)<a class="btn btn-outline-secondary btn-sm" href="{{ route('teacher-diaries.contents', [$schoolClass, $component, 'period' => $period->id, 'page' => $page - 1]) }}"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>Anteriores</a>@else<span></span>@endif<span class="small text-muted">Página {{ $page }} de {{ $totalPages }}</span>@if($page < $totalPages)<a class="btn btn-outline-secondary btn-sm" href="{{ route('teacher-diaries.contents', [$schoolClass, $component, 'period' => $period->id, 'page' => $page + 1]) }}">Próximos<i class="fas fa-arrow-right ml-1" aria-hidden="true"></i></a>@endif</nav>@endif
@endsection
