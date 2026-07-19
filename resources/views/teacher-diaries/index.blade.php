@extends('layouts.app')

@section('title', 'Diários')
@section('page-title', $isManagement ? 'Diários das escolas' : 'Meus diários')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm" href="{{ route('teacher-schedules.index') }}">
        <i class="fas fa-calendar-alt mr-1" aria-hidden="true"></i> Meu horário
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-schedules.pdf') }}" aria-label="Imprimir meu horário docente" title="Imprimir meu horário">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <section class="card shadow mb-4" aria-labelledby="diaries-title">
        <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
            <h2 id="diaries-title" class="h6 m-0 font-weight-bold text-primary">{{ $isManagement ? 'Diários disponíveis para gestão' : 'Diários disponíveis' }}</h2>
            <span id="diary-results-count" class="badge badge-light" aria-live="polite">{{ $diaries->count() }} diário(s)</span>
        </div>
        <div class="card-body">
            @if (! $isManagement && $diaries->count() > 1)
                @php
                    $diarySchools = $diaries->map(fn (array $diary) => $diary['academicYear']->school)->filter()->unique('id')->sortBy('name')->values();
                    $diaryYears = $diaries->map(fn (array $diary) => $diary['academicYear'])->unique('id')->sortByDesc('starts_at')->values();
                    $diaryClasses = $diaries->map(fn (array $diary) => $diary['class'])->unique('id')->sortBy('name')->values();
                @endphp
                <div class="sge-diary-quick-filters mb-4" data-diary-filters>
                    <div class="form-group mb-0 sge-diary-search-field">
                        <label for="diary-search">Buscar diário</label>
                        <div class="input-group">
                            <div class="input-group-prepend" aria-hidden="true"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                            <input id="diary-search" class="form-control" type="search" placeholder="Componente, turma ou escola" autocomplete="off" data-diary-search>
                        </div>
                    </div>
                    @if ($diarySchools->count() > 1)
                        <div class="form-group mb-0">
                            <label for="diary-school-filter">Escola</label>
                            <select id="diary-school-filter" class="custom-select" data-diary-filter="school">
                                <option value="">Todas</option>
                                @foreach ($diarySchools as $school)
                                    <option value="{{ $school->id }}">{{ $school->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($diaryYears->count() > 1)
                        <div class="form-group mb-0">
                            <label for="diary-year-filter">Ano letivo</label>
                            <select id="diary-year-filter" class="custom-select" data-diary-filter="year">
                                <option value="">Todos</option>
                                @foreach ($diaryYears as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }} · {{ $year->reference_year }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($diaryClasses->count() > 1)
                        <div class="form-group mb-0">
                            <label for="diary-class-filter">Turma</label>
                            <select id="diary-class-filter" class="custom-select" data-diary-filter="class">
                                <option value="">Todas</option>
                                @foreach ($diaryClasses as $classOption)
                                    <option value="{{ $classOption->id }}">{{ $classOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button class="btn btn-outline-secondary align-self-end" type="button" data-diary-clear>
                        <i class="fas fa-eraser mr-1" aria-hidden="true"></i>Limpar
                    </button>
                </div>
            @endif

            <div data-diary-list>
            @forelse ($diaries as $diary)
                <article class="sge-diary-list-card"
                    data-diary-item
                    data-school="{{ $diary['academicYear']->school_id }}"
                    data-year="{{ $diary['academicYear']->id }}"
                    data-class="{{ $diary['class']->id }}"
                    data-search="{{ Illuminate\Support\Str::lower(Illuminate\Support\Str::ascii($diary['component']->name.' '.$diary['class']->name.' '.$diary['academicYear']->name.' '.($diary['academicYear']->school?->name ?? '').' '.($diary['component']->area?->name ?? ''))) }}">
                    <div class="sge-diary-list-main">
                        <h3><a href="{{ route('teacher-diaries.show', [$diary['class'], $diary['component']]) }}">{{ $diary['component']->name }}</a></h3>
                        <p>{{ $diary['class']->name }} · {{ $diary['academicYear']->school?->name }}</p>
                        <small>{{ $diary['academicYear']->name }} · {{ $diary['course']->name }} · {{ $diary['component']->area?->name ?? 'Área não definida' }}</small>
                    </div>
                    <div class="sge-diary-list-actions" aria-label="Ações do diário de {{ $diary['component']->name }}">
                        <a class="btn btn-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.show', [$diary['class'], $diary['component']]) }}" aria-label="Abrir diário de {{ $diary['component']->name }} da turma {{ $diary['class']->name }}" title="Abrir diário">
                            <i class="fas fa-book-open" aria-hidden="true"></i>
                        </a>
                        <a class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('academic-years.classes.schedules.pdf', [$diary['academicYear'], $diary['class']]) }}" aria-label="Imprimir horário da turma {{ $diary['class']->name }}" title="Imprimir horário da turma">
                            <i class="fas fa-calendar-week" aria-hidden="true"></i>
                        </a>
                        <a class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.attendance-sheet.pdf', [$diary['class'], $diary['component']]) }}" aria-label="Imprimir lista de chamada mensal de {{ $diary['component']->name }}" title="Imprimir lista de chamada">
                            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>
            @empty
                <div class="sge-empty-state">
                    <i class="fas fa-book-open" aria-hidden="true"></i>
                    <h3>Nenhum diário disponível</h3>
                    <p>Os diários aparecem quando o ano letivo está aprovado e a docência está vinculada a uma turma e a um componente curricular.</p>
                </div>
            @endforelse
            </div>

            <div class="sge-empty-state d-none" data-diary-no-results role="status">
                <i class="fas fa-search" aria-hidden="true"></i>
                <h3>Nenhum diário encontrado</h3>
                <p>Altere os filtros ou limpe a busca para ver os demais diários.</p>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const filters = document.querySelector('[data-diary-filters]');
            const items = Array.from(document.querySelectorAll('[data-diary-item]'));

            if (!filters || items.length === 0) {
                return;
            }

            const search = filters.querySelector('[data-diary-search]');
            const selects = Array.from(filters.querySelectorAll('[data-diary-filter]'));
            const clear = filters.querySelector('[data-diary-clear]');
            const count = document.getElementById('diary-results-count');
            const noResults = document.querySelector('[data-diary-no-results]');
            const normalize = (value) => String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();

            const applyFilters = () => {
                const term = normalize(search?.value);
                let visible = 0;

                items.forEach((item) => {
                    const matchesSearch = term === '' || item.dataset.search.includes(term);
                    const matchesSelects = selects.every((select) => !select.value || item.dataset[select.dataset.diaryFilter] === select.value);
                    const show = matchesSearch && matchesSelects;

                    item.hidden = !show;
                    visible += show ? 1 : 0;
                });

                if (count) {
                    count.textContent = `${visible} diário(s)`;
                }

                noResults?.classList.toggle('d-none', visible !== 0);
            };

            search?.addEventListener('input', applyFilters);
            selects.forEach((select) => select.addEventListener('change', applyFilters));
            clear?.addEventListener('click', () => {
                if (search) {
                    search.value = '';
                }
                selects.forEach((select) => { select.value = ''; });
                applyFilters();
                search?.focus();
            });
        })();
    </script>
@endpush
