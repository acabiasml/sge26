@php($isEdit = $class->exists)
@php($selectedCourseIds = collect(old('course_ids', $class->exists ? $class->courses->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all())
@php($baseCourses = $readyCourses->reject(fn ($course) => $course->isItineraryMatrix())->values())
@php($itineraryCourses = $readyCourses->filter(fn ($course) => $course->isItineraryMatrix())->values())
@php($selectedShift = old('shift', $class->shift))
@php($selectedBaseCourse = $baseCourses->first(fn ($course) => in_array($course->id, $selectedCourseIds, true)))
@php($selectedStage = old('stage', $selectedBaseCourse?->stage))

<form method="POST" action="{{ $isEdit ? route('academic-years.classes.update', [$academicYear, $class]) : route('academic-years.classes.store', $academicYear) }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Dados da turma</h2>
        </div>
        <div class="card-body">
            @error('general')
                <div class="alert alert-danger" role="alert">
                    {{ $message }}
                </div>
            @enderror

            @if ($readyCourses->isEmpty())
                <div class="alert alert-warning">
                    Cadastre ao menos uma matriz com componentes curriculares antes de criar turmas.
                </div>
            @endif

            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="name">Nome da turma</label>
                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $class->name) }}" placeholder="3º Ano A" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="shift">Turno</label>
                    <select id="shift" name="shift" class="form-control @error('shift') is-invalid @enderror">
                        <option value="">Não definido</option>
                        @foreach(\App\Models\SchoolClass::SHIFT_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected($selectedShift === $value)>{{ $label }}</option>
                        @endforeach
                        @if($selectedShift && !array_key_exists($selectedShift, \App\Models\SchoolClass::SHIFT_LABELS))
                            <option value="{{ $selectedShift }}" selected>{{ $selectedShift }} (cadastrado anteriormente)</option>
                        @endif
                    </select>
                    @error('shift') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="stage">Etapa de ensino</label>
                    <select id="stage" name="stage" class="form-control @error('stage') is-invalid @enderror" data-class-stage required>
                        <option value="">Selecione a etapa</option>
                        <option value="{{ \App\Models\AcademicCourse::STAGE_ELEMENTARY }}" @selected($selectedStage === \App\Models\AcademicCourse::STAGE_ELEMENTARY)>Ensino Fundamental</option>
                        <option value="{{ \App\Models\AcademicCourse::STAGE_HIGH_SCHOOL }}" @selected($selectedStage === \App\Models\AcademicCourse::STAGE_HIGH_SCHOOL)>Ensino Médio</option>
                    </select>
                    @error('stage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2 form-group">
                    <div class="font-weight-bold text-gray-900">Situação</div>
                    <div class="custom-control custom-checkbox mt-2">
                        <input class="custom-control-input" id="active" name="active" type="checkbox" value="1" @checked(old('active', $class->active ?? true))>
                        <label class="custom-control-label" for="active">Turma ativa</label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="starts_period_id">Período inicial</label>
                    <select id="starts_period_id" name="starts_period_id" class="form-control @error('starts_period_id') is-invalid @enderror" required>
                        @foreach ($academicYear->periods->sortBy('position') as $period)
                            <option value="{{ $period->id }}" @selected((int) old('starts_period_id', $class->starts_period_id) === $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                    @error('starts_period_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 form-group">
                    <label for="ends_period_id">Período final</label>
                    <select id="ends_period_id" name="ends_period_id" class="form-control @error('ends_period_id') is-invalid @enderror" required>
                        @foreach ($academicYear->periods->sortBy('position') as $period)
                            <option value="{{ $period->id }}" @selected((int) old('ends_period_id', $class->ends_period_id) === $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                    @error('ends_period_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            @if ($academicYear->periods->isEmpty())
                <div class="alert alert-warning" role="alert">
                    Cadastre os períodos avaliativos deste ano letivo antes de criar ou editar turmas.
                </div>
            @endif

            <fieldset class="form-group" aria-describedby="course_ids_help" data-matrix-group="base">
                <legend class="col-form-label pt-0 font-weight-bold">Formação Geral Básica</legend>
                <div class="row" role="group" aria-describedby="course_ids_help">
                    @forelse ($baseCourses as $course)
                        @php($courseMatchesSelectedStage = filled($selectedStage) && $course->stage === $selectedStage)
                        <div class="col-lg-6 mb-2" data-matrix-option data-course-stage="{{ $course->stage }}" @if(!$courseMatchesSelectedStage) hidden @endif>
                            <div class="border rounded px-3 py-2 h-100 @if(in_array($course->id, $selectedCourseIds, true)) border-primary bg-light @endif">
                                <div class="custom-control custom-checkbox">
                                    <input id="course_id_{{ $course->id }}" name="course_ids[]" type="checkbox" class="custom-control-input" value="{{ $course->id }}" @checked(in_array($course->id, $selectedCourseIds, true)) @disabled(!$courseMatchesSelectedStage)>
                                    <label class="custom-control-label d-block" for="course_id_{{ $course->id }}">
                                        <strong>{{ $course->name }}</strong>
                                        <span class="d-block text-muted small">{{ $course->stageLabel() }} · {{ $course->modalityLabel() }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><p class="text-muted mb-2">Nenhuma matriz de Formação Geral Básica está pronta para vinculação.</p></div>
                    @endforelse
                    <div class="col-12" data-no-compatible-matrix hidden><p class="text-muted mb-2">Nenhuma matriz pronta está disponível para a etapa selecionada.</p></div>
                </div>
            </fieldset>

            <fieldset class="form-group" aria-describedby="course_ids_help" data-matrix-group="itinerary" @if($selectedStage !== \App\Models\AcademicCourse::STAGE_HIGH_SCHOOL) hidden @endif>
                <legend class="col-form-label pt-0 font-weight-bold">Itinerário Formativo</legend>
                <div class="row" role="group">
                    @forelse ($itineraryCourses as $course)
                        <div class="col-lg-6 mb-2">
                            <div class="border rounded px-3 py-2 h-100 @if(in_array($course->id, $selectedCourseIds, true)) border-primary bg-light @endif">
                                <div class="custom-control custom-checkbox">
                                    <input id="course_id_{{ $course->id }}" name="course_ids[]" type="checkbox" class="custom-control-input" value="{{ $course->id }}" @checked(in_array($course->id, $selectedCourseIds, true)) @disabled($selectedStage !== \App\Models\AcademicCourse::STAGE_HIGH_SCHOOL)>
                                    <label class="custom-control-label d-block" for="course_id_{{ $course->id }}">
                                        <strong>{{ $course->name }}</strong>
                                        <span class="d-block text-muted small">{{ $course->stageLabel() }} · {{ $course->modalityLabel() }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><p class="text-muted mb-2">Nenhuma matriz de Itinerário Formativo está pronta para vinculação.</p></div>
                    @endforelse
                </div>
            </fieldset>
            <small id="course_ids_help" class="form-text text-muted mb-3">Selecione a matriz da Formação Geral Básica. Para o Ensino Médio, selecione também os itinerários que compõem a turma, quando houver.</small>
            @error('course_ids') <div class="invalid-feedback d-block mb-3">{{ $message }}</div> @enderror

            <div class="form-group mb-0">
                <label for="notes">Observações</label>
                <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $class->notes) }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a class="btn btn-outline-secondary" href="{{ $isEdit ? route('academic-years.classes.show', [$academicYear, $class]) : route('academic-years.show', $academicYear) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit" @disabled($readyCourses->isEmpty() || $academicYear->periods->isEmpty())>{{ $isEdit ? 'Salvar turma' : 'Cadastrar turma' }}</button>
    </div>
</form>

@push('scripts')
    <script>
        (() => {
            const stageSelect = document.querySelector('[data-class-stage]');
            const baseGroup = document.querySelector('[data-matrix-group="base"]');
            const itineraryGroup = document.querySelector('[data-matrix-group="itinerary"]');

            const setOptionAvailability = (option, available) => {
                option.hidden = !available;
                const checkbox = option.querySelector('input[type="checkbox"]');
                if (!checkbox) return;
                checkbox.disabled = !available;
                if (!available) checkbox.checked = false;
            };

            const syncMatricesToStage = () => {
                const stage = stageSelect?.value || '';
                baseGroup?.querySelectorAll('[data-matrix-option]').forEach((option) => {
                    setOptionAvailability(option, stage !== '' && option.dataset.courseStage === stage);
                });
                const hasCompatibleMatrix = Array.from(baseGroup?.querySelectorAll('[data-matrix-option]') || [])
                    .some((option) => !option.hidden);
                const emptyMessage = baseGroup?.querySelector('[data-no-compatible-matrix]');
                if (emptyMessage) emptyMessage.hidden = stage === '' || hasCompatibleMatrix;

                const showItinerary = stage === '{{ \App\Models\AcademicCourse::STAGE_HIGH_SCHOOL }}';
                if (itineraryGroup) {
                    itineraryGroup.hidden = !showItinerary;
                    itineraryGroup.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                        checkbox.disabled = !showItinerary;
                        if (!showItinerary) checkbox.checked = false;
                    });
                }
            };

            stageSelect?.addEventListener('change', syncMatricesToStage);
            syncMatricesToStage();
        })();
    </script>
@endpush
