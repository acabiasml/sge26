<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@page { size: A4 landscape; margin: 170px 22px 96px; }
body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
@include('reports.partials.letterhead-styles')
.letterhead-repeat { top: -156px; }
table { width: 100%; border-collapse: collapse; margin: 7px 0 11px; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td { border: .5px solid #999; padding: 4px; vertical-align: middle; }
th { background: #eee; }
.component-block, .component-table { page-break-inside: avoid; }
.component-table { table-layout: fixed; }
.component-title { padding: 5px; border: .5px solid #999; border-bottom: 0; background: #e7ddd6; color: #4f2d22; font-weight: 600; }
.report-context { margin-top: 24px; }
.student-name { white-space: nowrap; }
.number { text-align: center; }
.note { font-size: 11px; color: #444; }
</style></head><body>
@include('reports.partials.letterhead', ['title' => 'Panorama de aprovação da turma', 'showTechnicalRegulation' => false])
<p class="report-context"><strong>{{ $schoolClass->name }}</strong> · {{ $academicYear->referenceYearsLabel() }} · {{ $academicYear->school?->name }}</p>
<p class="note">A soma considera apenas os períodos encerrados com média completa. * indica média parcial, fora da soma. O resultado final também considera a frequência anual e, quando aplicável, a regra de dependência da escola.</p>
@php
    $formatPoints = fn ($points) => number_format($points, abs(round($points, 1) - $points) > 0.0001 ? 2 : 1, ',', '.');
    $endedPeriods = $reports
        ->flatMap(fn ($report) => $report['annualComponents'])
        ->flatMap(fn ($summary) => $summary['overview']['ended_periods']->values())
        ->map(fn ($item) => $item['period'])
        ->unique('id')
        ->sortBy([['starts_at', 'asc'], ['position', 'asc']])
        ->values();
    $periodWidth = $endedPeriods->isEmpty() ? 0 : 21 / $endedPeriods->count();
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
<div class="component-block">
<div class="component-title">{{ $loop->iteration }}. {{ $component->name }}@if($component->course?->name) <span class="note">· {{ $component->course->name }} · {{ $component->course->modalityLabel() }}</span>@endif</div>
<table class="component-table" style="margin-top:0">
<colgroup>
<col style="width:30%"><col style="width:9%">
@foreach($endedPeriods as $endedPeriod)<col style="width:{{ $periodWidth }}%">@endforeach
<col style="width:9%"><col style="width:9%"><col style="width:10%"><col style="width:12%">
</colgroup>
<thead><tr>
<th width="30%">Estudante</th>
<th width="9%">{{ $teacherComponentScope ? 'Frequência no componente' : 'Frequência anual' }}</th>
@foreach($endedPeriods as $endedPeriod)
<th width="{{ $periodWidth }}%" title="{{ $endedPeriod->name }}">{{ preg_replace(['/\s+Bimestre$/iu', '/\s+Trimestre$/iu', '/\s+Semestre$/iu'], [' Bim.', ' Trim.', ' Sem.'], $endedPeriod->name) }}</th>
@endforeach
<th width="9%">Pontos apurados / Meta</th><th width="9%">Faltam pontos</th><th width="10%">Períodos completos</th><th width="12%">Situação dos pontos</th>
</tr></thead>
<tbody>
@foreach($componentRows as $row)
@php
    $report = $row['report'];
    $summary = $row['summary'];
    $progress = $summary['overview'];
@endphp
<tr><td class="student-name">{{ $report['student']->full_name }}</td>
@php $attendance = $teacherComponentScope ? $summary['attendance'] : $report['annualAttendance']; @endphp
<td class="number">{{ $attendance['percentage'] === null ? '—' : number_format($attendance['percentage'], 1, ',', '.').'%' }}@unless($teacherComponentScope)<br><small>Mín. {{ $report['minimumAttendance'] }}%</small>@endunless</td>
@foreach($endedPeriods as $endedPeriod)
@php
    $periodSummary = $progress['ended_periods']->get($endedPeriod->id);
    $periodScore = $periodSummary['average']['value'] ?? null;
@endphp
<td class="number">{{ $periodSummary === null ? '—' : ($periodScore === null ? 'Sem nota' : $formatPoints($periodScore).($periodSummary['average']['complete'] ? '' : '*')) }}</td>
@endforeach
<td class="number">{{ $progress['value'] === null ? '—' : $formatPoints($progress['value']) }} / {{ $formatPoints($progress['target']) }}</td>
<td class="number">{{ $progress['value'] === null ? '—' : $formatPoints($progress['missing']) }}</td>
<td class="number">{{ $progress['complete_periods'] }}/{{ $summary['total_periods'] }}</td>
<td>{{ $progress['status'] }}</td></tr>
@endforeach
</tbody></table>
</div>
@empty
<p>Nenhum componente vinculado às matrículas ativas desta turma.</p>
@endforelse
@include('reports.partials.document-footer')
</body></html>
