@php
    $summary = $diary['summary'];
    $summaryId = 'diary-summary-'.$diary['assignment']->id;
    $timeliness = $summary['timeliness'];
    $hasDelay = $timeliness['content']['missing'] > 0 || $timeliness['attendance']['missing'] > 0;
    $state = $timeliness['expected_today'] === null || $timeliness['expected_today'] === 0 ? 'neutral'
        : ($hasDelay ? ($timeliness['urgency'] >= 65 ? 'critical' : 'warning') : 'success');
    $paceLabel = $timeliness['expected_today'] === null ? __('Previsão indisponível')
        : ($timeliness['expected_today'] === 0 ? __('Sem aulas previstas até hoje')
        : ($hasDelay ? ($state === 'critical' ? __('Atraso crítico nos lançamentos') : __('Lançamentos em atraso')) : __('Lançamentos em dia')));
    $shortLabel = ['neutral' => $timeliness['expected_today'] === null ? __('Sem previsão') : __('Aguardando'), 'success' => __('Em dia'), 'warning' => __('Atenção'), 'critical' => __('Crítico')][$state];
@endphp
<div class="sge-diary-overview" aria-label="{{ __('Acompanhamento dos lançamentos') }}" data-diary-state="{{ $state }}">
    <div class="sge-diary-progress-grid">
        @foreach(['content' => __('Conteúdo'), 'attendance' => __('Frequência')] as $kind => $label)
            @php
                $metric = $summary[$kind];
                $pace = $timeliness[$kind];
                $percentage = $metric['percentage'];
                $percentageLabel = $percentage === null ? '—' : number_format($percentage, 1, ',', '.').'%';
                $valueText = $percentage === null
                    ? __(':registered aulas registradas; previsão indisponível.', ['registered' => $metric['registered']])
                    : __(':registered de :planned aulas previstas; :percent.', ['registered' => $metric['registered'], 'planned' => $summary['planned'], 'percent' => $percentageLabel]);
            @endphp
            <div class="sge-diary-progress-metric sge-diary-progress-{{ $kind }}">
                <div class="sge-diary-progress-label">
                    <strong><i class="fas {{ $kind === 'content' ? 'fa-book' : 'fa-clipboard-check' }}" aria-hidden="true"></i> {{ $label }}</strong>
                    <span class="sge-diary-progress-numbers">{{ $metric['registered'] }}/{{ $summary['planned'] ?? '—' }} <span class="sr-only">{{ __('aulas') }}</span> · {{ $percentageLabel }}</span>
                    @if($pace['missing'] > 0)
                        <i class="fas fa-clock sge-diary-pending-icon" aria-hidden="true" title="{{ __(':count aulas por registrar até hoje', ['count' => $pace['missing']]) }}"></i>
                    @endif
                </div>
                <progress max="100" value="{{ min(100, $percentage ?? 0) }}"
                    aria-label="{{ $label }} · {{ $diary['component']->name }} · {{ $diary['class']->name }}"
                    aria-valuetext="{{ $valueText }} {{ $paceLabel }}{{ $pace['missing'] > 0 ? '; '.__(':count aulas por registrar até hoje', ['count' => $pace['missing']]) : '' }}">{{ $percentageLabel }}</progress>
                @if($percentage > 100)<span class="sr-only">{{ __('Acima do previsto') }}</span>@endif
            </div>
        @endforeach
    </div>
    <details class="sge-diary-issues">
        <summary class="sge-diary-compact-status" title="{{ __('Detalhes e pendências') }}" aria-label="{{ $paceLabel }}; {{ __('Inconsistências') }}: {{ $summary['issue_count'] }}; {{ __('Detalhes e pendências') }}">
            <span class="sge-diary-traffic" role="img" aria-label="{{ $paceLabel }}" data-state="{{ $state }}">
                @foreach(['success' => 'fa-check', 'warning' => 'fa-exclamation', 'critical' => 'fa-times'] as $signal => $icon)
                    <span class="sge-diary-light sge-diary-light-{{ $signal }} {{ $state === $signal ? 'is-active' : '' }}" aria-hidden="true">@if($state === $signal)<i class="fas {{ $icon }}"></i>@endif</span>
                @endforeach
            </span>
            <span class="sge-diary-state-label">{{ $shortLabel }}</span>
            @if($timeliness['ends_at'])
                <time datetime="{{ $timeliness['ends_at'] }}" title="{{ __('Encerramento previsto: :date', ['date' => \Carbon\Carbon::parse($timeliness['ends_at'])->format('d/m/Y')]) }}">
                    <i class="far fa-calendar-alt" aria-hidden="true"></i> {{ \Carbon\Carbon::parse($timeliness['ends_at'])->format('d/m/Y') }}
                </time>
            @endif
            <span class="sge-diary-issue-count" title="{{ __('Inconsistências') }}: {{ $summary['issue_count'] }}">
                <i class="fas {{ $summary['issue_count'] > 0 ? 'fa-exclamation-triangle' : 'fa-info-circle' }}" aria-hidden="true"></i>
                <span class="sr-only">{{ __('Inconsistências') }}:</span> {{ $summary['issue_count'] }}
            </span>
            <i class="fas fa-chevron-down sge-diary-details-chevron" aria-hidden="true"></i>
        </summary>
        <div id="{{ $summaryId }}-details" class="sge-diary-issues-body">
            <p class="small mb-2">{{ $diary['course']->name }} · {{ $summary['source'] === 'schedule' ? __('Previsão pelo horário e calendário letivo') : ($summary['source'] === 'matrix' ? __('Previsão estimada pela carga horária da matriz') : __('Previsão de aulas não definida')) }}</p>
            @if($hasDelay && $summary['content']['registered'] === 0 && $summary['attendance']['registered'] === 0)<p class="small mb-2">{{ __('Nenhum lançamento de conteúdo ou frequência.') }}</p>@endif
            @if($timeliness['expected_today'] !== null)<p class="small mb-2">{{ __('Até hoje: :count aulas previstas', ['count' => $timeliness['expected_today']]) }}</p>@endif
            <ul class="small">
                @foreach(['content' => __('Conteúdo'), 'attendance' => __('Frequência')] as $kind => $label)
                    @if($timeliness[$kind]['missing'] > 0)<li>{{ $label }}: <strong>{{ __(':count aulas por registrar até hoje', ['count' => $timeliness[$kind]['missing']]) }}</strong></li>@endif
                @endforeach
                <li>{{ __('Dias com frequência sem conteúdo') }}: <strong>{{ $summary['issues']['attendance_without_content'] }}</strong></li>
                <li>{{ __('Dias com conteúdo sem frequência') }}: <strong>{{ $summary['issues']['content_without_attendance'] }}</strong></li>
                <li>{{ __('Notas pendentes em períodos encerrados') }}: <strong>{{ $summary['issues']['missing_grades'] }}</strong></li>
            </ul>
            <div class="d-flex flex-wrap sge-diary-issue-links">
                @foreach($summary['periods'] as $periodSummary)
                    @if($periodSummary['attendance_without_content'] + $periodSummary['content_without_attendance'] + $periodSummary['missing_grades'] > 0)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('teacher-diaries.show', [$diary['class'], $diary['component'], 'period' => $periodSummary['period']->id]) }}#section-situacao">{{ $periodSummary['period']->name }} · {{ __('Ver pendências') }}</a>
                    @endif
                @endforeach
            </div>
        </div>
    </details>
</div>
