<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 202px 22px 96px; }
body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
@include('reports.partials.letterhead-styles')
.letterhead-repeat { top: -188px; }
.letterhead-line { line-height: 1.05; margin-top: 0; }
.document-title { font-size: 14px; line-height: 1; margin-top: 3px; }
table { width: 100%; border-collapse: collapse; margin: 7px 0 11px; table-layout: fixed; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td { border: .5px solid #999; padding: 4px; vertical-align: middle; }
th { background: #eee; }
.number { text-align: center; }
.note { font-size: 11px; color: #444; }
</style></head><body>
@include('reports.partials.letterhead', ['title' => 'Panorama de aprovação da turma', 'showTechnicalRegulation' => false])
<p><strong>{{ $schoolClass->name }}</strong> · {{ $academicYear->referenceYearsLabel() }} · {{ $academicYear->school?->name }}</p>
<p>Meta anual por componente: <strong>{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos</strong>.
Frequência mínima: {{ $academicYear->minimum_attendance_percentage }}%.</p>
<p class="note">Panorama dos lançamentos atuais. As notas individuais abaixo são exibidas somente para períodos já encerrados. A pontuação inclui médias parciais e pode mudar com novas notas ou recuperação. Atingir a meta de pontos não significa aprovação final: também são necessários frequência mínima e fechamento dos períodos. Períodos sem notas não são tratados como nota zero.</p>
@forelse($reports as $report)
@php
    $endedPeriods = $report['annualComponents']
        ->flatMap(fn ($summary) => $summary['periods'])
        ->filter(fn ($item) => $item['period']->ends_at?->copy()->endOfDay()->isPast())
        ->map(fn ($item) => $item['period'])
        ->unique('id')
        ->sortBy('position')
        ->values();
    $periodWidth = $endedPeriods->isEmpty() ? 0 : 20 / $endedPeriods->count();
@endphp
<table>
<thead><tr><th colspan="{{ 5 + $endedPeriods->count() }}" style="text-align:left">{{ $loop->iteration }}. {{ $report['student']->full_name }}<br>
<span class="note">Frequência anual: {{ $report['annualAttendance']['percentage'] === null ? 'Sem registros' : number_format($report['annualAttendance']['percentage'], 1, ',', '.').'%' }}</span></th></tr>
<tr>
<th style="width:32%">Componente</th>
@foreach($endedPeriods as $endedPeriod)
<th style="width:{{ $periodWidth }}%" title="{{ $endedPeriod->name }}">{{ preg_replace(['/\s+Bimestre$/iu', '/\s+Trimestre$/iu', '/\s+Semestre$/iu'], [' Bim.', ' Trim.', ' Sem.'], $endedPeriod->name) }}</th>
@endforeach
<th style="width:9%">Pontos atuais</th><th style="width:9%">Faltam pontos</th><th style="width:12%">Períodos completos</th><th style="width:18%">Situação da pontuação</th>
</tr></thead>
<tbody>
@forelse($report['annualComponents'] as $summary)
@php
    $hasScore = $summary['periods']->contains(fn ($item) => ($item['average']['value'] ?? null) !== null);
    $missing = max(0, $report['passingPoints'] - $summary['points']);
@endphp
<tr><td>{{ $summary['component']->name }}<br><span class="note">{{ $summary['component']->course?->name }}</span></td>
@foreach($endedPeriods as $endedPeriod)
@php
    $periodSummary = $summary['periods']->first(fn ($item) => $item['period']->id === $endedPeriod->id);
    $periodScore = $periodSummary['average']['value'] ?? null;
@endphp
<td class="number">{{ $periodScore === null ? '—' : number_format($periodScore, 1, ',', '.') }}</td>
@endforeach
<td class="number">{{ $hasScore ? number_format($summary['points'], 1, ',', '.') : '—' }}</td>
<td class="number">{{ number_format($missing, 1, ',', '.') }}</td>
<td class="number">{{ $summary['complete_periods'] }}/{{ $summary['total_periods'] }}</td>
<td>{{ ! $hasScore ? 'Sem notas' : ($missing > 0 ? 'Abaixo da meta' : 'Meta atingida') }}</td></tr>
@empty
<tr><td colspan="{{ 5 + $endedPeriods->count() }}">Sem componentes vinculados.</td></tr>
@endforelse
</tbody></table>
@empty
<p>Nenhum aluno com matrícula ativa nesta turma.</p>
@endforelse
@include('reports.partials.document-footer')
</body></html>
