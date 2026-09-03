@extends('layouts.app')

@section('title', __('screens.announcements'))
@section('page-title', __('screens.announcements'))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('screens.new_announcement') }}</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('announcements.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="school_id">{{ __('screens.destination') }}</label>
                        <select id="school_id" name="school_id" class="form-control" @unless(auth()->user()->isAdministrator()) required @endunless>
                            @if (auth()->user()->isAdministrator())
                                <option value="">{{ __('screens.global_all_schools') }}</option>
                            @else
                                <option value="">{{ __('screens.select') }}</option>
                            @endif
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}">{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="starts_at">{{ __('screens.display_from') }}</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ends_at">{{ __('screens.display_until') }}</label>
                        <input id="ends_at" name="ends_at" type="datetime-local" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">{{ __('screens.title') }}</label>
                    <input id="title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="body">{{ __('screens.message') }}</label>
                    <textarea id="body" name="body" class="form-control" rows="4" required></textarea>
                </div>

                <div class="custom-control custom-checkbox mb-2">
                    <input class="custom-control-input" id="highlight" name="highlight" type="checkbox" value="1">
                    <label class="custom-control-label" for="highlight">{{ __('screens.highlight_home') }}</label>
                </div>

                <div class="custom-control custom-checkbox mb-3">
                    <input class="custom-control-input" id="active" name="active" type="checkbox" value="1" checked>
                    <label class="custom-control-label" for="active">{{ __('screens.active_announcement') }}</label>
                </div>

                <button class="btn btn-primary" type="submit">{{ __('screens.save_announcement') }}</button>
            </form>
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
