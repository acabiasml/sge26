@php
    $automatic = $school->exists && $school->usesAutomaticLetterhead();
    $elementaryText = $automatic ? '' : ($school->letterheadTextsForStages(['fundamental'])[0] ?? '');
    $highSchoolText = $automatic ? '' : ($school->letterheadTextsForStages(['medio'])[0] ?? '');
    $sameText = $elementaryText === $highSchoolText;
    $mode = old('letterhead_mode', $automatic ? 'automatic' : ($sameText ? 'shared' : 'by_stage'));
    $byStage = $mode === 'by_stage';
    $sharedText = $sameText ? $elementaryText : ($school->letterhead_text ?? $elementaryText);
    $firstText = old('letterhead_text', $byStage ? $elementaryText : $sharedText);
    $secondText = old('letterhead_text_high_school', $highSchoolText);
@endphp

<details class="border rounded p-3 mb-3" @if($errors->hasAny(['letterhead_mode', 'letterhead_text', 'letterhead_text_high_school'])) open @endif>
<summary class="font-weight-bold">
    {{ __('Linha institucional do cabeçalho') }}
    <small class="text-muted">{{ $mode === 'automatic' ? __('· Gerada pelos atos cadastrais') : __('· Personalizada') }}</small>
</summary>
<fieldset class="mt-3" aria-describedby="school-letterhead-help" x-data="{
    mode: @js($mode),
    shared: @js($byStage ? $sharedText : $firstText),
    elementary: @js($byStage ? $firstText : null),
    highSchool: @js($byStage ? $secondText : null),
    get text() { return this.mode === 'by_stage' ? (this.elementary ?? this.shared) : this.shared },
    set text(value) {
        if (this.mode === 'by_stage') this.elementary = value;
        else this.shared = value;
    },
    get secondText() { return this.highSchool ?? this.shared },
    set secondText(value) { this.highSchool = value }
}">
    <legend class="sr-only">{{ __('Linha institucional do cabeçalho') }}</legend>
    <p class="small text-muted" id="school-letterhead-help">{{ __('O modo automático usa o credenciamento e as autorizações vigentes cadastrados na escola.') }}</p>
    <div class="form-group">
        <label for="letterhead_mode">{{ __('Modo do cabeçalho') }}</label>
        <select id="letterhead_mode" name="letterhead_mode" x-model="mode" class="form-control @error('letterhead_mode') is-invalid @enderror">
            <option value="automatic" @selected($mode === 'automatic')>{{ __('Automático, pelos atos cadastrados') }}</option>
            <option value="shared" @selected($mode === 'shared')>{{ __('Mesmo texto para Fundamental e Médio') }}</option>
            <option value="by_stage" @selected($byStage)>{{ __('Um texto para cada etapa') }}</option>
        </select>
        @error('letterhead_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-row" x-bind:hidden="mode === 'automatic'" @if($mode === 'automatic') hidden @endif>
        <div class="form-group mb-0" :class="mode === 'by_stage' ? 'col-md-6' : 'col-12'">
            <label for="letterhead_text" x-text="mode === 'by_stage' ? @js(__('Ensino Fundamental')) : @js(__('Texto do cabeçalho'))">{{ $byStage ? __('Ensino Fundamental') : __('Texto do cabeçalho') }}</label>
            <textarea id="letterhead_text" name="letterhead_text" rows="3" maxlength="5000" x-model="text" class="form-control @error('letterhead_text') is-invalid @enderror"
                x-bind:disabled="mode === 'automatic'" x-bind:required="mode !== 'automatic'" @disabled($mode === 'automatic') @required($mode !== 'automatic')>{{ $firstText }}</textarea>
            @error('letterhead_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-group col-md-6 mb-0" x-bind:hidden="mode !== 'by_stage'" @if(! $byStage) hidden @endif>
            <label for="letterhead_text_high_school">{{ __('Ensino Médio') }}</label>
            <textarea id="letterhead_text_high_school" name="letterhead_text_high_school" rows="3" maxlength="5000" class="form-control @error('letterhead_text_high_school') is-invalid @enderror"
                x-model="secondText" x-bind:disabled="mode !== 'by_stage'" x-bind:required="mode === 'by_stage'" @disabled(! $byStage) @required($byStage)>{{ $secondText }}</textarea>
            @error('letterhead_text_high_school')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</fieldset>
</details>
