<table class="{{ $annualWorkloadTableClass ?? 'history-table workload-totals' }}" data-annual-formation-workloads>
    <thead>
        <tr><th rowspan="2">{{ __('Ano / Série') }}</th><th colspan="2" class="center text-center">{{ __('Formação Geral Básica') }}</th><th colspan="2" class="center text-center">{{ __('Itinerário Formativo') }}</th></tr>
        <tr>@foreach(range(1, 2) as $formationIndex)<th class="center text-center">{{ __('Horas previstas') }}</th><th class="center text-center">CHC</th>@endforeach</tr>
    </thead>
    <tbody>
        @foreach($history->years as $year)
            @php($annualHours = $history->formationWorkloadTotals($year))
            <tr data-workload-year="{{ $year->id }}">
                <td>{{ $year->label }}@if($year->year) · {{ $year->year }}@endif</td>
                @foreach($annualHours as $hours)
                    <td class="center text-center">{{ $hours['planned'] !== null ? number_format($hours['planned'], 2, ',', '.').'h' : '-' }}</td>
                    <td class="center text-center">{{ $hours['completed'] !== null ? number_format($hours['completed'], 2, ',', '.').'h' : '-' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
