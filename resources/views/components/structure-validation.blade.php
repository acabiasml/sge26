@props([
    'issues' => [],
    'title' => 'Validação da estrutura',
    'empty' => 'Nenhuma inconsistência encontrada.',
])

@php($summary = \App\Support\AcademicStructureValidator::summarize($issues))
@php($groupedIssues = collect($issues)->groupBy(fn ($issue) => implode('|', [
    $issue['level'] ?? 'info',
    $issue['title'] ?? '',
    $issue['action_label'] ?? '',
    $issue['action_url'] ?? '',
]))->values())

<section class="card shadow mb-4 sge-structure-validation" aria-labelledby="structure-validation-title">
    <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h2 id="structure-validation-title" class="h6 m-0 font-weight-bold text-primary">{{ $title }}</h2>
            <p class="small text-muted mb-0 mt-1">Use este painel antes de iniciar matrículas, horários e diários.</p>
        </div>
        <div class="sge-validation-summary mt-2 mt-md-0" aria-label="Resumo da validação">
            <span class="sge-validation-pill sge-validation-pill-danger">{{ $summary['errors'] }} erro(s)</span>
            <span class="sge-validation-pill sge-validation-pill-warning">{{ $summary['warnings'] }} aviso(s)</span>
            <span class="sge-validation-pill sge-validation-pill-info">{{ $summary['info'] }} informação(ões)</span>
        </div>
    </div>
    <div class="card-body">
        @if($summary['total'] === 0)
            <div class="sge-validation-empty">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <div>
                    <strong>{{ $empty }}</strong>
                    <span>A estrutura está coerente para esta etapa.</span>
                </div>
            </div>
        @else
            <div class="sge-validation-list">
                @foreach($groupedIssues as $group)
                    @php($issue = $group->first())
                    @php($isGrouped = $group->count() > 1)
                    <article class="sge-validation-item sge-validation-item-{{ $issue['level'] }}">
                        <i class="fas fa-{{ $issue['level'] === 'danger' ? 'exclamation-triangle' : ($issue['level'] === 'warning' ? 'exclamation-circle' : 'info-circle') }}" aria-hidden="true"></i>
                        <div>
                            <strong>
                                {{ $issue['title'] }}
                                @if($isGrouped)
                                    <span class="sge-validation-count">{{ $group->count() }}</span>
                                @endif
                            </strong>
                            @if($isGrouped)
                                <p>{{ $group->count() }} ocorrências encontradas. Abra os detalhes para conferir cada item.</p>
                                <details class="sge-validation-details">
                                    <summary>Ver detalhes</summary>
                                    <ul>
                                        @foreach($group as $groupIssue)
                                            <li>{{ $groupIssue['description'] }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <p>{{ $issue['description'] }}</p>
                            @endif
                        </div>
                        @if(! empty($issue['action_url']) && ! empty($issue['action_label']))
                            <a class="btn btn-sm btn-outline-primary" href="{{ $issue['action_url'] }}">{{ $issue['action_label'] }}</a>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
