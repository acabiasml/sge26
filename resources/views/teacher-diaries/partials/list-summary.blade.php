@php
    $summary = $diary['summary'];
    $summaryId = 'diary-summary-'.$diary['assignment']->id;
    $timeliness = $summary['timeliness'];
    $hasDelay = $timeliness['content']['missing'] > 0 || $timeliness['attendance']['missing'] > 0;
    $paceLabel = $timeliness['expected_today'] === null ? __('Previsão indisponível')
        : ($timeliness['expected_today'] === 0 ? __('Sem aulas previstas até hoje')
        : ($hasDelay ? ($timeliness['urgency'] >= 65 ? __('Atraso crítico nos lançamentos') : __('Lançamentos em atraso')) : __('Lançamentos em dia')));
@endphp
<div class="sge-diary-overview" aria-label="{{ __('Acompanhamento dos lançamentos') }}">
    <div id="{{ $summaryId }}-deadline" class="sge-diary-deadline mb-3">
        <strong><i class="fas {{ $hasDelay ? 'fa-exclamation-triangle' : 'fa-clock' }} mr-1" aria-hidden="true"></i>{{ $paceLabel }}</strong>
        @if($timeliness['ends_at'])<span>{{ __('Encerramento previsto: :date', ['date' => \Carbon\Carbon::parse($timeliness['ends_at'])->format('d/m/Y')]) }}</span>@endif
        @if($timeliness['expected_today'] !== null)<span>{{ __('Até hoje: :count aulas previstas', ['count' => $timeliness['expected_today']]) }}</span>@endif
        @if($hasDelay && $summary['content']['registered'] === 0 && $summary['attendance']['registered'] === 0)
            <span class="sge-diary-no-entries">{{ __('Nenhum lançamento de conteúdo ou frequência.') }}</span>
        @endif
    </div>
    <p id="{{ $summaryId }}" class="small text-muted mb-2">
        {{ __('Total do diário') }} · {{ $summary['source'] === 'schedule' ? __('Previsão pelo horário e calendário letivo') : ($summary['source'] === 'matrix' ? __('Previsão estimada pela carga horária da matriz') : __('Previsão de aulas não definida')) }}
    </p>
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
            <div class="sge-diary-progress-metric sge-diary-progress-{{ $kind }} {{ $pace['missing'] > 0 ? 'sge-diary-metric-delayed' : '' }}"
                style="--sge-diary-warning-strength: {{ min(100, $pace['urgency'] * 2) }}%; --sge-diary-danger-strength: {{ max(0, ($pace['urgency'] - 50) * 2) }}%; --sge-diary-tint: {{ 3 + round($pace['urgency'] * .25) }}%;">
                <div class="sge-diary-progress-label">
                    <strong><i class="fas {{ $kind === 'content' ? 'fa-book' : 'fa-clipboard-check' }} mr-1" aria-hidden="true"></i>{{ $label }}</strong>
                    <span>{{ $percentageLabel }}</span>
                </div>
                <progress max="100" value="{{ min(100, $percentage ?? 0) }}"
                    aria-label="{{ $label }} · {{ $diary['component']->name }} · {{ $diary['class']->name }}"
                    aria-valuetext="{{ $valueText }}" aria-describedby="{{ $summaryId }} {{ $summaryId }}-deadline{{ $pace['missing'] > 0 ? ' '.$summaryId.'-'.$kind.'-pace' : '' }}">{{ $percentageLabel }}</progress>
                <p class="small mb-0">{{ $metric['registered'] }} / {{ $summary['planned'] ?? '—' }} {{ __('aulas') }}@if($percentage > 100) <span class="text-warning">· {{ __('Acima do previsto') }}</span>@endif</p>
                @if($pace['missing'] > 0)
                    <p id="{{ $summaryId }}-{{ $kind }}-pace" class="sge-diary-pace-note mb-0"><strong>{{ __(':count aulas por registrar até hoje', ['count' => $pace['missing']]) }}</strong></p>
                @endif
            </div>
        @endforeach
    </div>
    <details class="sge-diary-issues mt-3">
        <summary class="{{ $summary['issue_count'] > 0 ? 'text-danger' : ($hasDelay || $timeliness['expected_today'] === null || $timeliness['expected_today'] === 0 ? 'text-muted' : 'text-success') }}">
            <i class="fas {{ $summary['issue_count'] > 0 ? 'fa-exclamation-triangle' : ($hasDelay || $timeliness['expected_today'] === null || $timeliness['expected_today'] === 0 ? 'fa-info-circle' : 'fa-check-circle') }} mr-1" aria-hidden="true"></i>
            {{ __('Inconsistências') }}: {{ $summary['issue_count'] }}
        </summary>
        <div class="sge-diary-issues-body">
            <p class="small text-muted">{{ __('O atraso considera as aulas previstas até hoje e o prazo de encerramento. As inconsistências comparam os registros existentes.') }}</p>
            <p class="small text-muted">{{ __('Conteúdo considera as aulas do horário ou da frequência registrada no dia. Notas pendentes são contadas apenas nos períodos encerrados.') }}</p>
            <ul class="small">
                <li>{{ __('Dias com frequência sem conteúdo') }}: <strong>{{ $summary['issues']['attendance_without_content'] }}</strong></li>
                <li>{{ __('Dias com conteúdo sem frequência') }}: <strong>{{ $summary['issues']['content_without_attendance'] }}</strong></li>
                <li>{{ __('Notas pendentes em períodos encerrados') }}: <strong>{{ $summary['issues']['missing_grades'] }}</strong></li>
            </ul>
            <div class="d-flex flex-wrap sge-diary-issue-links">
                @foreach($summary['periods'] as $periodSummary)
                    @if($periodSummary['attendance_without_content'] + $periodSummary['content_without_attendance'] + $periodSummary['missing_grades'] > 0)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('teacher-diaries.show', [$diary['class'], $diary['component'], 'period' => $periodSummary['period']->id]) }}#section-situacao">
                            {{ $periodSummary['period']->name }} · {{ __('Ver pendências') }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </details>
</div>
