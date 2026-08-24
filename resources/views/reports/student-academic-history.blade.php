<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 150px 18px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.06; }
        @include('reports.partials.letterhead-styles')
        .letterhead { padding-bottom: 6px; }
        .document-title { font-size: 14px; text-transform: uppercase; margin: 5px 0 2px; }
        .student-box, .history-table, .studies-table { border-collapse: collapse; width: 100%; }
        .student-box td { border: 0; padding: 1px 4px; }
        .label { font-weight: 600; }
        .history-table { margin: 0 0 4px; table-layout: fixed; }
        .history-table th, .history-table td, .studies-table th, .studies-table td { border: .55px solid #111; padding: 1px 2px; vertical-align: middle; }
        .history-table th, .studies-table th { background: #f1ede9; font-size: 11px; text-transform: uppercase; }
        .history-table td, .studies-table td { font-size: 11px; }
        .center { text-align: center; }
        .muted { color: #666; }
        .section-title { font-size: 11px; font-weight: 600; margin: 4px 0 2px; text-transform: uppercase; page-break-after: avoid; }
        .section-title-reference { font-weight: 400; margin-left: 6px; text-transform: none; }
        .formation-title { background: #e7dfd9; border: .55px solid #111; font-size: 11px; font-weight: 700; margin-top: 3px; padding: 2px 4px; text-transform: uppercase; page-break-after: avoid; }
        .formation-title-reference { font-weight: 400; margin-left: 6px; text-transform: none; }
        .module-title { border: .55px solid #111; border-bottom: 0; background: #f5f2ef; font-weight: 600; padding: 2px 4px; page-break-after: avoid; }
        .area-group { page-break-inside: avoid; }
        .score-cell { white-space: nowrap; }
        .general-total-label { border-right: 0 !important; white-space: nowrap; }
        .general-total-label-space { border-left: 0 !important; }
        .studies-table { page-break-inside: avoid; }
        .studies-nowrap { white-space: nowrap; }
        .notes { margin: 3px 0 0; }
        .signatures { border-collapse: collapse; margin-top: 62px; page-break-inside: avoid; width: 100%; }
        .signatures td { border: 0; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 280px; padding-top: 6px; }
        .signature-name { display: block; font-weight: 600; }
        .signature-role { display: block; margin-top: 2px; }
        .document-footer { position: fixed; bottom: -20px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 5px; text-align: center; font-size: 11px; color: #333; }
    </style>
</head>
<body>
@php
    $formatCpf = function (?string $cpf): string {
        $digits = preg_replace('/\D/', '', (string) $cpf);

        return strlen($digits) === 11
            ? substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2)
            : ($cpf ?: '-');
    };
    $transcriptModeLabels = ['detailed' => 'Detalhada', 'summary' => 'Global/AP', 'no_transcription' => 'Sem transcrição'];
    $basicFormationReference = $history->education_stage === 'medio'
        ? 'BNCC-EM — Resolução CNE/CP nº 4/2018'
        : 'BNCC — Resolução CNE/CP nº 2/2017';
@endphp

@include('reports.partials.letterhead', [
    'title' => $history->title,
    'letterhead' => $letterhead,
    'issuedDocument' => $issuedDocument,
    'verificationUrl' => $verificationUrl,
])

<table class="student-box">
    <tr>
        <td><span class="label">Estudante:</span> {{ $person->full_name }}</td>
        <td>@if($person->cpf)<span class="label">CPF:</span> {{ $formatCpf($person->cpf) }}@endif</td>
        <td>@if($person->birth_date)<span class="label">Nascimento:</span> {{ $person->birth_date->format('d/m/Y') }}@endif</td>
    </tr>
    <tr>
        @php($birthPlace = collect([$person->birth_city ?: ($person->legacy_metadata['naturalidade'] ?? null), $person->birth_state ?: ($person->legacy_metadata['naturalidade_uf'] ?? null)])->filter()->join(' / '))
        <td>@if($birthPlace)<span class="label">Naturalidade:</span> {{ $birthPlace }}@endif</td>
        <td>@if($person->nationality)<span class="label">Nacionalidade:</span> {{ $person->nationality }}@endif</td>
        <td>@if($person->nis)<span class="label">NIS:</span> {{ $person->nis }}@endif</td>
    </tr>
    @if($person->social_name || $person->student_inep || $person->receives_federal_aid)
    <tr>
        <td>@if($person->social_name)<span class="label">Nome social:</span> {{ $person->social_name }}@endif</td>
        <td>@if($person->student_inep)<span class="label">INEP:</span> {{ $person->student_inep }}@endif</td>
        <td>@if($person->receives_federal_aid)<span class="label">Auxílio federal:</span> Sim@endif</td>
    </tr>
    @endif
    @if($person->mother_name)
    <tr><td colspan="3"><span class="label">Mãe:</span> {{ $person->mother_name }}</td></tr>
    @endif
    @if($person->father_name)
    <tr>
        <td colspan="3"><span class="label">Pai:</span> {{ $person->father_name }}</td>
    </tr>
    @endif
</table>

<div class="section-title">
    Componentes curriculares
    @if($history->education_stage !== 'tecnico')
        <span class="section-title-reference">Fundamento legal: {{ $history->legal_basis }}</span>
    @endif
</div>
@if($history->education_stage === 'tecnico')
    @include('reports.partials.technical-history-matrix', ['history' => $history])
@else
@forelse($history->components->groupBy(fn ($component) => $component->formation ?: '-') as $formation => $formationComponents)
    <div class="formation-title">
        {{ $formation }}
        @if($formation === 'Formação Geral Básica')
            <span class="formation-title-reference">{{ $basicFormationReference }}</span>
        @endif
    </div>
    @if($formationComponents->pluck('module_label')->filter()->isNotEmpty())
        <div class="muted" style="margin:2px 0 3px;">A conclusão de cada módulo assegura sua certificação intermediária; a conclusão de todos os módulos e demais requisitos do curso assegura o diploma técnico.</div>
    @endif
    @foreach($formationComponents->sortBy(fn ($component) => $component->module_label ?: '')->groupBy(fn ($component) => $component->module_label ?: '') as $moduleLabel => $moduleComponents)
    @if($moduleLabel)<div class="module-title">{{ $moduleLabel }}</div>@endif
    <table class="history-table">
        <thead>
            <tr><th rowspan="2" style="width: 22%;">Área</th><th rowspan="2" style="width: 28%;">Componente curricular</th>@foreach($history->years as $year)<th colspan="3" class="center" style="width: {{ 50 / max(1, $history->years->count()) }}%;">{{ $year->label }}</th>@endforeach</tr>
            <tr>@foreach($history->years as $year)<th class="center">N</th><th class="center">CH</th><th class="center">Freq.</th>@endforeach</tr>
        </thead>
        @foreach($moduleComponents->groupBy(fn ($component) => $component->knowledge_area ?: '-') as $area => $areaComponents)
        <tbody class="area-group">
            @foreach($areaComponents as $component)
            <tr>
                @if($loop->first)<td rowspan="{{ $areaComponents->count() }}">{{ $area }}</td>@endif
                <td>{{ $component->name }}</td>
                @foreach($history->years as $year)
                    @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                    <td class="center score-cell">{{ $record?->score_label ?: '-' }}</td>
                    <td class="center score-cell">{{ $record?->workload_hours !== null ? number_format((float) $record->workload_hours, 0, ',', '.').'h' : '-' }}</td>
                    <td class="center score-cell">{{ $record?->frequency_percentage !== null ? number_format((float) $record->frequency_percentage, 1, ',', '.').'%' : '-' }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
        @endforeach
        <tfoot>
            <tr>
                <td colspan="2"><strong>Subtotal de carga horária — {{ $moduleLabel ?: $formation }}</strong></td>
                @foreach($history->years as $year)
                    @php($formationWorkload = $moduleComponents->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $year->id)?->workload_hours ?? 0)))
                    <td class="center">-</td>
                    <td class="center"><strong>{{ $year->transcript_mode === 'no_transcription' ? '-' : number_format($formationWorkload, 0, ',', '.').'h' }}</strong></td>
                    <td class="center">-</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
    @endforeach
@empty
    <table class="history-table"><tr><td colspan="{{ 2 + (3 * $history->years->count()) }}" class="center">Histórico cadastrado sem transcrição de componentes curriculares.</td></tr></table>
@endforelse

<table class="history-table">
    <colgroup>
        <col style="width: 22%;">
        <col style="width: 28%;">
        @foreach($history->years as $year)
            <col style="width: {{ (50 / 3) / max(1, $history->years->count()) }}%;">
            <col style="width: {{ (50 / 3) / max(1, $history->years->count()) }}%;">
            <col style="width: {{ (50 / 3) / max(1, $history->years->count()) }}%;">
        @endforeach
    </colgroup>
    <tr>
        <td class="general-total-label" style="width: 22%;"><strong>Carga horária total geral</strong></td>
        <td class="general-total-label-space" style="width: 28%;"></td>
        @foreach($history->years as $year)
            @php($generalWorkload = $history->components->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $year->id)?->workload_hours ?? 0)))
            <td class="center">-</td>
            <td class="center"><strong>{{ $year->transcript_mode === 'no_transcription' ? '-' : number_format($generalWorkload, 0, ',', '.').'h' }}</strong></td>
            <td class="center">-</td>
        @endforeach
    </tr>
