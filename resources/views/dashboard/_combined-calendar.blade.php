@php
    $month = $month ?? null;
@endphp

@if ($month)
    <section class="sge-calendar-month sge-calendar-month-combined" aria-label="Calendário combinado de {{ $month['label'] }}">
        <header class="sge-calendar-month-header d-flex align-items-center justify-content-between">
            <span>{{ $month['label'] }}</span>
            <span class="small font-weight-normal">
                {{ $month['school_days_count'] }} letivos · {{ $month['birthdays_count'] }} aniversários
            </span>
        </header>
        <div class="sge-calendar-weekdays" aria-hidden="true">
            <span>D</span>
            <span>S</span>
            <span>T</span>
            <span>Q</span>
            <span>Q</span>
            <span>S</span>
            <span>S</span>
        </div>
        @foreach ($month['weeks'] as $week)
            <div class="sge-calendar-week">
                @foreach ($week as $entry)
                    @php
                        $classes = trim('sge-calendar-day sge-calendar-day-'.$entry['primary_type']
                            .(! $entry['in_month'] ? ' is-outside-month' : '')
                            .($entry['period_class'] ? ' '.$entry['period_class'] : '')
                            .($entry['counts_as_school_day'] ? ' is-school-day' : '')
                            .($entry['has_birthdays'] ? ' has-birthday' : ''));
                    @endphp

                    <div class="{{ $classes }}" title="{{ $entry['date']->format('d/m/Y') }} - {{ $entry['label'] }}">
                        <span class="sge-calendar-date">{{ $entry['date']->format('j') }}</span>
                        <span class="sge-calendar-code">
                            {{ $entry['codes']->isNotEmpty() ? $entry['codes']->join('/') : '-' }}
                        </span>

                        @if ($entry['counts_as_school_day'])
                            <span class="sge-calendar-mini-mark sge-calendar-mini-mark-school" aria-label="Dia letivo">L</span>
                        @endif
                        @if ($entry['has_birthdays'])
                            <span class="sge-calendar-birthday-dot" aria-label="{{ $entry['birthdays']->count() }} aniversariante(s)"></span>
                        @endif

                        <span class="sr-only">{{ $entry['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>
@endif
