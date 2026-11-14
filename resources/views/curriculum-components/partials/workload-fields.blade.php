@php
    $workloadComponent = $workloadComponent ?? null;
    $workloadPeriods = $workloadPeriods ?? $academicYear->periods;
    $course->loadMissing('startsPeriod', 'endsPeriod');
    $workloadPeriods = $workloadPeriods->filter(fn ($period) => (! $course->startsPeriod || $period->position >= $course->startsPeriod->position) && (! $course->endsPeriod || $period->position <= $course->endsPeriod->position));
    $workloadMode = old('workload_mode', $workloadComponent?->hasPeriodLoads() ? 'periods' : ($workloadComponent?->workload_hours !== null ? 'workload_hours' : 'weekly_lessons'));
@endphp
<fieldset class="form-group border rounded p-3" data-workload-choice data-period-weeks="{{ ($academicYear->minimum_school_days ?: 200) / 5 / max(1, $academicYear->periods->count()) }}" data-lesson-minutes="{{ $course->class_hour_minutes }}">
    <legend class="h6 px-2 w-auto">{{ __('Como informar a carga horária?') }}</legend>
    <div class="d-flex flex-wrap mb-3" role="group" aria-label="{{ __('Forma de definição da carga horária') }}">
        @foreach (['weekly_lessons' => __('Aulas por semana'), 'workload_hours' => __('Total de horas'), 'periods' => __('Por período letivo')] as $value => $label)
            <div class="custom-control custom-radio mr-4 mb-2">
                <input class="custom-control-input" type="radio" id="{{ $workloadPrefix }}_workload_mode_{{ ['weekly_lessons' => 'weekly', 'workload_hours' => 'total', 'periods' => 'periods'][$value] }}" name="workload_mode" value="{{ $value }}" @checked($workloadMode === $value) required>
                <label class="custom-control-label" for="{{ $workloadPrefix }}_workload_mode_{{ ['weekly_lessons' => 'weekly', 'workload_hours' => 'total', 'periods' => 'periods'][$value] }}">{{ $label }}</label>
            </div>
        @endforeach
    </div>
    @if ($workloadComponent?->workload_hours !== null && $workloadComponent?->weekly_lessons !== null)
        <p class="text-muted">{{ __('O cálculo usa :hours horas totais', ['hours' => $workloadComponent->workload_hours]) }}</p>
    @endif
    <div data-workload-field="weekly_lessons">
        <label for="{{ $workloadPrefix }}_weekly_lessons">{{ __('Aulas por semana') }}</label>
        <input id="{{ $workloadPrefix }}_weekly_lessons" name="weekly_lessons" type="number" min="1" max="99" inputmode="numeric" class="form-control" value="{{ old('weekly_lessons', $workloadComponent?->weekly_lessons) }}">
    </div>
    <div data-workload-field="workload_hours">
        <label for="{{ $workloadPrefix }}_workload_hours">{{ __('Carga horária total') }}</label>
        <div class="input-group"><input id="{{ $workloadPrefix }}_workload_hours" name="workload_hours" type="number" min="1" max="99999" inputmode="numeric" class="form-control" value="{{ old('workload_hours', $workloadComponent?->workload_hours) }}"><div class="input-group-append"><span class="input-group-text">{{ __('horas') }}</span></div></div>
    </div>
    <div data-workload-field="periods">
        <p id="{{ $workloadPrefix }}_period_help" class="text-muted">{{ __('O componente continua único. Informe a quantidade semanal de cada período; use zero quando não houver aulas. Se necessário, informe a carga do período para manter o total aprovado.') }}</p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-2">
                <caption class="sr-only">{{ __('Distribuição das aulas e da carga horária por período') }}</caption>
                <thead><tr><th scope="col">{{ __('Período letivo') }}</th><th scope="col">{{ __('Aulas por semana') }}</th><th scope="col">{{ __('Carga do período (h), opcional') }}</th></tr></thead>
                <tbody>
                    @foreach ($workloadPeriods as $period)
                        <tr>
                            <th scope="row">{{ $period->name }}</th>
                            <td><label class="sr-only" for="{{ $workloadPrefix }}_period_{{ $period->id }}_weekly">{{ __('Aulas por semana') }} — {{ $period->name }}</label><input id="{{ $workloadPrefix }}_period_{{ $period->id }}_weekly" class="form-control" type="number" min="0" max="99" inputmode="numeric" name="period_loads[{{ $period->id }}][weekly_lessons]" data-period-weekly data-period-id="{{ $period->id }}" data-period-position="{{ $period->position }}" value="{{ old('period_loads.'.$period->id.'.weekly_lessons', $workloadComponent?->period_loads[$period->id]['weekly_lessons'] ?? ($workloadComponent?->weekly_lessons ?? 0)) }}" aria-describedby="{{ $workloadPrefix }}_period_help"></td>
                            <td><label class="sr-only" for="{{ $workloadPrefix }}_period_{{ $period->id }}_hours">{{ __('Carga horária') }} — {{ $period->name }}</label><input id="{{ $workloadPrefix }}_period_{{ $period->id }}_hours" class="form-control" type="number" min="0.01" max="99999" step="0.01" inputmode="decimal" name="period_loads[{{ $period->id }}][workload_hours]" data-period-hours data-period-position="{{ $period->position }}" value="{{ old('period_loads.'.$period->id.'.workload_hours', $workloadComponent?->period_loads[$period->id]['workload_hours'] ?? '') }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mb-0"><strong>{{ __('Carga total estimada') }}:</strong> <output data-period-total aria-live="polite">—</output> {{ __('horas') }}</p>
    </div>
</fieldset>
@once
    @push('scripts')
        <script src="{{ asset('template/js/sge-component-workload.js') }}" defer></script>
    @endpush
@endonce
