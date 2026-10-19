<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@page { size: A4 landscape; margin: 150px 22px 96px; }
body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
@include('reports.partials.letterhead-styles')
table { width: 100%; border-collapse: collapse; margin: 7px 0 11px; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td { border: .5px solid #999; padding: 4px; vertical-align: middle; }
th { background: #eee; }
.component-table { page-break-inside: avoid; table-layout: fixed; }
.component-title { text-align: left; background: #e7ddd6; color: #4f2d22; }
.report-context { margin-top: 24px; }
.number { text-align: center; }
.note { font-size: 11px; color: #444; }
</style></head><body>
@include('reports.partials.letterhead', ['title' => 'Panorama de aprovação da turma', 'showTechnicalRegulation' => false])
<p class="report-context"><strong>{{ $schoolClass->name }}</strong> · {{ $academicYear->referenceYearsLabel() }} · {{ $academicYear->school?->name }}</p>
<p>Meta anual por componente: <strong>{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos</strong>.
Frequência mínima: {{ $academicYear->minimum_attendance_percentage }}%.</p>
<p class="note">Panorama dos lançamentos atuais. As notas individuais abaixo são exibidas somente para períodos já encerrados. A pontuação inclui médias parciais e pode mudar com novas notas ou recuperação. Atingir a meta de pontos não significa aprovação final: também são necessários frequência mínima e fechamento dos períodos. Períodos sem notas não são tratados como nota zero.</p>
@php
    $endedPeriods = $reports
        ->flatMap(fn ($report) => $report['annualComponents'])
        ->flatMap(fn ($summary) => $summary['periods'])
        ->filter(fn ($item) => $item['period']->ends_at?->copy()->endOfDay()->isPast())
        ->map(fn ($item) => $item['period'])
        ->unique('id')
        ->sortBy('position')
        ->values();
    $periodWidth = $endedPeriods->isEmpty() ? 0 : 24 / $endedPeriods->count();
    $componentGroups = $reports
        ->flatMap(fn ($report) => $report['annualComponents']->map(fn ($summary) => [
            'report' => $report,
            'summary' => $summary,
        ]))
        ->groupBy(fn ($row) => $row['summary']['component']->id)
        ->sortBy(fn ($rows) => \Illuminate\Support\Str::ascii($rows->first()['summary']['component']->name));
@endphp
@forelse($componentGroups as $componentRows)
@php
    $component = $componentRows->first()['summary']['component'];
@endphp
<table class="component-table">
<thead><tr><th class="component-title" colspan="{{ 6 + $endedPeriods->count() }}">{{ $loop->iteration }}. {{ $component->name }}@if($component->course?->name) <span class="note">· {{ $component->course->name }}</span>@endif</th></tr>
<tr>
<th style="width:28%">Estudante</th>
<th style="width:10%">Frequência anual</th>
@foreach($endedPeriods as $endedPeriod)
<th style="width:{{ $periodWidth }}%" title="{{ $endedPeriod->name }}">{{ preg_replace(['/\s+Bimestre$/iu', '/\s+Trimestre$/iu', '/\s+Semestre$/iu'], [' Bim.', ' Trim.', ' Sem.'], $endedPeriod->name) }}</th>
@endforeach
<th style="width:9%">Pontos atuais</th><th style="width:9%">Faltam pontos</th><th style="width:9%">Períodos completos</th><th style="width:11%">Situação</th>
</tr></thead>
<tbody>
@foreach($componentRows as $row)
@php
    $report = $row['report'];
    $summary = $row['summary'];
    $hasScore = $summary['periods']->contains(fn ($item) => ($item['average']['value'] ?? null) !== null);
    $missing = max(0, $report['passingPoints'] - $summary['points']);
@endphp
<tr><td>{{ $report['student']->full_name }}</td>
<td class="number">{{ $report['annualAttendance']['percentage'] === null ? '—' : number_format($report['annualAttendance']['percentage'], 1, ',', '.').'%' }}</td>
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
@endforeach
</tbody></table>
@empty
<p>Nenhum componente vinculado às matrículas ativas desta turma.</p>
@endforelse
@include('reports.partials.document-footer')
</body></html>
