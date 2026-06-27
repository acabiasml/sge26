@extends('layouts.app')

@section('title', 'Horários - '.$class->name)
@section('page-title', 'Horários: '.$class->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.show', [$academicYear, $class]) }}" aria-label="Voltar à turma" title="Voltar à turma">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
    @if ($schedule)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.schedules.pdf', [$academicYear, $class, 'schedule' => $schedule->id]) }}" aria-label="Imprimir horário da turma {{ $class->name }}" title="Imprimir horário da turma">
            <i class="fas fa-file-pdf" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4 mb-4">
            <section class="card shadow h-100 sge-schedule-context" aria-labelledby="schedule-context-title">
                <div class="card-header py-3"><h2 id="schedule-context-title" class="h6 m-0 font-weight-bold text-primary">Contexto</h2></div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Escola</dt><dd>{{ $academicYear->school?->name }}</dd>
                        <dt>Ano letivo</dt><dd>{{ $academicYear->name }}</dd>
                        <dt>Turma</dt><dd>{{ $class->name }}</dd>
                        <dt>Hora-aula de referência</dt><dd>{{ $classMinutes }} minutos</dd>
                        <dt>Dias no horário</dt><dd>{{ implode(', ', $weekdays) }}</dd>
                        <dt>Matrizes</dt><dd>{{ $class->courses->pluck('name')->join(' + ') }}</dd>
                    </dl>
                </div>
            </section>
        </div>
        <div class="col-lg-8 mb-4">
            <section class="card shadow h-100" aria-labelledby="new-schedule-title">
                <div class="card-header py-3"><h2 id="new-schedule-title" class="h6 m-0 font-weight-bold text-primary">Nova versão de horário</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('academic-years.classes.schedules.store', [$academicYear, $class]) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-5 form-group"><label for="schedule_name">Nome</label><input id="schedule_name" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Horário regular" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-3 form-group"><label for="schedule_starts_at">Válido a partir de</label><input id="schedule_starts_at" name="starts_at" type="date" min="{{ $class->starts_at?->toDateString() }}" max="{{ $class->ends_at?->toDateString() }}" class="form-control @error('starts_at') is-invalid @enderror" required>@error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4 form-group"><label for="schedule_ends_at">Válido até</label><input id="schedule_ends_at" name="ends_at" type="date" min="{{ $class->starts_at?->toDateString() }}" max="{{ $class->ends_at?->toDateString() }}" class="form-control @error('ends_at') is-invalid @enderror"><small class="form-text text-muted">Deixe vazio se for indeterminado.</small>@error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        </div>
                        <div class="form-group"><label for="schedule_notes">Observações</label><input id="schedule_notes" name="notes" class="form-control" placeholder="Opcional"></div>
                        <button class="btn btn-primary" type="submit"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Criar versão</button>
                    </form>
                </div>
            </section>
        </div>
    </div>

    @if($schedules->isNotEmpty())
        <section class="card shadow mb-4" aria-labelledby="schedule-versions-title">
            <div class="card-header py-3"><h2 id="schedule-versions-title" class="h6 m-0 font-weight-bold text-primary">Versões cadastradas</h2></div>
            <div class="card-body">
                <div class="sge-schedule-version-list">
                    @foreach($schedules as $availableSchedule)
                        <a class="sge-schedule-version {{ $schedule?->id === $availableSchedule->id ? 'is-active' : '' }}" href="{{ route('academic-years.classes.schedules.index', [$academicYear, $class, 'schedule' => $availableSchedule->id]) }}">
                            <span><strong>{{ $availableSchedule->name }}</strong><small>{{ $availableSchedule->starts_at?->format('d/m/Y') }} até {{ $availableSchedule->ends_at?->format('d/m/Y') ?? 'indeterminado' }}</small></span>
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($schedule)
        <div class="row">
            <div class="col-xl-4 mb-4">
                <section class="card shadow" aria-labelledby="new-slot-title">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h2 id="new-slot-title" class="h6 m-0 font-weight-bold text-primary">Novo bloco</h2>
                        <form method="POST" action="{{ route('academic-years.classes.schedules.destroy', [$academicYear, $class, $schedule]) }}" onsubmit="return confirm('Remover esta versão de horário e todos os seus blocos?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Excluir versão de horário" title="Excluir versão">
                                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('academic-years.classes.schedules.slots.store', [$academicYear, $class, $schedule]) }}" data-schedule-slot-form data-class-minutes="{{ $classMinutes }}">
                            @csrf
                            @include('school-class-schedules._slot-fields', [
                                'prefix' => 'slot',
                                'slot' => null,
                                'assignments' => $assignments,
                                'assignmentUsage' => $assignmentUsage,
                                'weekdays' => $weekdays,
                            ])
                            <p class="small text-muted">Cada bloco de aula conta como uma aula semanal do componente, mesmo com duração menor que a hora-aula de referência.</p>
                            <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Adicionar bloco</button>
                        </form>
                    </div>
                </section>

                <section class="card shadow mt-4" aria-labelledby="weekly-limits-title">
                    <div class="card-header py-3"><h2 id="weekly-limits-title" class="h6 m-0 font-weight-bold text-primary">Aulas por semana</h2></div>
                    <div class="card-body">
                        @foreach($assignments as $assignment)
                            @php($used = (int) $assignmentUsage->get($assignment->id, 0))
                            @php($limit = (int) ($assignment->component?->weekly_lessons ?? 0))
                            <div class="sge-schedule-limit-row">
                                <span>
                                    <strong>{{ $assignment->component?->name }}</strong>
                                    <small>{{ $assignment->teacher?->full_name ?? 'Docência não definida' }}</small>
                                </span>
                                <span class="badge badge-{{ $limit > 0 && $used >= $limit ? 'success' : 'light' }} border">{{ $used }}/{{ $limit }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
            <div class="col-xl-8 mb-4">
                <section class="card shadow" aria-labelledby="weekly-schedule-title">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h2 id="weekly-schedule-title" class="h6 m-0 font-weight-bold text-primary">{{ $schedule->name }}</h2>
                            <span class="small text-muted">Clique em um bloco para editar. Janela: 06:00-22:00.</span>
                        </div>
                        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.classes.schedules.pdf', [$academicYear, $class, 'schedule' => $schedule->id]) }}" aria-label="Imprimir horário" title="Imprimir horário">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="sge-week-schedule-wrap">
                            <div class="sge-week-schedule" style="grid-template-columns: repeat({{ count($weekdays) }}, minmax(9.5rem, 1fr)); min-width: {{ count($weekdays) * 9.5 }}rem;">
                                @foreach($weekdays as $weekday => $weekdayLabel)
                                    <section class="sge-week-schedule-day">
                                        <h3>{{ $weekdayLabel }}</h3>
                                        <div class="sge-week-schedule-track">
                                            @for($hour = 6; $hour <= 22; $hour++)
                                                <span class="sge-week-schedule-hour" style="top: {{ (($hour - 6) / 16) * 100 }}%">{{ sprintf('%02d:00', $hour) }}</span>
                                            @endfor

                                            @foreach($schedule->slots->where('weekday', $weekday)->sortBy('starts_at') as $slot)
                                                @php($startParts = explode(':', $slot->starts_at))
                                                @php($endParts = explode(':', $slot->ends_at))
                                                @php($startMinutes = ((int) $startParts[0] * 60) + (int) $startParts[1])
                                                @php($endMinutes = ((int) $endParts[0] * 60) + (int) $endParts[1])
                                                @php($top = (($startMinutes - 360) / 960) * 100)
                                                @php($height = (($endMinutes - $startMinutes) / 960) * 100)
                                                @php($teacher = $slot->componentAssignment?->teacher)
                                                @php($colors = \App\Support\ScheduleTeacherColor::for($teacher?->id, $teacher?->full_name))
                                                <article class="sge-week-schedule-slot sge-week-schedule-slot-{{ $slot->type }}" style="top: {{ $top }}%; height: {{ $height }}%; border-left-color: {{ $colors['border'] }}; background: {{ $slot->type === \App\Models\SchoolClassScheduleSlot::TYPE_BREAK ? 'rgba(240, 144, 48, .22)' : $colors['background'] }};">
                                                    <button class="sge-week-schedule-edit" type="button" data-toggle="modal" data-target="#edit-slot-{{ $slot->id }}" aria-label="Editar bloco {{ $slot->type === 'aula' ? $slot->componentAssignment?->component?->name : $slot->label }}">
                                                        <strong>{{ $slot->type === 'aula' ? $slot->componentAssignment?->component?->name : $slot->label }}</strong>
                                                        @if($slot->type === 'aula')
                                                            <span>{{ $teacher?->full_name ?? 'Docência não definida' }}</span>
                                                        @endif
                                                        <small>{{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</small>
                                                    </button>
                                                    <form method="POST" action="{{ route('academic-years.classes.schedules.slots.destroy', [$academicYear, $class, $schedule, $slot]) }}" onsubmit="return confirm('Remover este bloco?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" aria-label="Remover bloco {{ $slot->type === 'aula' ? $slot->componentAssignment?->component?->name : $slot->label }}" title="Remover bloco"><i class="fas fa-times" aria-hidden="true"></i></button>
                                                    </form>
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        @foreach($schedule->slots as $slot)
            <div class="modal fade" id="edit-slot-{{ $slot->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-slot-title-{{ $slot->id }}" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('academic-years.classes.schedules.slots.update', [$academicYear, $class, $schedule, $slot]) }}" data-schedule-slot-form data-class-minutes="{{ $classMinutes }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h6" id="edit-slot-title-{{ $slot->id }}">Editar bloco</h2>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                            </div>
                            <div class="modal-body">
                                @include('school-class-schedules._slot-fields', [
                                    'prefix' => 'slot_'.$slot->id,
                                    'slot' => $slot,
                                    'assignments' => $assignments,
                                    'assignmentUsage' => $assignmentUsage,
                                    'weekdays' => $weekdays,
                                ])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1" aria-hidden="true"></i>Salvar bloco</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="sge-empty-state"><i class="fas fa-calendar-plus" aria-hidden="true"></i><p>Crie uma versão de horário para começar a organizar a semana desta turma.</p></div>
    @endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-schedule-slot-form]').forEach((form) => {
    const type = form.querySelector('[data-slot-type]');
    const component = form.querySelector('[data-slot-component]');
    const label = form.querySelector('[data-slot-label]');
    const start = form.querySelector('[data-slot-start]');
    const end = form.querySelector('[data-slot-end]');
    const sync = () => {
        const isClass = type.value === 'aula';
        component.hidden = !isClass;
        component.querySelector('select').disabled = !isClass;
        label.hidden = isClass;
        label.querySelector('input').disabled = isClass;
        if (isClass && start.value && !end.dataset.touched) {
            const [hours, minutes] = start.value.split(':').map(Number);
            const total = (hours * 60) + minutes + Number(form.dataset.classMinutes);
            end.value = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
        }
    };
    end?.addEventListener('input', () => { end.dataset.touched = '1'; });
    type?.addEventListener('change', sync);
    start?.addEventListener('change', sync);
    sync();
});
</script>
@endpush
