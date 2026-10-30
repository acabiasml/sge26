<div>

    <section class="card shadow mb-4" aria-labelledby="diaries-title">
        <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
            <h2 id="diaries-title" class="h6 m-0 font-weight-bold text-primary">{{ $isManagement ? __('Diários disponíveis para gestão') : __('Diários disponíveis') }}</h2>
            <span id="diary-results-count" class="badge badge-light" aria-live="polite">{{ $diaries->count() }} {{ __('diário(s)') }}</span>
        </div>
        <div class="card-body">
            @if (! $isManagement && ($availableDiaries ?? $diaries)->count() > 1)
                @php
                    $diarySchools = ($availableDiaries ?? $diaries)->map(fn (array $diary) => $diary['academicYear']->school)->filter()->unique('id')->sortBy('name')->values();
                    $diaryYears = ($availableDiaries ?? $diaries)->map(fn (array $diary) => $diary['academicYear'])->unique('id')->sortByDesc('starts_at')->values();
                    $diaryClasses = ($availableDiaries ?? $diaries)->map(fn (array $diary) => $diary['class'])->unique('id')->sortBy('name')->values();
                @endphp
                <div class="sge-diary-quick-filters mb-4" data-diary-filters>
                    <div class="form-group mb-0 sge-diary-search-field">
                        <label for="diary-search">{{ __('Buscar diário') }}</label>
                        <div class="input-group">
                            <div class="input-group-prepend" aria-hidden="true"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                            <input id="diary-search" class="form-control" type="search" placeholder="{{ __('Componente, turma ou escola') }}" autocomplete="off" wire:model.live.debounce.350ms="filters.q" data-diary-search>
                        </div>
                    </div>
                    @if ($diarySchools->count() > 1)
                        <div class="form-group mb-0">
                            <label for="diary-school-filter">{{ __('Escola') }}</label>
                            <select id="diary-school-filter" class="custom-select" wire:model.live="filters.school" data-diary-filter="school">
                                <option value="">{{ __('Todas') }}</option>
                                @foreach ($diarySchools as $school)
                                    <option value="{{ $school->id }}">{{ $school->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($diaryYears->count() > 1)
                        <div class="form-group mb-0">
                            <label for="diary-year-filter">{{ __('Ano letivo') }}</label>
                            <select id="diary-year-filter" class="custom-select" wire:model.live="filters.academic_year" data-diary-filter="year">
                                <option value="">{{ __('Todos') }}</option>
                                @foreach ($diaryYears as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }} · {{ $year->reference_year }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($diaryClasses->count() > 1)
                        <div class="form-group mb-0">
                            <label for="diary-class-filter">{{ __('Turma') }}</label>
                            <select id="diary-class-filter" class="custom-select" wire:model.live="filters.class" data-diary-filter="class">
                                <option value="">{{ __('Todas') }}</option>
                                @foreach ($diaryClasses as $classOption)
                                    <option value="{{ $classOption->id }}">{{ $classOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button class="btn btn-outline-secondary align-self-end" type="button" wire:click="clearFilters" data-diary-clear>
                        <i class="fas fa-eraser mr-1" aria-hidden="true"></i>{{ __('Limpar') }}
                    </button>
                </div>
            @endif

            <div data-diary-list>
            @forelse ($diaries as $diary)
                <article class="sge-diary-list-card sge-diary-progress-card"
                    data-diary-item
                    data-school="{{ $diary['academicYear']->school_id }}"
                    data-year="{{ $diary['academicYear']->id }}"
                    data-class="{{ $diary['class']->id }}"
                    data-search="{{ Illuminate\Support\Str::lower(Illuminate\Support\Str::ascii($diary['component']->name.' '.$diary['class']->name.' '.$diary['academicYear']->name.' '.($diary['academicYear']->school?->name ?? '').' '.($diary['component']->area?->name ?? ''))) }}">
                    <div class="sge-diary-list-main">
                        <h3><a href="{{ route('teacher-diaries.show', [$diary['class'], $diary['component']]) }}">{{ $diary['component']->name }}</a></h3>
                        <p>{{ $diary['class']->name }} · {{ $diary['academicYear']->school?->name }} · {{ $diary['academicYear']->reference_year }}</p>
                    </div>
                    <div role="group" class="sge-diary-list-actions" aria-label="{{ __('Ações do diário de') }} {{ $diary['component']->name }}">
                        <a class="btn btn-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.show', [$diary['class'], $diary['component']]) }}" aria-label="{{ __('Abrir diário de') }} {{ $diary['component']->name }} {{ __('da turma') }} {{ $diary['class']->name }}" title="{{ __('Abrir diário') }}">
                            <i class="fas fa-book-open" aria-hidden="true"></i>
                        </a>
                        <a target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('academic-years.classes.schedules.pdf', [$diary['academicYear'], $diary['class']]) }}" aria-label="{{ __('Imprimir horário da turma') }} {{ $diary['class']->name }}" title="{{ __('Imprimir horário da turma') }}">
                            <i class="fas fa-calendar-week" aria-hidden="true"></i>
                        </a>
                        <a target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.attendance-sheet.pdf', [$diary['class'], $diary['component']]) }}" aria-label="{{ __('Imprimir lista de chamada mensal de') }} {{ $diary['component']->name }}" title="{{ __('Imprimir lista de chamada') }}">
                            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        </a>
                    </div>
                    @include('teacher-diaries.partials.list-summary', ['diary' => $diary])
                </article>
            @empty
                <div class="sge-empty-state">
                    <i class="fas fa-book-open" aria-hidden="true"></i>
                    <h3>{{ __('Nenhum diário disponível') }}</h3>
                    <p>{{ __('Os diários aparecem quando o ano letivo está aprovado e a docência está vinculada a uma turma e a um componente curricular.') }}</p>
                </div>
            @endforelse
            </div>

            <div class="sge-empty-state d-none" data-diary-no-results role="status">
                <i class="fas fa-search" aria-hidden="true"></i>
                <h3>{{ __('Nenhum diário encontrado') }}</h3>
                <p>{{ __('Altere os filtros ou limpe a busca para ver os demais diários.') }}</p>
            </div>
        </div>
    </section>

</div>
