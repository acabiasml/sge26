@php
    $selectedFormation = $component['formation'] ?? ($componentStage === 'tecnico' ? 'Formação Técnica Profissional' : 'Formação Geral Básica');
    $selectedArea = $component['knowledge_area'] ?? '';
    $selectedComponent = $component['name'] ?? '';
    $record = $component['record'] ?? [];
    $summaryMode = $summaryMode ?? false;
    $preservesMatrixReference = $history->components->contains(fn ($saved) => $saved->name === $selectedComponent && $saved->knowledge_area === $selectedArea && $saved->formation === $selectedFormation && $saved->catalogEntries->contains('matrix_origin', true));
    $catalogRows = collect($historyCatalogs[$componentStage] ?? []);
    $rowFormations = $catalogRows->pluck('formation')->unique()->values()->all();
    if (! in_array($selectedFormation, $rowFormations, true)) $rowFormations[] = $selectedFormation;
    $rowAreas = $catalogRows->where('formation', $selectedFormation)->pluck('area')->unique()->values();
    $rowComponents = $catalogRows->where('formation', $selectedFormation)->where('area', $selectedArea)->pluck('name')->unique()->values();
    $prefix = "year_components[{$yearIndex}][{$componentIndex}]";
@endphp
<tr data-history-component>
    <td>
        <input type="hidden" name="{{ $prefix }}[module_label]" value="{{ $component['module_label'] ?? '' }}">
        <input type="hidden" name="{{ $prefix }}[regulatory_reference]" value="{{ $component['regulatory_reference'] ?? '' }}">
        <input type="hidden" name="{{ $prefix }}[record][completed_workload_hours]" value="{{ $record['completed_workload_hours'] ?? '' }}" @disabled($summaryMode)>
        <select name="{{ $prefix }}[formation]" class="form-control form-control-sm" data-history-formation aria-label="{{ __('Formação') }}" required>
            @if(! in_array($selectedFormation, $rowFormations, true))<option value="{{ $selectedFormation }}" selected>{{ $selectedFormation }}</option>@endif
            @foreach($rowFormations as $formation)<option value="{{ $formation }}" @selected($selectedFormation === $formation)>{{ __($formation) }}</option>@endforeach
        </select>
        <small class="d-block text-muted mt-1" data-history-formation-inactive hidden>{{ __('Formação desmarcada: estes componentes não serão incluídos ao salvar.') }}</small>
    </td>
    <td>
        <select name="{{ $prefix }}[knowledge_area]" class="form-control form-control-sm" data-history-area aria-label="{{ __('Área') }}" required>
            <option value="">{{ __('Selecione') }}</option>
            @foreach($rowAreas as $area)<option value="{{ $area }}" @selected($selectedArea === $area)>{{ $area }}</option>@endforeach
            @if($selectedArea && ! $rowAreas->contains($selectedArea) && ($preservesMatrixReference || \App\Support\CurriculumAreaStage::compatible($selectedArea, $componentStage)))<option value="{{ $selectedArea }}" selected>{{ $selectedArea }} · {{ __('Registro existente') }}</option>@endif
        </select>
    </td>
    <td>
        <select class="form-control form-control-sm" data-history-component-select aria-label="{{ __('Componente') }}" required >
            <option value="">{{ __('Selecione') }}</option>
            @foreach($rowComponents as $name)<option value="{{ $name }}" @selected($selectedComponent === $name)>{{ $name }}</option>@endforeach
            @if($selectedComponent && ! $rowComponents->contains($selectedComponent))<option value="{{ $selectedComponent }}" selected>{{ $selectedComponent }}</option>@endif
        </select>
        <input name="{{ $prefix }}[name]" class="form-control form-control-sm" data-history-component-input aria-label="{{ __('Componente') }}" value="{{ $selectedComponent }}" type="hidden" required>
    </td>
    <td data-history-record-fields @if($summaryMode) hidden @endif>
        <div class="sge-history-record-grid">
            <label><span class="sr-only">{{ __('Nota/conceito') }}</span><input name="{{ $prefix }}[record][score_label]" class="form-control form-control-sm" value="{{ $record['score_label'] ?? '' }}" @disabled($summaryMode)></label>
            <details><summary>{{ __('Dados complementares do registro') }}</summary>
                @foreach(['score_numeric' => 'Nota numérica', 'workload_hours' => 'Carga horária', 'frequency_label' => 'Frequência', 'frequency_percentage' => 'Frequência (%)', 'absences' => 'Faltas', 'result' => 'Resultado'] as $field => $label)
                    <label><span>{{ __($label) }}</span><input name="{{ $prefix }}[record][{{ $field }}]" class="form-control form-control-sm" value="{{ $record[$field] ?? '' }}" @disabled($summaryMode)></label>
                @endforeach
            </details>
        </div>
    </td>
    <td class="text-right"><button class="btn btn-sm btn-outline-danger sge-icon-action" type="button" data-remove-history-row aria-label="{{ __('Remover componente deste ano') }}" title="{{ __('Remover componente deste ano') }}"><i class="fas fa-trash" aria-hidden="true"></i></button></td>
</tr>
