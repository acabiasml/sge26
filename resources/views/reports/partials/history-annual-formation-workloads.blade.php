@php($formationColumns = array_keys($history->formationWorkloadTotals()))
<table class="{{ $annualWorkloadTableClass ?? 'history-table workload-totals' }}" data-annual-formation-workloads @isset($annualWorkloadTableClass) data-sge-client-pagination="10" @endisset>
    <thead>
        <tr><th rowspan="2">{{ __('Ano / Série') }}</th>@foreach($formationColumns as $formation)<th colspan="2" class="center text-center">{{ __($formation) }}</th>@endforeach</tr>
        <tr>@foreach($formationColumns as $formation)<th class="center text-center">{{ __('Horas previstas') }}</th><th class="center text-center">CHC (h)</th>@endforeach</tr>
    </thead>
    <tbody>
        @foreach($history->years as $year)
            @php($annualHours = $history->formationWorkloadTotals($year))
            <tr data-workload-year="{{ $year->id }}">
                <td>{{ $year->label }}@if($year->year) · {{ $year->year }}@endif</td>
                @foreach($formationColumns as $formation)
                    @php($hours = $annualHours[$formation] ?? ['planned' => null, 'completed' => null])
                    <td class="center text-center">{{ $hours['planned'] !== null ? number_format($hours['planned'], 2, ',', '.') : '-' }}</td>
                    <td class="center text-center">{{ $hours['completed'] !== null ? number_format($hours['completed'], 2, ',', '.') : '-' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
