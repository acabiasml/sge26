@php
    $labelArea = $labelArea ?? null;
    $areaSchoolId = $labelSchoolId ?? $history->school_id ?? $academicYear->school_id ?? $letterhead['school']->id ?? $school->id ?? null;
    $areaAuthorization = $labelArea ? app(\App\Support\TechnicalProgramAuthorization::class)->forArea($labelArea, $areaSchoolId, $labelCourse ?? null) : null;
@endphp
@if(filled($areaAuthorization))<span class="sge-area-name">{{ $labelName ?? $labelArea->name }}</span><small class="sge-area-subtitle">{{ $areaAuthorization }}</small>@else{{ $labelName ?? $labelArea?->name ?? __('Área não definida') }}@endif
