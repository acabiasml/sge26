<div>
    @include('livewire.listings.search')

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ $school->name }}</h2>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>{{ __('screens.name') }}</th>
                        <th>{{ __('screens.year') }}</th>
                        <th>{{ __('screens.period') }}</th>
                        <th>{{ __('screens.school_days') }}</th>
                        <th>{{ __('screens.approval') }}</th>
                        <th class="text-right">{{ __('screens.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($listingRows as $year)
                        <tr>
                            <td>{{ $year->name }}</td>
                            <td>{{ $year->reference_year }}</td>
                            <td>{{ $year->starts_at?->format('d/m/Y') }} {{ __('a') }} {{ $year->ends_at?->format('d/m/Y') }}</td>
                            <td>{{ $year->schoolDayCount() }}</td>
                            <td>{{ $year->approved_at?->format('d/m/Y') ?? __('screens.pending') }}</td>
                            <td class="text-right">
                                <div class="sge-action-buttons">
                                <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('academic-years.show', $year) }}" aria-label="{{ __('screens.open_school_year', ['year' => $year->name]) }}" title="{{ __('screens.open_school_year_title') }}">
                                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                                </a>
                                <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.calendar-pdf', $year) }}" aria-label="{{ __('screens.issue_calendar_pdf', ['year' => $year->name]) }}" title="{{ __('screens.calendar_pdf') }}">
                                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-gray-600">{{ __('screens.no_school_year') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@if(isset($listingPages))<div class="mt-3">{{ $listingPages->links('livewire::bootstrap') }}</div>@endif
</div>
