@csrf

@if (isset($academicYear))
    @method('PUT')
@endif

<div class="row">
    <div class="col-md-5 form-group">
        <label for="school_id">Escola</label>
        <select id="school_id" name="school_id" class="form-control @error('school_id') is-invalid @enderror" required>
            <option value="">Selecione</option>
            @foreach ($schools as $school)
                <option value="{{ $school->id }}" @selected(old('school_id', $academicYear->school_id ?? '') == $school->id)>{{ $school->name }}</option>
            @endforeach
        </select>
        @error('school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 form-group">
        <label for="name">Nome</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $academicYear->name ?? '') }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 form-group">
        <label for="reference_year">Ano de referência</label>
        <input id="reference_year" name="reference_year" type="number" min="2000" max="2100" class="form-control @error('reference_year') is-invalid @enderror" value="{{ old('reference_year', $academicYear->reference_year ?? now()->year) }}" required>
        @error('reference_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-3 form-group">
        <label for="starts_at">Início</label>
        <input id="starts_at" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', isset($academicYear) ? $academicYear->starts_at?->toDateString() : '') }}" required>
        @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 form-group">
        <label for="ends_at">Fim</label>
        <input id="ends_at" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', isset($academicYear) ? $academicYear->ends_at?->toDateString() : '') }}" required>
        @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 form-group">
        <label for="approved_at">Aprovação</label>
        <input id="approved_at" name="approved_at" type="date" class="form-control @error('approved_at') is-invalid @enderror" value="{{ old('approved_at', isset($academicYear) ? $academicYear->approved_at?->toDateString() : '') }}">
        @error('approved_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3 form-group">
        <label for="class_hour_minutes">Minutos da hora-aula</label>
        <input id="class_hour_minutes" name="class_hour_minutes" type="number" min="1" max="240" class="form-control @error('class_hour_minutes') is-invalid @enderror" value="{{ old('class_hour_minutes', $academicYear->class_hour_minutes ?? 50) }}" required>
        @error('class_hour_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-3 form-group">
        <label for="minimum_school_days">Mínimo de dias letivos</label>
        <input id="minimum_school_days" name="minimum_school_days" type="number" min="1" max="365" class="form-control @error('minimum_school_days') is-invalid @enderror" value="{{ old('minimum_school_days', $academicYear->minimum_school_days ?? 200) }}" required>
        @error('minimum_school_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-9 form-group">
        <label for="notes">Observações</label>
        <input id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes', $academicYear->notes ?? '') }}">
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

@if (! isset($academicYear))
    <div class="card border-left-primary shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 font-weight-bold text-primary mb-2">Geração inicial do calendário</h2>
            <p class="small text-gray-700 mb-3">
                Ao salvar, o sistema cria os dias do calendário dentro das datas informadas. Os dias comuns entram como letivos, os recessos entram como não letivos e os fins de semana podem ser ignorados automaticamente.
            </p>

            <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" class="custom-control-input" id="ignore_weekends" name="ignore_weekends" value="1" @checked(old('ignore_weekends', true))>
                <label class="custom-control-label" for="ignore_weekends">Ignorar sábados e domingos</label>
            </div>

            @error('recesses') <div class="alert alert-warning">{{ $message }}</div> @enderror

            <div class="row">
                @for ($index = 0; $index < 3; $index++)
                    <div class="col-lg-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <h3 class="h6 font-weight-bold text-gray-800">Recesso {{ $index + 1 }}</h3>
                            <div class="form-group">
                                <label for="recess_{{ $index }}_title">Nome</label>
                                <input id="recess_{{ $index }}_title" name="recesses[{{ $index }}][title]" class="form-control @error("recesses.$index.title") is-invalid @enderror" value="{{ old("recesses.$index.title") }}" placeholder="Recesso escolar">
                                @error("recesses.$index.title") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="recess_{{ $index }}_starts_at">Início</label>
                                <input id="recess_{{ $index }}_starts_at" name="recesses[{{ $index }}][starts_at]" type="date" class="form-control @error("recesses.$index.starts_at") is-invalid @enderror" value="{{ old("recesses.$index.starts_at") }}">
                                @error("recesses.$index.starts_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label for="recess_{{ $index }}_ends_at">Fim</label>
                                <input id="recess_{{ $index }}_ends_at" name="recesses[{{ $index }}][ends_at]" type="date" class="form-control @error("recesses.$index.ends_at") is-invalid @enderror" value="{{ old("recesses.$index.ends_at") }}">
                                @error("recesses.$index.ends_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
@endif

<div class="custom-control custom-checkbox mb-3">
    <input type="checkbox" class="custom-control-input" id="active" name="active" value="1" @checked(old('active', $academicYear->active ?? true))>
    <label class="custom-control-label" for="active">Ano letivo ativo</label>
</div>

<button type="submit" class="btn btn-primary">Salvar</button>
<a href="{{ route('academic-years.index') }}" class="btn btn-outline-secondary">Voltar</a>
