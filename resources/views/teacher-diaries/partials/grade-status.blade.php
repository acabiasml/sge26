<strong>{{ $average['value'] ?? '—' }}</strong>
@if(($average['completed_assessments'] ?? 0) === 0)
    <span class="d-block small text-muted">{{ __('Sem notas') }}</span>
@elseif(! $average['complete'])
    <span class="d-block small text-muted">{{ $average['completed_assessments'] }} / {{ $average['total_assessments'] }} · {{ __('Notas incompletas') }}</span>
@elseif(($average['period_passing_score'] ?? null) !== null && ($average['value'] ?? 0) >= $average['period_passing_score'])
    <span class="d-block small"><i class="fas fa-check-circle" aria-hidden="true"></i> {{ __('Média alcançada') }}</span>
@elseif($average['recovery_required'] && $average['recovery_value'] === null)
    <span class="d-block small"><i class="fas fa-undo" aria-hidden="true"></i> {{ __('Recuperação disponível') }}</span>
@else
    <span class="d-block small text-muted">{{ __('Completa') }}</span>
@endif
@if($average['recovery_value'] !== null)
    <span class="d-block small text-muted">{{ __('Original:') }} {{ $average['regular_value'] }} {{ __('· Recuperação:') }} {{ $average['recovery_value'] }}</span>
@endif
