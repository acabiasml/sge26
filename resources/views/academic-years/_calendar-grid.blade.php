@php
    $months = $months ?? collect();
    $interactive = $interactive ?? false;
@endphp

<div class="sge-calendar-grid">
    @foreach ($months as $month)
        <section class="sge-calendar-month" aria-label="Calendário de {{ $month['label'] }}">
            <header class="sge-calendar-month-header">{{ $month['label'] }}</header>
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
                            $day = $entry['day'];
                            $type = $day?->type ?? 'empty';
                            $classes = trim('sge-calendar-day sge-calendar-day-'.$type
                                .(! $entry['in_month'] ? ' is-outside-month' : '')
                                .(! $entry['in_academic_year'] ? ' is-outside-year' : '')
                                .($entry['period_class'] ? ' '.$entry['period_class'] : ''));
                        @endphp

                        @if ($interactive && $entry['in_academic_year'])
                            <button
                                type="button"
                                class="{{ $classes }}"
                                data-calendar-day="{{ $entry['date']->toDateString() }}"
                                data-calendar-title="{{ $entry['label'] }}"
                                data-calendar-type="{{ $day?->type ?? '' }}"
                                data-calendar-counts-as-school-day="{{ $day?->counts_as_school_day ? '1' : '0' }}"
                                data-calendar-day-title="{{ $day?->title ?? '' }}"
                                data-calendar-description="{{ $day?->description ?? '' }}"
                                title="{{ $entry['date']->format('d/m/Y') }} - {{ $entry['label'] }}"
                            >
                                <span class="sge-calendar-date">{{ $entry['date']->format('j') }}</span>
                                <span class="sge-calendar-code">{{ $entry['code'] ?: '-' }}</span>
                                @if ($day?->title)
                                    <span class="sr-only">{{ $day->title }}</span>
                                @endif
                            </button>
                        @else
                            <div class="{{ $classes }}" title="{{ $entry['date']->format('d/m/Y') }} - {{ $entry['label'] }}">
                                <span class="sge-calendar-date">{{ $entry['date']->format('j') }}</span>
                                <span class="sge-calendar-code">{{ $entry['code'] ?: '-' }}</span>
                                @if ($day?->title)
                                    <span class="sr-only">{{ $day->title }}</span>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </section>
    @endforeach
</div>
