@extends('layouts.app')

@section('title', __('screens.announcements'))
@section('page-title', __('screens.announcements'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('screens.new_announcement') }}</h2>
        </div>
        <div class="card-body">
            @include('announcements.form')
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('screens.registered_announcements') }}</h2>
        </div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('screens.announcement') }}</th>
                        <th>{{ __('screens.destination') }}</th>
                        <th>{{ __('screens.display') }}</th>
                        <th>{{ __('screens.status') }}</th>
                        <th>{{ __('screens.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td>
                                {{ $announcement->title }}
                                @if ($announcement->highlight)
                                    <span class="badge badge-warning ml-1">{{ __('dashboard.highlight') }}</span>
                                @endif
                            </td>
                            <td>{{ $announcement->school?->name ?? __('screens.global') }}</td>
                            <td>
                                {{ $announcement->starts_at?->format('d/m/Y H:i') }}
                                {{ __('screens.until') }}
                                {{ $announcement->ends_at?->format('d/m/Y H:i') ?? __('roles.indefinite') }}
                            </td>
                            <td>{{ $announcement->active ? __('screens.active_m') : __('screens.inactive_m') }}</td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary mb-2" href="{{ route('announcements.edit', $announcement) }}"><i class="fas fa-pen mr-1" aria-hidden="true"></i>{{ __('Editar') }}</a>
                                <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" onsubmit="return confirm(@js(__('screens.remove_announcement_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('screens.remove_announcement', ['title' => $announcement->title]) }}" title="{{ __('screens.remove_announcement_title') }}">
                                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('screens.no_announcement') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            {{ $announcements->links() }}
        </div>
    </div>
@endsection
