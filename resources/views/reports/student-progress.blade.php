<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 165px 24px 96px; }
body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; color: #111; line-height: 1.25; }
@include('reports.partials.letterhead-styles')
.letterhead-repeat { top: -151px; }
.context { margin: 18px 0 9px; }
.summary { width: 100%; margin: 8px 0; }
.summary td { border: .5px solid #999; padding: 5px; vertical-align: top; }
.summary .label { background: #eee; font-weight: 600; width: 28%; }
table.components { width: 100%; margin: 9px 0; table-layout: fixed; }
.components th, .components td { border: .5px solid #888; padding: 5px 4px; vertical-align: top; }
.components th { background: #eee; text-align: left; }
.components td.number, .components th.number { text-align: center; }
.components tr { page-break-inside: avoid; }
.subtle { color: #444; }
.note { margin: 8px 0; }
</style></head><body>
@include('reports.partials.letterhead', ['title' => 'Situação de notas e frequência', 'showTechnicalRegulation' => false])
@php
    $formatPoints = fn ($value) => number_format((float) $value, 1, ',', '.');
    $formatMinutes = fn ($minutes) => $minutes === null ? '—' : intdiv((int) $minutes, 60).' h '.str_pad((string) ((int) $minutes % 60), 2, '0', STR_PAD_LEFT).' min';
    $annualAttendance = $progress['attendance'];
@endphp
<p class="context"><strong>Estudante:</strong> {{ $report['student']->full_name }}<br>
<strong>Escola:</strong> {{ $report['academicYear']->school?->name }} · <strong>Turma:</strong> {{ $report['schoolClass']->name }} · <strong>Ano letivo:</strong> {{ $report['academicYear']->referenceYearsLabel() }}<br>
<strong>Matrícula:</strong> {{ $report['enrollment']->id }} · <strong>Situação registrada:</strong> {{ $report['finalResult']['label'] ?? $report['enrollment']->statusLabel() }}</p>
<table class="summary">
<tr><td class="label">Critérios anuais</td><td>{{ $formatPoints($progress['passing_points']) }} pontos por componente · frequência mínima geral de {{ $progress['minimum_attendance'] }}%</td></tr>
<tr><td class="label">Frequência lançada</td><td>{{ $annualAttendance['percentage'] === null ? 'Sem lançamento' : number_format((float) $annualAttendance['percentage'], 1, ',', '.').'%' }} · {{ $annualAttendance['effective_attended'] }}/{{ $annualAttendance['lessons'] }} aulas com presença efetiva (inclui ausências justificadas)</td></tr>
<tr><td class="label">Carga horária prevista</td><td>{{ $formatMinutes($progress['planned_minutes']) }}</td></tr>
<tr><td class="label">Faltas lançadas</td><td>{{ $progress['raw_absences'] }} aulas · {{ $formatMinutes($progress['raw_absence_minutes']) }}</td></tr>
<tr><td class="label">Abatidas por justificativas e atestados</td><td>{{ $progress['justified_absences'] }} aulas · {{ $formatMinutes($progress['justified_minutes']) }}</td></tr>
<tr><td class="label">Faltas efetivas após abatimento</td><td>{{ $progress['effective_absences'] }} aulas · <strong>{{ $formatMinutes($progress['absence_minutes']) }}</strong></td></tr>
<tr><td class="label">Limite estimado de faltas</td><td>{{ $formatMinutes($progress['absence_limit_minutes']) }}</td></tr>
<tr><td class="label">Margem estimada para novas faltas</td><td><strong>{{ $formatMinutes($progress['remaining_absence_minutes']) }}</strong>@if($progress['over_absence_limit']) · Limite projetado ultrapassado @endif</td></tr>
</table>
<p class="note subtle">As justificativas e os atestados abrangem os dias letivos informados; o abatimento é mostrado somente no total da matrícula. As horas de falta por componente abaixo são as lançadas antes do abatimento geral. A margem usa as faltas efetivas após esse abatimento. A projeção usa a carga horária curricular prevista e a duração da hora-aula de cada curso; pressupõe presença nas demais aulas previstas. A frequência oficial usa as aulas lançadas e pode mudar com novos registros ou ajustes de calendário.</p>
<table class="components">
<thead><tr><th width="44%">Componente</th><th class="number" width="9%">Pontos atuais</th><th class="number" width="11%">Pontos faltantes</th><th class="number" width="13%">Períodos completos</th><th class="number" width="10%">Frequência</th><th class="number" width="13%">Horas de falta</th></tr></thead>
<tbody>
@forelse($progress['components'] as $row)
<tr>
<td width="44%"><strong>{{ $row['component']->name }}</strong>@if($row['course']?->name)<br><span class="subtle">{{ $row['course']->name }}</span>@endif</td>
<td class="number">{{ $row['points'] === null ? '—' : $formatPoints($row['points']) }}</td>
<td class="number">{{ $formatPoints($row['missing_points']) }}</td>
<td class="number">{{ $row['complete_periods'] }}/{{ $row['total_periods'] }}</td>
<td class="number">{{ $row['attendance']['percentage'] === null ? '—' : number_format((float) $row['attendance']['percentage'], 1, ',', '.').'%' }}</td>
<td class="number">{{ $formatMinutes($row['raw_absence_minutes']) }}</td>
</tr>
@empty
<tr><td colspan="6">Nenhum componente curricular vinculado à matrícula.</td></tr>
@endforelse
</tbody></table>
<p class="note subtle">Pontos faltantes = meta anual menos pontos apurados, com mínimo de zero. Períodos sem notas não contam como nota zero. Médias parciais podem mudar com novos lançamentos ou recuperação; este relatório não substitui o resultado final registrado.</p>
@include('reports.partials.document-footer')
</body></html>
