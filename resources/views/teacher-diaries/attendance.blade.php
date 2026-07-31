@extends('layouts.app')

@section('title', 'Frequência - '.$component->name)
@section('page-title', 'Frequência: '.$component->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.show', [$schoolClass, $component, 'period' => $period->id]) }}" aria-label="Voltar ao diário" title="Voltar ao diário"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
    <section class="card shadow mb-4" aria-labelledby="attendance-range-title">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap"><h2 id="attendance-range-title" class="h6 m-0 font-weight-bold text-primary">{{ $usesScheduledDiary ? 'Dias previstos pelo horário' : 'Período de lançamento' }}</h2><a class="btn btn-sm btn-outline-primary mt-2 mt-md-0" href="{{ route('teacher-diaries.contents', [$schoolClass, $component, 'period' => $period->id, 'page' => $page]) }}"><i class="fas fa-book mr-1" aria-hidden="true"></i>Conteúdos</a></div>
        <div class="card-body">
            @if($usesScheduledDiary)
                <p class="mb-0 small text-muted">{{ $academicYear->school?->name }} · {{ $schoolClass->name }} · {{ $component->name }} · {{ $period->name }}. As datas são definidas automaticamente pelo horário semanal da turma e pelos dias letivos do calendário.</p>
            @else
                <p class="small text-muted">Esta turma não possui horário cadastrado para este componente. Escolha até 15 dias letivos para a folha de frequência.</p>
                <form method="GET" action="{{ route('teacher-diaries.attendance', [$schoolClass, $component]) }}" class="row align-items-end"><input type="hidden" name="period" value="{{ $period->id }}"><div class="col-md-4 form-group mb-md-0"><label for="attendance_starts_at">Início</label><input id="attendance_starts_at" name="starts_at" type="date" min="{{ $period->starts_at->format('Y-m-d') }}" max="{{ $period->ends_at->format('Y-m-d') }}" value="{{ $startsAt?->format('Y-m-d') }}" class="form-control" required></div><div class="col-md-4 form-group mb-md-0"><label for="attendance_ends_at">Fim</label><input id="attendance_ends_at" name="ends_at" type="date" min="{{ $period->starts_at->format('Y-m-d') }}" max="{{ $period->ends_at->format('Y-m-d') }}" value="{{ $endsAt?->format('Y-m-d') }}" class="form-control" required></div><div class="col-md-4"><button class="btn btn-outline-primary" type="submit"><i class="fas fa-calendar-alt mr-1" aria-hidden="true"></i>Atualizar folha</button></div></form>
            @endif
        </div>
    </section>

    <section class="card shadow mb-4" aria-labelledby="attendance-sheet-title">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap"><h2 id="attendance-sheet-title" class="h6 m-0 font-weight-bold text-primary">Folha de frequência</h2><span class="small text-muted">Defina as aulas do componente em cada dia; as caixas surgem em seguida.</span></div>
        <div class="card-body">
            @if ($days->isEmpty())
                <p class="mb-0">Não há dias letivos neste intervalo.</p>
            @else
                <form method="POST" action="{{ route('teacher-diaries.attendance.batch-update', [$schoolClass, $component]) }}" data-attendance-sheet>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
                    @if($usesScheduledDiary)<input type="hidden" name="page" value="{{ $page }}">@foreach($days as $scheduledDay)<input type="hidden" name="scheduled_dates[]" value="{{ $scheduledDay->date->toDateString() }}">@endforeach @else<input type="hidden" name="starts_at" value="{{ $startsAt?->format('Y-m-d') }}"><input type="hidden" name="ends_at" value="{{ $endsAt?->format('Y-m-d') }}">@endif
                    <div class="table-responsive sge-attendance-sheet-wrap">
                        <table class="table table-bordered table-sm sge-attendance-sheet">
                            <thead><tr>
                                <th scope="col" class="sge-attendance-student-column">Estudante</th>
                                @foreach ($days as $day)
                                    @php($date = $day->date->toDateString())
                                        @php($record = $records->get($date))
                                        @php($content = $contents->get($date))
                                    <th scope="col" class="text-center sge-attendance-date-column">
                                        <span class="d-block">{{ $day->date->format('d/m') }}</span><span class="d-block small text-muted text-uppercase">{{ $day->date->translatedFormat('D') }}</span>
                                        @if($content)<span class="d-block small text-success" title="Conteúdo lançado"><i class="fas fa-book" aria-hidden="true"></i><span class="sr-only">Conteúdo lançado</span></span>@else<span class="d-block small text-warning" title="Conteúdo pendente"><i class="fas fa-exclamation-circle" aria-hidden="true"></i><span class="sr-only">Conteúdo pendente</span></span>@endif
                                        @if($record?->updatedBy && $record->updated_by_person_id !== $assignment->teacher_person_id)<span class="d-block small text-warning" title="Frequência alterada por {{ $record->updatedBy->full_name }}"><i class="fas fa-user-shield" aria-hidden="true"></i><span class="sr-only">Frequência alterada por {{ $record->updatedBy->full_name }}</span></span>@endif
                                        <label class="sr-only" for="lesson_count_{{ $date }}">Aulas de {{ $component->name }} em {{ $day->date->format('d/m/Y') }}</label>
                                        <select id="lesson_count_{{ $date }}" name="lesson_counts[{{ $date }}]" class="form-control form-control-sm mt-1" data-lesson-count data-date="{{ $date }}">
                                            @php($maxLessons = $usesScheduledDiary ? $day->scheduled_lessons : 24)
                                            @for ($count = 0; $count <= $maxLessons; $count++)<option value="{{ $count }}" @selected((int) old('lesson_counts.'.$date, $record?->lesson_count ?? 0) === $count)>{{ $count }} aula(s)</option>@endfor
                                        </select>
                                    </th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                                @forelse ($enrollments as $enrollment)
                                    @php($enrollmentLocked = ! $enrollment->isActive())
                                    <tr>
                                        <th scope="row" class="sge-attendance-student-column">
                                            {{ $enrollment->student?->full_name }}
                                            @if ($enrollmentLocked)
                                                <span class="badge badge-secondary ml-1">{{ $enrollment->statusLabel() }}</span>
                                            @endif
                                        </th>
                                        @foreach ($days as $day)
                                            @php($date = $day->date->toDateString())
                                            @php($record = $records->get($date))
                                            @php($entry = $record?->entries->firstWhere('student_enrollment_id', $enrollment->id))
                                            @php($presence = old('attendance.'.$date.'.'.$enrollment->id, $entry?->lesson_presence ?? []))
                                            @php($justified = ($justifications->where('student_enrollment_id', $enrollment->id))->contains(fn ($justification) => $justification->appliesTo($date)))
                                            <td class="text-center {{ $justified ? 'sge-attendance-justified' : '' }}" data-attendance-cell data-date="{{ $date }}">
                                                <div class="sge-attendance-checks" aria-label="Presenças de {{ $enrollment->student?->full_name }} em {{ $day->date->format('d/m/Y') }}">
                                                    @for ($lesson = 1; $lesson <= 24; $lesson++)
                                                        @php($isPresent = array_is_list((array) $presence) ? (bool) data_get($presence, $lesson - 1, false) : (bool) data_get($presence, $lesson, false))
                                                        <span data-lesson-checkbox="{{ $lesson }}">
                                                            <input id="attendance_{{ $date }}_{{ $enrollment->id }}_{{ $lesson }}" name="attendance[{{ $date }}][{{ $enrollment->id }}][{{ $lesson }}]" type="checkbox" value="1" data-locked="{{ $enrollmentLocked ? '1' : '0' }}" @checked($isPresent) @disabled($enrollmentLocked)>
                                                            <label for="attendance_{{ $date }}_{{ $enrollment->id }}_{{ $lesson }}" title="Aula {{ $lesson }}"><span class="sr-only">Presente na aula {{ $lesson }}</span>{{ $lesson }}</label>
                                                        </span>
                                                    @endfor
                                                </div>
                                                @if ($justified)<span class="sr-only">Há justificativa de ausência neste dia.</span><i class="fas fa-notes-medical text-success small" aria-hidden="true"></i>@endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $days->count() + 1 }}">Nenhum estudante com matrícula ativa nesta turma e matriz.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap mt-3"><p class="small text-muted mb-2 mr-3">A folha começa em <strong>0 aulas</strong>. {{ $usesScheduledDiary ? 'A quantidade máxima segue o horário da turma.' : 'Informe a quantidade de aulas realizadas em cada dia.' }} O ícone de livro indica que o conteúdo daquele dia foi lançado.</p><button class="btn btn-primary mb-2" type="submit" @disabled($enrollments->isEmpty())><i class="fas fa-save mr-1" aria-hidden="true"></i>Salvar frequências</button></div>
                </form>
            @endif
        </div>
    </section>
    @if($usesScheduledDiary && $totalPages > 1)<nav class="d-flex justify-content-between align-items-center" aria-label="Navegação dos dias previstos">@if($page > 1)<a class="btn btn-outline-secondary btn-sm" href="{{ route('teacher-diaries.attendance', [$schoolClass, $component, 'period' => $period->id, 'page' => $page - 1]) }}"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>Anteriores</a>@else<span></span>@endif<span class="small text-muted">Página {{ $page }} de {{ $totalPages }} · {{ $scheduledDayCount }} dias previstos</span>@if($page < $totalPages)<a class="btn btn-outline-secondary btn-sm" href="{{ route('teacher-diaries.attendance', [$schoolClass, $component, 'period' => $period->id, 'page' => $page + 1]) }}">Próximos<i class="fas fa-arrow-right ml-1" aria-hidden="true"></i></a>@endif</nav>@endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-attendance-sheet]').forEach((form) => {
            const syncDate = (date) => {
                const lessonCount = Number(form.querySelector(`[data-lesson-count][data-date="${date}"]`).value);
                form.querySelectorAll(`[data-attendance-cell][data-date="${date}"]`).forEach((cell) => {
                    cell.querySelectorAll('[data-lesson-checkbox]').forEach((lesson) => {
                        const visible = Number(lesson.dataset.lessonCheckbox) <= lessonCount;
                        lesson.hidden = !visible;
                        const input = lesson.querySelector('input');
                        input.disabled = !visible || input.dataset.locked === '1';
                    });
                });
            };
            form.querySelectorAll('[data-lesson-count]').forEach((field) => {
                field.addEventListener('change', () => syncDate(field.dataset.date));
                syncDate(field.dataset.date);
            });
        });
    </script>
@endpush
