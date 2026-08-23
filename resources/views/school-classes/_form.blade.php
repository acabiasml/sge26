@php($isEdit = $class->exists)
@php($selectedCourseIds = collect(old('course_ids', $class->exists ? $class->courses->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all())

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
                <div class="col-md-6 form-group">
                    <label for="name">Nome da turma</label>
                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $class->name) }}" placeholder="3º Ano A" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="shift">Turno</label>
                    <input id="shift" name="shift" class="form-control @error('shift') is-invalid @enderror" value="{{ old('shift', $class->shift) }}" placeholder="Matutino, vespertino...">
                    @error('shift') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
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

            <fieldset class="form-group">
                <legend class="col-form-label pt-0 font-weight-bold">Matrizes vinculadas</legend>
                <div class="row" role="group" aria-describedby="course_ids_help">
                    @foreach ($readyCourses as $course)
                        <div class="col-lg-6 mb-2">
                            <div class="border rounded px-3 py-2 h-100 @if(in_array($course->id, $selectedCourseIds, true)) border-primary bg-light @endif">
                                <div class="custom-control custom-checkbox">
                                    <input id="course_id_{{ $course->id }}" name="course_ids[]" type="checkbox" class="custom-control-input" value="{{ $course->id }}" @checked(in_array($course->id, $selectedCourseIds, true))>
                                    <label class="custom-control-label d-block" for="course_id_{{ $course->id }}">
                                        <strong>{{ $course->name }}</strong>
                                        <span class="d-block text-muted small">{{ $course->stageLabel() }} · {{ $course->modalityLabel() }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <small id="course_ids_help" class="form-text text-muted">Marque todas as matrizes que compõem esta turma.</small>
                @error('course_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </fieldset>

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