</table>

@php($basicFormationComponents = $history->components->where('formation', 'Formação Geral Básica'))
@php($itineraryComponents = $history->components->where('formation', 'Itinerário Formativo'))
@php($basicFormationHours = $basicFormationComponents->sum(fn ($component) => (float) $component->records->sum('workload_hours')))
@php($itineraryHours = $itineraryComponents->sum(fn ($component) => (float) $component->records->sum('workload_hours')))
@if($basicFormationComponents->isNotEmpty() && $itineraryComponents->isNotEmpty())
    <table class="history-table" style="margin-top:3px;">
        <tr>
            <td><strong>Total de Formação Geral Básica</strong></td>
            <td class="center"><strong>{{ number_format($basicFormationHours, 0, ',', '.').'h' }}</strong></td>
            <td><strong>Total de Itinerário Formativo</strong></td>
            <td class="center"><strong>{{ number_format($itineraryHours, 0, ',', '.').'h' }}</strong></td>
        </tr>
    </table>
@endif
@endif

@php($technicalRegulation = $history->components->pluck('regulatory_reference')->filter()->unique()->join(' '))
@if($technicalRegulation)
    <div class="muted" style="font-size:11px;line-height:1.15;margin:2px 2px 5px;"><strong>Nota legal da formação técnica:</strong> {{ $technicalRegulation }}</div>
