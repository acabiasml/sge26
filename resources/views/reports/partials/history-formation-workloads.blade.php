@if($showAnnualFormationWorkloads ?? true)
@include('reports.partials.history-annual-formation-workloads')
@endif
@if($compactFormationWorkloads ?? false)
<table class="history-table workload-totals workload-totals-compact">
    <tbody><tr>
        @foreach($history->formationWorkloadTotals() as $formation => $hours)
            <td><strong>{{ $formation }}</strong>: {{ $hours['planned'] !== null ? number_format($hours['planned'], 2, ',', '.') : '-' }} / {{ $hours['completed'] !== null ? number_format($hours['completed'], 2, ',', '.') : '-' }}</td>
        @endforeach
    </tr></tbody>
</table>
@else
<table class="history-table workload-totals">
    <thead><tr><th>Formação</th><th class="center">Total de horas previstas</th><th class="center">Total de horas cursadas</th></tr></thead>
    <tbody>
        @foreach($history->formationWorkloadTotals() as $formation => $hours)
            <tr><td>{{ $formation }}</td><td class="center">{{ $hours['planned'] !== null ? number_format($hours['planned'], 2, ',', '.') : '-' }}</td><td class="center">{{ $hours['completed'] !== null ? number_format($hours['completed'], 2, ',', '.') : '-' }}</td></tr>
        @endforeach
    </tbody>
</table>

@endif
