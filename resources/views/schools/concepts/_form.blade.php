<form method="POST" action="{{ $action }}" class="sge-concept-form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-row">
        <div class="form-group col-sm-6 col-lg-2">
            <label for="concept_sort_order_{{ $concept->id ?? 'new' }}">Ordem</label>
            <input
                id="concept_sort_order_{{ $concept->id ?? 'new' }}"
                name="sort_order"
                type="number"
                min="0"
                max="999"
                inputmode="numeric"
                class="form-control @error('sort_order') is-invalid @enderror"
                value="{{ old('sort_order', $concept->sort_order) }}"
                required
            >
            @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group col-lg-4">
            <label for="concept_name_{{ $concept->id ?? 'new' }}">Nome do conceito</label>
            <input
                id="concept_name_{{ $concept->id ?? 'new' }}"
                name="name"
                class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $concept->name) }}"
                autocomplete="off"
                required
            >
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group col-sm-6 col-lg-2">
            <label for="concept_abbreviation_{{ $concept->id ?? 'new' }}">Abreviatura</label>
            <input
                id="concept_abbreviation_{{ $concept->id ?? 'new' }}"
                name="abbreviation"
                maxlength="20"
                class="form-control @error('abbreviation') is-invalid @enderror"
                value="{{ old('abbreviation', $concept->abbreviation) }}"
                autocomplete="off"
                required
            >
            @error('abbreviation') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group col-lg-4">
            <label for="concept_effective_from_{{ $concept->id ?? 'new' }}">Vigente a partir de</label>
            <input
                id="concept_effective_from_{{ $concept->id ?? 'new' }}"
                name="effective_from"
                type="date"
                class="form-control @error('effective_from') is-invalid @enderror"
                value="{{ old('effective_from', $concept->effective_from?->format('Y-m-d') ?? now()->toDateString()) }}"
                required
            >
            @error('effective_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="form-row align-items-end">
        <div class="form-group col-sm-6 col-lg-3">
            <label for="concept_minimum_score_{{ $concept->id ?? 'new' }}">Nota mínima</label>
            <input
                id="concept_minimum_score_{{ $concept->id ?? 'new' }}"
                name="minimum_score"
                data-mask="decimal"
                inputmode="decimal"
                class="form-control @error('minimum_score') is-invalid @enderror"
                value="{{ old('minimum_score', $concept->minimum_score) }}"
            >
            @error('minimum_score') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group col-sm-6 col-lg-3">
            <label for="concept_maximum_score_{{ $concept->id ?? 'new' }}">Nota máxima</label>
            <input
                id="concept_maximum_score_{{ $concept->id ?? 'new' }}"
                name="maximum_score"
                data-mask="decimal"
                inputmode="decimal"
                class="form-control @error('maximum_score') is-invalid @enderror"
                value="{{ old('maximum_score', $concept->maximum_score) }}"
            >
            @error('maximum_score') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group col-sm-6 col-lg-2">
            <div class="custom-control custom-checkbox sge-concept-check">
                <input id="concept_minimum_inclusive_{{ $concept->id ?? 'new' }}" name="minimum_inclusive" value="1" type="checkbox" class="custom-control-input" @checked(old('minimum_inclusive', $concept->minimum_inclusive))>
                <label for="concept_minimum_inclusive_{{ $concept->id ?? 'new' }}" class="custom-control-label">Inclui mínima</label>
            </div>
        </div>

        <div class="form-group col-sm-6 col-lg-2">
            <div class="custom-control custom-checkbox sge-concept-check">
                <input id="concept_maximum_inclusive_{{ $concept->id ?? 'new' }}" name="maximum_inclusive" value="1" type="checkbox" class="custom-control-input" @checked(old('maximum_inclusive', $concept->maximum_inclusive))>
                <label for="concept_maximum_inclusive_{{ $concept->id ?? 'new' }}" class="custom-control-label">Inclui máxima</label>
            </div>
        </div>

        <div class="form-group col-lg-2 text-lg-right">
            <button class="btn btn-primary btn-block" type="submit">
                <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
            </button>
        </div>
    </div>
</form>
