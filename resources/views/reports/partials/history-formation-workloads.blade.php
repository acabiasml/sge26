@include('reports.partials.history-annual-formation-workloads')
<table class="history-table workload-totals">
    <thead><tr><th>Formação</th><th class="center">Total de horas previstas</th><th class="center">Total de horas cursadas</th></tr></thead>
    <tbody>
        @foreach($history->formationWorkloadTotals() as $formation => $hours)
            <tr><td>{{ $formation }}</td><td class="center">{{ $hours['planned'] !== null ? number_format($hours['planned'], 2, ',', '.').'h' : '-' }}</td><td class="center">{{ $hours['completed'] !== null ? number_format($hours['completed'], 2, ',', '.').'h' : '-' }}</td></tr>
        @endforeach
    </tbody>
</table>
