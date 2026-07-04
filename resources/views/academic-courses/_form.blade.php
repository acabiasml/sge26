@php($isEdit = $course->exists)

<form method="POST" action="{{ $isEdit ? route('academic-years.courses.update', [$academicYear, $course]) : route('academic-years.courses.store', $academicYear) }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Dados da matriz</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="name">Nome</label>
                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $course->name) }}" placeholder="9º Ano, 3º Ano, Técnico em Móveis..." required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="stage">Etapa</label>
                    <select id="stage" name="stage" class="form-control @error('stage') is-invalid @enderror" required>
                        @foreach (\App\Models\AcademicCourse::STAGE_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('stage', $course->stage) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('stage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="status">Situação</label>
                    <select id="status" name="status" class="form-control @error('status') is-invalid @enderror" required>
                        @foreach (['planejado' => 'Planejado', 'iniciado' => 'Iniciado', 'finalizado' => 'Finalizado', 'suspenso' => 'Suspenso'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $course->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="modality">Modalidade</label>
                    <input id="modality" name="modality" class="form-control @error('modality') is-invalid @enderror" value="{{ old('modality', $course->modality) }}" placeholder="Regular, integral, técnico...">
                    @error('modality') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4 form-group">
                    <label for="starts_period_id">Período inicial</label>
                    <select id="starts_period_id" name="starts_period_id" class="form-control @error('starts_period_id') is-invalid @enderror">
                        <option value="">Não definido</option>
                        @foreach ($academicYear->periods->sortBy('position') as $period)
                            <option value="{{ $period->id }}" @selected((int) old('starts_period_id', $course->starts_period_id) === $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                    @error('starts_period_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4 form-group">
                    <label for="ends_period_id">Período final</label>
                    <select id="ends_period_id" name="ends_period_id" class="form-control @error('ends_period_id') is-invalid @enderror">
                        <option value="">Não definido</option>
                        @foreach ($academicYear->periods->sortBy('position') as $period)
                            <option value="{{ $period->id }}" @selected((int) old('ends_period_id', $course->ends_period_id) === $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                    @error('ends_period_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="class_hour_minutes">Minutos da hora-aula</label>
                    <input id="class_hour_minutes" name="class_hour_minutes" data-mask="digits" data-mask-max="3" inputmode="numeric" autocomplete="off" class="form-control @error('class_hour_minutes') is-invalid @enderror" value="{{ old('class_hour_minutes', $course->class_hour_minutes ?? 50) }}" required>
                    @error('class_hour_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-9 form-group">
                    <label for="notes">Observações</label>
                    <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $course->notes) }}</textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="custom-control custom-checkbox">
                <input class="custom-control-input" id="active" name="active" type="checkbox" value="1" @checked(old('active', $course->active ?? true))>
                <label class="custom-control-label" for="active">Matriz ativa</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a class="btn btn-outline-secondary" href="{{ $isEdit ? route('academic-years.courses.show', [$academicYear, $course]) : route('academic-years.show', $academicYear) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Salvar matriz' : 'Cadastrar matriz' }}</button>
    </div>
</form>
