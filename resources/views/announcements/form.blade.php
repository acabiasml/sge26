            <form method="POST" action="{{ isset($announcement) ? route('announcements.update', $announcement) : route('announcements.store') }}">
                @csrf
                @isset($announcement) @method('PUT') @endisset
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
                                <option value="{{ $school->id }}" @selected(old('school_id', $announcement->school_id ?? null) == $school->id)>{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="starts_at">{{ __('screens.display_from') }}</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" value="{{ old('starts_at', ($announcement->starts_at ?? now())->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ends_at">{{ __('screens.display_until') }}</label>
                        <input id="ends_at" name="ends_at" type="datetime-local" class="form-control" value="{{ old('ends_at', isset($announcement) ? $announcement->ends_at?->format('Y-m-d\TH:i') : null) }}">
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">{{ __('screens.title') }}</label>
                    <input id="title" name="title" value="{{ old('title', $announcement->title ?? '') }}" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="body">{{ __('screens.message') }}</label>
                    <textarea id="body" name="body" class="form-control" rows="6" required>{{ old('body', $announcement->body ?? '') }}</textarea>
                </div>

                <input type="hidden" name="highlight" value="0">
                <div class="custom-control custom-checkbox mb-2">
                    <input class="custom-control-input" id="highlight" name="highlight" type="checkbox" value="1" @checked(old('highlight', $announcement->highlight ?? false))>
                    <label class="custom-control-label" for="highlight">{{ __('screens.highlight_home') }}</label>
                </div>

                <div class="custom-control custom-checkbox mb-3">
                    <input type="hidden" name="active" value="0">
                    <input class="custom-control-input" id="active" name="active" type="checkbox" value="1" @checked(old('active', $announcement->active ?? true))>
                    <label class="custom-control-label" for="active">{{ __('screens.active_announcement') }}</label>
                </div>

                <button class="btn btn-primary" type="submit">{{ __('screens.save_announcement') }}</button>
            </form>
