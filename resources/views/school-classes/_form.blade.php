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
            @if ($readyCourses->isEmpty())
                <div class="alert alert-warning">
                    Cadastre ao menos uma matriz ativa com componentes curriculares antes de criar turmas.
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
                    <label for="starts_at">Início</label>
                    <input id="starts_at" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', $class->starts_at?->toDateString()) }}">
                    @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 form-group">
                    <label for="ends_at">Fim</label>
                    <input id="ends_at" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', $class->ends_at?->toDateString()) }}">
                    @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="course_ids">Matrizes vinculadas</label>
                <select id="course_ids" name="course_ids[]" class="form-control @error('course_ids') is-invalid @enderror" multiple required>
                    @foreach ($readyCourses as $course)
                        <option value="{{ $course->id }}" @selected(in_array($course->id, $selectedCourseIds, true))>
                            {{ $course->name }} · {{ $course->stageLabel() }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Use Ctrl para selecionar mais de uma matriz, quando a mesma turma reunir formação geral e itinerário formativo.</small>
                @error('course_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="form-group mb-0">
                <label for="notes">Observações</label>
                <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $class->notes) }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a class="btn btn-outline-secondary" href="{{ $isEdit ? route('academic-years.classes.show', [$academicYear, $class]) : route('academic-years.show', $academicYear) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit" @disabled($readyCourses->isEmpty())>{{ $isEdit ? 'Salvar turma' : 'Cadastrar turma' }}</button>
    </div>
</form>
