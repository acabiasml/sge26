@php
    $formId = 'catalog-'.($catalogComponent->id ?: 'new');
    $submitted = old('catalog_form') === $formId;
    $selectedStage = $submitted ? old('education_stage', 'fundamental') : ($catalogComponent->education_stage ?: 'fundamental');
    if (! isset($config[$selectedStage])) $selectedStage = 'fundamental';
    $selectedFormation = $submitted ? old('formation') : ($catalogComponent->formation ?: $config[$selectedStage]['formations'][0]);
    $selectedArea = $submitted ? old('knowledge_area_id') : $catalogComponent->knowledge_area_id;
    $selectedName = $submitted ? old('name') : $catalogComponent->name;
    $selectedActive = $submitted ? old('active') : (! $catalogComponent->exists || $catalogComponent->active);
@endphp
<input type="hidden" name="catalog_form" value="{{ $formId }}">
<div class="form-row">
    <div class="form-group col-md-6"><label for="{{ $formId }}-stage">{{ __('Etapa') }}</label><select id="{{ $formId }}-stage" name="education_stage" class="form-control" data-catalog-stage required>@foreach($stages as $stage => $label)<option value="{{ $stage }}" @selected($selectedStage === $stage)>{{ __($label) }}</option>@endforeach</select></div>
    <div class="form-group col-md-6"><label for="{{ $formId }}-formation">{{ __('Formação') }}</label><select id="{{ $formId }}-formation" name="formation" class="form-control" data-catalog-formation required>@foreach($config[$selectedStage]['formations'] as $formation)<option value="{{ $formation }}" @selected($selectedFormation === $formation)>{{ __($formation) }}</option>@endforeach</select></div>
    <div class="form-group col-md-6"><label for="{{ $formId }}-area">{{ __('Área') }}</label><select id="{{ $formId }}-area" name="knowledge_area_id" class="form-control" data-catalog-area required><option value="">{{ __('Selecione') }}</option>@foreach($config[$selectedStage]['areas'] as $area)<option value="{{ $area['id'] }}" @selected((string) $selectedArea === (string) $area['id'])>{{ $area['name'] }}</option>@endforeach</select></div>
    <div class="form-group col-md-6"><label for="{{ $formId }}-name">{{ __('Componente') }}</label><input id="{{ $formId }}-name" name="name" class="form-control" maxlength="255" value="{{ $selectedName }}" required></div>
</div>
<input type="hidden" name="active" value="0"><label class="d-block"><input type="checkbox" name="active" value="1" @checked($selectedActive)> {{ __('Ativo') }}</label>
<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1" aria-hidden="true"></i>{{ __('Salvar') }}</button>
