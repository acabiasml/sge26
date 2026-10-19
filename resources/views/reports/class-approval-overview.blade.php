<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 182px 22px 96px; }
body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
@include('reports.partials.letterhead-styles')
.letterhead-repeat { top: -168px; }
table { width: 100%; border-collapse: collapse; margin: 7px 0 11px; table-layout: fixed; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td { border: .5px solid #999; padding: 4px; vertical-align: middle; }
th { background: #eee; }
.number { text-align: center; }
.note { font-size: 11px; color: #444; }
.period-scores { line-height: 1.45; }
.period-score { display: inline-block; margin-right: 10px; white-space: nowrap; }
</style></head><body>
@include('reports.partials.letterhead', ['title' => 'Panorama de aprovação da turma', 'showTechnicalRegulation' => false])
<p><strong>{{ $schoolClass->name }}</strong> · {{ $academicYear->referenceYearsLabel() }} · {{ $academicYear->school?->name }}</p>
<p>Meta anual por componente: <strong>{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos</strong>.
Frequência mínima: {{ $academicYear->minimum_attendance_percentage }}%.</p>
<p class="note">Panorama dos lançamentos atuais. As notas individuais abaixo são exibidas somente para períodos já encerrados. A pontuação inclui médias parciais e pode mudar com novas notas ou recuperação. Atingir a meta de pontos não significa aprovação final: também são necessários frequência mínima e fechamento dos períodos. Períodos sem notas não são tratados como nota zero.</p>
@forelse($reports as $report)
<table>
<thead><tr><th colspan="6" style="text-align:left">{{ $loop->iteration }}. {{ $report['student']->full_name }}<br>
<span class="note">Frequência anual: {{ $report['annualAttendance']['percentage'] === null ? 'Sem registros' : number_format($report['annualAttendance']['percentage'], 1, ',', '.').'%' }}</span></th></tr>
<tr><th style="width:22%">Componente</th><th style="width:30%">Notas dos períodos encerrados</th><th style="width:11%">Pontos atuais</th><th style="width:11%">Faltam pontos</th><th style="width:12%">Períodos completos</th><th style="width:14%">Situação da pontuação</th></tr></thead>
<tbody>
@forelse($report['annualComponents'] as $summary)
@php
    $hasScore = $summary['periods']->contains(fn ($item) => ($item['average']['value'] ?? null) !== null);
    $missing = max(0, $report['passingPoints'] - $summary['points']);
    $endedPeriods = $summary['periods']->filter(
        fn ($item) => $item['period']->ends_at?->copy()->endOfDay()->isPast()
    );
@endphp
<tr><td>{{ $summary['component']->name }}<br><span class="note">{{ $summary['component']->course?->name }}</span></td>
<td class="period-scores">
@forelse($endedPeriods as $periodSummary)
<span class="period-score"><strong>{{ $periodSummary['period']->name }}:</strong> {{ ($periodSummary['average']['value'] ?? null) === null ? '—' : number_format($periodSummary['average']['value'], 1, ',', '.') }}</span>
@empty
<span class="note">Nenhum período encerrado.</span>
@endforelse
</td>
<td class="number">{{ $hasScore ? number_format($summary['points'], 1, ',', '.') : '—' }}</td>
<td class="number">{{ number_format($missing, 1, ',', '.') }}</td>
<td class="number">{{ $summary['complete_periods'] }}/{{ $summary['total_periods'] }}</td>
<td>{{ ! $hasScore ? 'Sem notas' : ($missing > 0 ? 'Abaixo da meta' : 'Meta atingida') }}</td></tr>
@empty
<tr><td colspan="6">Sem componentes vinculados.</td></tr>
@endforelse
</tbody></table>
@empty
<p>Nenhum aluno com matrícula ativa nesta turma.</p>
@endforelse
@include('reports.partials.document-footer')
</body></html>