@endif

<div class="studies-section">
<div class="section-title">Estudos realizados</div>
<table class="studies-table">
    <thead>
        <tr>
            <th style="width: 18%;">Ano / Série</th>
            <th style="width: 54%;">Estabelecimento / Local</th>
            <th style="width: 14%;">Modalidade</th>
            <th style="width: 14%;">Resultado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($history->years as $year)
            <tr>
                <td class="studies-nowrap">{{ collect([$year->year ?: null, $year->grade_phase ?: $year->label])->filter()->join(' — ') ?: '-' }}</td>
                <td class="studies-nowrap"><strong>{{ $year->school_name ?: '-' }}</strong>@if($location = collect([$year->city, $year->state, $year->country])->filter()->join(' / ')) — {{ $location }}@endif</td>
                <td>{{ $year->modality ?: '-' }}</td>
                <td>{{ $year->final_result ?: '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if($history->notes)
    <p class="notes"><span class="label">Observações:</span> {{ $history->notes }}</p>
@endif

@if($history->years->contains(fn ($year) => filled($year->notes)))
    <p class="notes">
        <span class="label">Observações por ano/série:</span>
        @foreach($history->years->filter(fn ($year) => filled($year->notes)) as $year)
            <strong>{{ $year->label }}:</strong> {{ $year->notes }}@if(! $loop->last) · @endif
        @endforeach
    </p>
@endif

<p class="notes">
    Legenda: N - nota ou conceito; CH - carga horária; RF - resultado final; AP - aproveitamento/progressão global conforme documento de origem.
</p>

<p class="center">
    {{ $history->issued_place ?: 'Jarudore / Poxoréu-MT' }},
    {{ $history->issued_date?->format('d/m/Y') ?? now('America/Sao_Paulo')->format('d/m/Y') }}.
</p>

<table class="signatures">
    <tr>
        @include('reports.partials.signature-staff', ['school' => $history->school, 'signatureType' => 'secretarial', 'signatureDate' => $issuedDocument->issued_at ?? now()])
    </tr>
</table>
</div>

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
