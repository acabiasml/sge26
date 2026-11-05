@php
    $selectedFormation = $component['formation'] ?? 'Formação Geral Básica';
    $selectedArea = $component['knowledge_area'] ?? '';
    $selectedComponent = $component['name'] ?? '';
    $record = $component['record'] ?? [];
    $rowAreas = collect(config("curriculum.stages.{$componentStage}.formations.formacao_geral_basica.areas", []));
    $rowFlexibleFormation = match ($componentStage) { 'medio' => 'Itinerário Formativo', 'tecnico' => 'Formação Técnica Profissional', default => 'Parte Diversificada' };
    $rowFormations = $componentStage === 'tecnico' ? [$rowFlexibleFormation] : ['Formação Geral Básica', $rowFlexibleFormation];
    if ($componentStage === 'tecnico' && empty($component['formation'])) $selectedFormation = $rowFlexibleFormation;
    $rowComponents = collect($rowAreas->firstWhere('name', $selectedArea)['components'] ?? []);
    $prefix = "year_components[{$yearIndex}][{$componentIndex}]";
@endphp
<tr data-history-component>
    <td>
        <input type="hidden" name="{{ $prefix }}[module_label]" value="{{ $component['module_label'] ?? '' }}">
        <input type="hidden" name="{{ $prefix }}[regulatory_reference]" value="{{ $component['regulatory_reference'] ?? '' }}">
        <input type="hidden" name="{{ $prefix }}[record][completed_workload_hours]" value="{{ $record['completed_workload_hours'] ?? '' }}">
        <select name="{{ $prefix }}[formation]" class="form-control form-control-sm" data-history-formation aria-label="{{ __('Formação') }}" required>
            @if(! in_array($selectedFormation, $rowFormations, true))<option value="{{ $selectedFormation }}" selected>{{ $selectedFormation }}</option>@endif
            @foreach($rowFormations as $formation)<option value="{{ $formation }}" @selected($selectedFormation === $formation)>{{ __($formation) }}</option>@endforeach
        </select>
    </td>
    <td>
        @if($componentStage === 'tecnico')
            <input name="{{ $prefix }}[knowledge_area]" class="form-control form-control-sm" data-history-area aria-label="{{ __('Área') }}" value="{{ $selectedArea }}" required>
        @else
            <select name="{{ $prefix }}[knowledge_area]" class="form-control form-control-sm" data-history-area aria-label="{{ __('Área') }}" required>
                <option value="">{{ __('Selecione') }}</option>
                @foreach($rowAreas as $area)<option value="{{ $area['name'] }}" @selected($selectedArea === $area['name'])>{{ $area['name'] }}</option>@endforeach
                @if($selectedArea && ! $rowAreas->pluck('name')->contains($selectedArea) && \App\Support\CurriculumAreaStage::compatible($selectedArea, $componentStage))<option value="{{ $selectedArea }}" selected>{{ $selectedArea }}</option>@endif
            </select>
        @endif
    </td>
    <td>
        <select class="form-control form-control-sm" data-history-component-select aria-label="{{ __('Componente') }}" required @disabled($selectedFormation !== 'Formação Geral Básica')>
            <option value="">{{ __('Selecione') }}</option>
            @foreach($rowComponents as $name)<option value="{{ $name }}" @selected($selectedComponent === $name)>{{ $name }}</option>@endforeach
            @if($selectedComponent && ! $rowComponents->contains($selectedComponent))<option value="{{ $selectedComponent }}" selected>{{ $selectedComponent }}</option>@endif
        </select>
        <input name="{{ $prefix }}[name]" class="form-control form-control-sm" data-history-component-input aria-label="{{ __('Componente') }}" value="{{ $selectedComponent }}" @if($selectedFormation === 'Formação Geral Básica') type="hidden" @else type="text" @endif required>
    </td>
    <td>
        <div class="sge-history-record-grid">
            <label><span class="sr-only">{{ __('Nota/conceito') }}</span><input name="{{ $prefix }}[record][score_label]" class="form-control form-control-sm" value="{{ $record['score_label'] ?? '' }}"></label>
            <details><summary>{{ __('Dados complementares do registro') }}</summary>
                @foreach(['score_numeric' => 'Nota numérica', 'workload_hours' => 'Carga horária', 'frequency_label' => 'Frequência', 'frequency_percentage' => 'Frequência (%)', 'absences' => 'Faltas', 'result' => 'Resultado'] as $field => $label)
                    <label><span>{{ __($label) }}</span><input name="{{ $prefix }}[record][{{ $field }}]" class="form-control form-control-sm" value="{{ $record[$field] ?? '' }}"></label>
                @endforeach
            </details>
        </div>
    </td>
    <td class="text-right"><button class="btn btn-sm btn-outline-danger sge-icon-action" type="button" data-remove-history-row aria-label="{{ __('Remover componente deste ano') }}" title="{{ __('Remover componente deste ano') }}"><i class="fas fa-trash" aria-hidden="true"></i></button></td>
</tr>
