@extends('layouts.app')

@section('title', 'Períodos avaliativos')
@section('page-title', 'Períodos: '.$academicYear->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="Voltar ao ano letivo" title="Voltar ao ano letivo"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
    @php($periods = $academicYear->periods->sortBy('position'))

    <div class="row">
        <div class="col-lg-4 mb-4">
            <section class="card shadow h-100 sge-academic-context sge-period-context" aria-labelledby="period-context-title">
                <div class="card-header py-3"><h2 id="period-context-title" class="h6 m-0 font-weight-bold text-primary">Contexto</h2></div>
                <div class="card-body">
                    <div class="sge-context-school-mark"><i class="fas fa-school" aria-hidden="true"></i></div>
                    <h3 class="h6 font-weight-bold">{{ $academicYear->school?->name }}</h3>
                    <p class="small text-muted">{{ $academicYear->name }} · {{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}</p>
                    <div class="sge-period-context-metrics" aria-label="Resumo dos períodos cadastrados">
                        <div><strong>{{ $periods->count() }}</strong><span>períodos</span></div>
                        <div><strong>{{ $periods->sum(fn ($period) => $period->schoolDayCount()) }}</strong><span>dias nos períodos</span></div>
                    </div>
                    <p class="small mb-0">Cada período reúne as datas letivas, as regras de avaliação e o fechamento dos diários.</p>
                </div>
            </section>
        </div>
        <div class="col-lg-8 mb-4">
            <section class="card shadow h-100 sge-new-period-panel" aria-labelledby="new-period-title">
                <div class="card-header py-3"><h2 id="new-period-title" class="h6 m-0 font-weight-bold text-primary">Novo período avaliativo</h2></div>
                <div class="card-body">
                    @if ($canChangeCalendar)
                        <form method="POST" action="{{ route('academic-years.periods.store', $academicYear) }}">
                            @csrf
                            <div class="row sge-period-form-row">
                                <div class="col-md-4 form-group"><label for="period_name">Nome</label><input id="period_name" name="name" class="form-control" placeholder="1º Bimestre" required></div>
                                <div class="col-md-2 form-group"><label for="position">Ordem</label><input id="position" name="position" type="number" min="1" class="form-control" value="{{ $academicYear->periods->count() + 1 }}" required></div>
                                <div class="col-md-3 form-group"><label for="period_starts_at">Início</label><input id="period_starts_at" name="starts_at" type="date" class="form-control" required></div>
                                <div class="col-md-3 form-group"><label for="period_ends_at">Fim</label><input id="period_ends_at" name="ends_at" type="date" class="form-control" required></div>
                            </div>
                            <div class="form-group mb-2"><label for="period_notes">Observações</label><input id="period_notes" name="notes" class="form-control" placeholder="Opcional"></div>
                            <div class="sge-weekday-options mb-3">
                                <input type="checkbox" class="sge-weekday-input" id="period_ignore_saturdays" name="ignore_saturdays" value="1" checked>
                                <label class="sge-weekday-option" for="period_ignore_saturdays"><span class="sge-weekday-icon"><i class="fas fa-calendar-day" aria-hidden="true"></i></span><span><strong>Sábados</strong><small>Manter como fim de semana.</small></span></label>
                                <input type="checkbox" class="sge-weekday-input" id="period_ignore_sundays" name="ignore_sundays" value="1" checked>
                                <label class="sge-weekday-option" for="period_ignore_sundays"><span class="sge-weekday-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></span><span><strong>Domingos</strong><small>Manter como fim de semana.</small></span></label>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Criar período</button>
                        </form>
                    @else
                        <p class="mb-0 text-muted">Calendário aprovado. Períodos avaliativos estão bloqueados para edição.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <section class="card shadow sge-periods-panel" aria-labelledby="periods-title">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h2 id="periods-title" class="h6 m-0 font-weight-bold text-primary">Períodos cadastrados</h2>
            <span class="sge-periods-count">{{ $periods->count() }}</span>
        </div>
        <div class="card-body">
            @forelse ($periods as $period)
                @php($periodSchoolDays = $period->schoolDayCount())
                @php($diarySummaries = $periodDiaryStatus->get($period->id, collect()))
                @php($confirmedDiaries = $diarySummaries->filter(fn ($summary) => $summary['confirmation']?->confirmed)->count())
                @php($pendingDiaries = $diarySummaries->filter(fn ($summary) => $summary['pending']['is_pending'])->count())
                @php($canConsolidatePeriod = $diarySummaries->isNotEmpty() && $confirmedDiaries === $diarySummaries->count() && $pendingDiaries === 0)
                @php($consolidation = $period->diaryConsolidation)
                @php($useOldForPeriod = (int) old('period_form_id') === $period->id)
                @php($assessmentErrorKeys = ['assessment_count', 'weights', 'assessment_names', 'recovery_mode', 'recovery_weight', 'recovery_replaced_position'])
                @php($periodHasAssessmentErrors = $useOldForPeriod && collect($assessmentErrorKeys)->contains(fn ($key) => $errors->has($key) || $errors->has($key.'.*')))

                <article class="sge-period-card">
                    <div class="sge-period-card-header">
                        <div class="sge-period-card-title">
                            <span class="sge-period-position" aria-label="Ordem {{ $period->position }}">
                                <span class="sge-period-position-label">Ordem</span>
                                <strong>{{ $period->position }}</strong>
                            </span>
                            <div><strong>{{ $period->name }}</strong><span>{{ $period->starts_at?->format('d/m/Y') }} a {{ $period->ends_at?->format('d/m/Y') }}</span></div>
                        </div>
                        <div class="sge-period-card-stats">
                            <span><i class="fas fa-calendar-check" aria-hidden="true"></i>{{ $periodSchoolDays }} dias letivos</span>
                            <span><i class="fas fa-clipboard-list" aria-hidden="true"></i>{{ $period->assessmentRules->count() ?: '—' }} avaliações</span>
                            <span class="badge badge-{{ $consolidation?->consolidated ? 'success' : 'secondary' }}">{{ $consolidation?->consolidated ? 'Consolidado' : 'Aberto' }}</span>
                        </div>
                        @if ($canChangeCalendar)
                            <form method="POST" action="{{ route('academic-years.periods.destroy', [$academicYear, $period]) }}" onsubmit="return confirm('Remover este período?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Remover período {{ $period->name }}" title="Remover período"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                            </form>
                        @endif
                    </div>

                    <section class="sge-period-assessments mb-3" aria-labelledby="diary-closing-{{ $period->id }}">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                                <div>
                                    <h3 id="diary-closing-{{ $period->id }}" class="h6 font-weight-bold text-primary mb-1"><i class="fas fa-check-double mr-1" aria-hidden="true"></i>Fechamento dos diários</h3>
                                    <p class="small text-muted mb-0">A gestão consolida o período apenas quando todos os diários estiverem confirmados e sem pendências.</p>
                                </div>
                                <div class="btn-group mt-2 mt-md-0">
                                    @if($consolidation?->consolidated)
                                        <button class="btn btn-sm btn-outline-warning" type="button" data-toggle="collapse" data-target="#reopen-period-{{ $period->id }}" aria-expanded="false" aria-controls="reopen-period-{{ $period->id }}"><i class="fas fa-unlock mr-1" aria-hidden="true"></i>Reabrir</button>
                                    @else
                                        <form method="POST" action="{{ route('academic-years.periods.diaries.consolidate', [$academicYear, $period]) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-success" type="submit" @disabled(! $canConsolidatePeriod)><i class="fas fa-check-double mr-1" aria-hidden="true"></i>Consolidar período</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="sge-period-context-metrics mb-3" aria-label="Resumo do fechamento dos diários">
                                <div><strong>{{ $diarySummaries->count() }}</strong><span>diários</span></div>
                                <div><strong>{{ $confirmedDiaries }}</strong><span>confirmados</span></div>
                                <div><strong>{{ $pendingDiaries }}</strong><span>com pendência</span></div>
                                <div><strong>{{ max(0, $diarySummaries->count() - $confirmedDiaries) }}</strong><span>aguardando</span></div>
                            </div>

                            @if($consolidation?->consolidated)
                                <p class="small text-success mb-2"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i>Consolidado em {{ $consolidation->consolidated_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} por {{ $consolidation->consolidatedBy?->full_name ?? 'gestão' }}.</p>
                                <div class="collapse" id="reopen-period-{{ $period->id }}">
                                    <form method="POST" action="{{ route('academic-years.periods.diaries.reopen', [$academicYear, $period]) }}" class="border rounded p-3 mb-3">
                                        @csrf
                                        <label for="period_reopen_reason_{{ $period->id }}">Motivo da reabertura</label>
                                        <div class="input-group"><input id="period_reopen_reason_{{ $period->id }}" name="reopen_reason" class="form-control" required><div class="input-group-append"><button class="btn btn-warning" type="submit">Confirmar reabertura</button></div></div>
                                    </form>
                                </div>
                            @elseif(! $canConsolidatePeriod)
                                <p class="small text-muted mb-2">Ainda há diários aguardando confirmação ou com pendências.</p>
                            @endif

                            @if($consolidation?->reopened_at && ! $consolidation?->consolidated)
                                <p class="small text-warning mb-2"><i class="fas fa-history mr-1" aria-hidden="true"></i>Reaberto em {{ $consolidation->reopened_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} por {{ $consolidation->reopenedBy?->full_name ?? 'gestão' }}: {{ $consolidation->reopen_reason }}</p>
                            @endif

                            <details>
                                <summary><span><i class="fas fa-list-check" aria-hidden="true"></i>Ver diários deste período</span><small>{{ $diarySummaries->count() }} registros</small></summary>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead><tr><th>Turma</th><th>Componente</th><th>Docência</th><th>Situação</th><th>Pendências</th><th>Ações</th></tr></thead>
                                        <tbody>
                                            @forelse($diarySummaries as $summary)
                                                @php($assignment = $summary['assignment'])
                                                <tr>
                                                    <td>{{ $assignment->schoolClass?->name }}</td>
                                                    <td>{{ $assignment->component?->name }}</td>
                                                    <td>{{ $assignment->teacher?->full_name ?? 'Não definida' }}</td>
                                                    <td><span class="badge badge-{{ $summary['confirmation']?->confirmed ? 'success' : 'warning' }}">{{ $summary['confirmation']?->confirmed ? 'Confirmado' : 'Em aberto' }}</span></td>
                                                    <td class="small">{{ count($summary['pending']['attendance_without_content']) }} freq. sem conteúdo · {{ count($summary['pending']['content_without_attendance']) }} conteúdo sem freq. · {{ $summary['pending']['missing_grades'] }} notas</td>
                                                    <td>
                                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('teacher-diaries.show', [$assignment->schoolClass, $assignment->component, 'period' => $period->id]) }}"><i class="fas fa-eye mr-1" aria-hidden="true"></i>Abrir</a>
                                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('teacher-diaries.pdf', [$assignment->schoolClass, $assignment->component, 'period' => $period->id]) }}"><i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>PDF</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6">Nenhum diário ativo neste período.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </div>
                    </section>

                    <details class="sge-period-assessments" data-assessment-rules-form @if($periodHasAssessmentErrors) open @endif>
                        <summary><span><i class="fas fa-sliders-h" aria-hidden="true"></i>Configurar avaliações e recuperação</span><small>Regras aplicadas aos diários</small></summary>
                        <div class="pt-3">
                            <form method="POST" action="{{ route('academic-years.periods.assessment-rules.update', [$academicYear, $period]) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="period_form_id" value="{{ $period->id }}">
                                @if($periodHasAssessmentErrors)
                                    <div class="alert alert-danger" role="alert">
                                        <strong>Não foi possível salvar as configurações deste período.</strong>
                                        <ul class="mb-0 pl-3">
                                            @foreach($errors->all() as $message)
                                                <li>{{ $message }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @php($ruleCount = (int) ($useOldForPeriod ? old('assessment_count') : max(1, $period->assessmentRules->count())))
                                <div class="row align-items-end">
                                    <div class="col-md-3 form-group"><label for="assessment_count_{{ $period->id }}">Avaliações</label><select id="assessment_count_{{ $period->id }}" name="assessment_count" class="form-control" data-assessment-count>@for($count=1;$count<=10;$count++)<option value="{{ $count }}" @selected($ruleCount === $count)>{{ $count }}</option>@endfor</select></div>
                                    <div class="col-md-9 form-group mb-0"><div class="row">@for($position=1;$position<=10;$position++)@php($rule=$period->assessmentRules->firstWhere('position',$position))<div class="col-sm-6 mb-2" data-assessment-weight="{{ $position }}"><div class="sge-assessment-definition"><div><label for="assessment_name_{{ $period->id }}_{{ $position }}">Avaliação {{ $position }}</label><input id="assessment_name_{{ $period->id }}_{{ $position }}" name="assessment_names[]" class="form-control" value="{{ $useOldForPeriod ? old('assessment_names.'.($position-1), $rule?->name ?? 'Avaliação '.$position) : ($rule?->name ?? 'Avaliação '.$position) }}"></div><div><label for="weight_{{ $period->id }}_{{ $position }}">Peso</label><input id="weight_{{ $period->id }}_{{ $position }}" name="weights[]" type="number" min="1" max="100" class="form-control" value="{{ $useOldForPeriod ? old('weights.'.($position-1), $rule?->weight ?? 1) : ($rule?->weight ?? 1) }}"></div></div></div>@endfor</div></div>
                                </div>
                                <fieldset class="sge-recovery-options">
                                    <legend>Recuperação</legend>
                                    @php($recoveryMode = $useOldForPeriod ? old('recovery_mode', $period->recovery_mode ?? \App\Models\AcademicPeriod::RECOVERY_NONE) : ($period->recovery_mode ?? \App\Models\AcademicPeriod::RECOVERY_NONE))
                                    @foreach(\App\Models\AcademicPeriod::RECOVERY_MODE_LABELS as $mode => $label)
                                        <div class="custom-control custom-radio sge-recovery-choice"><input class="custom-control-input" id="recovery_{{ $period->id }}_{{ $mode }}" type="radio" name="recovery_mode" value="{{ $mode }}" @checked($recoveryMode === $mode) data-recovery-mode><label class="custom-control-label" for="recovery_{{ $period->id }}_{{ $mode }}">{{ $label }}</label></div>
                                    @endforeach
                                    <div class="row mt-2" data-recovery-details>
                                        <div class="col-md-4 form-group" data-recovery-weight><label for="recovery_weight_{{ $period->id }}">Peso da recuperação</label><input id="recovery_weight_{{ $period->id }}" name="recovery_weight" type="number" min="1" max="100" class="form-control" value="{{ $useOldForPeriod ? old('recovery_weight', $period->recovery_weight ?? 1) : ($period->recovery_weight ?? 1) }}"></div>
                                        <div class="col-md-5 form-group" data-recovery-replace><label for="recovery_replace_{{ $period->id }}">Avaliação substituída</label><select id="recovery_replace_{{ $period->id }}" name="recovery_replaced_position" class="form-control"><option value="">Selecione</option>@for($position=1;$position<=10;$position++)<option value="{{ $position }}" @selected((int) ($useOldForPeriod ? old('recovery_replaced_position', $period->recoveryReplacedRule?->position ?? 1) : ($period->recoveryReplacedRule?->position ?? 1)) === $position) data-recovery-option="{{ $position }}">Avaliação {{ $position }}</option>@endfor</select></div>
                                    </div>
                                </fieldset>
                                <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i>Salvar configuração</button>
                            </form>
                        </div>
                    </details>
                </article>
            @empty
                <div class="sge-empty-state"><i class="fas fa-layer-group" aria-hidden="true"></i><p>Nenhum período avaliativo cadastrado.</p></div>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-assessment-rules-form]').forEach((container) => {
    const count = container.querySelector('[data-assessment-count]');
    const sync = () => {
        const selectedCount = Number(count.value);
        container.querySelectorAll('[data-assessment-weight]').forEach((field) => { const visible = Number(field.dataset.assessmentWeight) <= selectedCount; field.hidden = !visible; field.querySelector('input').disabled = !visible; });
        container.querySelectorAll('[data-recovery-option]').forEach((option) => { const available = Number(option.dataset.recoveryOption) <= selectedCount; option.hidden = !available; option.disabled = !available; });
    };
    const syncRecovery = () => {
        const mode = container.querySelector('[data-recovery-mode]:checked')?.value;
        const weight = container.querySelector('[data-recovery-weight]');
        const replacement = container.querySelector('[data-recovery-replace]');
        weight.hidden = mode !== 'weighted';
        replacement.hidden = mode !== 'replace_assessment';
        weight.querySelector('input').disabled = mode !== 'weighted';
        replacement.querySelector('select').disabled = mode !== 'replace_assessment';
    };
    count.addEventListener('change', sync);
    container.querySelectorAll('[data-recovery-mode]').forEach((input) => input.addEventListener('change', syncRecovery));
    sync();
    syncRecovery();
});
</script>
@endpush
