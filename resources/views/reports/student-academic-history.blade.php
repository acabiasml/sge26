<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 140px 18px 126px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.06; }
        @include('reports.partials.letterhead-styles')
        /* Reserve the full footer height while preserving its position on the page. */
        .document-footer { bottom: -85px !important; }
        .letterhead-repeat { top: -126px; }
        .letterhead-line, .document-title, .document-meta, .document-footer { line-height: 1.06; }
        .letterhead { padding-bottom: 6px; }
        .document-title { font-size: 14px; text-transform: uppercase; margin: 5px 0 0; }
        .meta-table, .history-table, .studies-table { border-collapse: collapse; width: 100%; }
        .meta-table { margin-bottom: 4px; }
        .meta-table td { border: .45px solid #d8ccc4; padding: 1px 3px; vertical-align: top; }
        .meta-table .label { background: #f3eee9; font-weight: 600; width: 18%; }
        .label { font-weight: 600; }
        .history-table { margin: 0 0 4px; table-layout: fixed; }
        .history-table th, .history-table td, .studies-table th, .studies-table td { border: .55px solid #111; padding: 1px 2px; vertical-align: middle; }
        .history-table th, .studies-table > thead > tr > th { background: #f1ede9; font-size: 11px; text-transform: uppercase; }
        .history-table td, .studies-table td { font-size: 11px; }
        .basic-history-matrix, .technical-history-matrix { page-break-inside: auto; }
        .basic-history-matrix th, .basic-history-matrix td { font-size: 11px; padding: 1px 2px; line-height: 1.12; }
        .basic-history-matrix th { text-transform: none; }
        .basic-history-matrix .workload-heading { white-space: nowrap; padding-inline: 0; }
        .basic-history-matrix .area-label { font-size: 11px; }
        .basic-history-matrix .score-cell { padding: 2px 0; white-space: nowrap; letter-spacing: -.2px; }
        .vertical-workload { height: 116px; position: relative; }
        .vertical-workload span { position: absolute; width: 140px; left: 50%; top: 50%; margin-left: -70px; margin-top: -6px; transform: rotate(-90deg); white-space: nowrap; text-align: center; }
        .vertical-formation { height: 100px; position: relative; }
        .vertical-formation span { position: absolute; width: 125px; left: 50%; top: 50%; margin-left: -62.5px; margin-top: -6px; transform: rotate(-90deg); white-space: nowrap; text-align: center; font-weight: 600; }
        .workload-totals { page-break-inside: avoid; margin-top: 6px; }
        .workload-totals-compact { margin-top: 0; margin-bottom: 2px; }
        .workload-totals-compact td { padding: 0 2px; }
        .vertical-heading { height: 48px; position: relative; }
        .vertical-heading span { position: absolute; width: 72px; left: 50%; top: 50%; margin-left: -36px; margin-top: -6px; transform: rotate(-90deg); white-space: nowrap; text-align: center; }
        .vertical-heading-multiline { height: 76px; }
        .vertical-heading-multiline span { white-space: normal; margin-top: -12px; line-height: 1.12; }
        .vertical-result { height: 128px; position: relative; }
        .vertical-result strong { position: absolute; width: 150px; left: 50%; top: 50%; margin-left: -75px; margin-top: -6px; transform: rotate(-90deg); white-space: nowrap; text-align: center; }
        .basic-history-matrix .global-year { background: #faf8f6; }
        .center { text-align: center; }
        .muted { color: #666; }
        .section-title { color: #6f3a29; font-size: 11px; font-weight: 600; margin: 3px 0 1px; text-transform: uppercase; page-break-after: avoid; }
        .section-title-reference { font-weight: 400; margin-left: 6px; text-transform: none; }
        .formation-title { background: #e7dfd9; border: .55px solid #111; font-size: 11px; font-weight: 700; margin-top: 3px; padding: 2px 4px; text-transform: uppercase; page-break-after: avoid; }
        .formation-title-reference { font-weight: 400; margin-left: 6px; text-transform: none; }
        .module-title { border: .55px solid #111; border-bottom: 0; background: #f5f2ef; font-weight: 600; padding: 2px 4px; page-break-after: avoid; }
        .area-group { page-break-inside: avoid; }
        .score-cell { white-space: normal; }
        .general-total-label { border-right: 0 !important; white-space: normal; }
        .general-total-label-space { border-left: 0 !important; }
        .studies-table { page-break-inside: auto; }
        .studies-table > tbody > tr > td { padding-top: 0; padding-bottom: 0; }
        .studies-table > tbody > tr { page-break-inside: avoid; }
        .studies-nowrap { white-space: normal; }
        .last-study-row { page-break-after: avoid; }
        .history-closing-row { page-break-before: avoid; }
        .studies-table > tbody > tr > .history-closing-cell { border: 0; padding: 0; }
        .notes { margin: 3px 0 0; }
        .legend-table { border-collapse: collapse; font-size: 11px; line-height: 1.06; margin-top: 3px; width: 100%; }
        .legend-table th, .legend-table td { border: .45px solid #d8ccc4; padding: 1px 5px; text-align: left; vertical-align: top; }
        .legend-table th { background: #f3eee9; font-weight: 600; width: 10%; }
        .legend-table td { background: #faf8f6; }
        .signatures { border-collapse: collapse; margin-top: 6px; page-break-inside: avoid; width: 100%; }
        .history-closing-cell > p.center { margin: 2px 0; }
        .signatures td { border: 0; padding-top: 36px; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 220px; padding-top: 6px; }
        .signature-name { display: block; font-weight: 600; }
        .signature-role { display: block; margin-top: 2px; }
    </style>
</head>
<body>
@if($isDraft ?? false)<div style="position:fixed;top:300px;left:30px;right:30px;text-align:center;transform:rotate(-25deg);font-size:27px;opacity:.15;z-index:-1">PRÉVIA TEMPORÁRIA<br>SEM VALOR OFICIAL — SEM REGISTRO</div>@endif
@php
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

@include('reports.partials.student-identification', ['student' => $person, 'showCredentialIdentity' => isset($isDraft)])

@foreach($issuedDocument->payload['authorizations'] ?? [] as $key => $act)
    @if(filled($act['act'] ?? null) && filled($act['published_on'] ?? null))
        <p class="notes"><strong>{{ __(\App\Models\School::DOCUMENT_AUTHORIZATIONS[$key]) }}</strong> — {{ $act['act'] }} · Publicação em {{ \Carbon\CarbonImmutable::parse($act['published_on'])->format('d/m/Y') }}</p>
    @endif
@endforeach

<div class="section-title">
    Componentes curriculares
    @if($history->education_stage !== 'tecnico' && filled($history->legal_basis))
        <span class="section-title-reference">Fundamento legal: {{ $history->legal_basis }}</span>
    @endif
</div>
@if($history->education_stage === 'tecnico')
    @include('reports.partials.technical-history-matrix', ['history' => $history])
@else
@include('reports.partials.basic-history-matrix', ['history' => $history])
@include('reports.partials.history-formation-workloads', ['showAnnualFormationWorkloads' => $history->education_stage === 'medio', 'compactFormationWorkloads' => $history->education_stage === 'fundamental'])
@endif

@if($history->notes)
    <p class="notes"><span class="label">Observações:</span> {{ $history->notes }}</p>
@endif

@foreach($issuedDocument->payload['school_special_processes'] ?? [] as $process)
    <p class="notes"><strong>{{ __(['classification' => 'Classificação', 'reclassification' => 'Reclassificação', 'placement' => 'Enturmação'][$process['kind']]) }}</strong>
        · {{ \Carbon\CarbonImmutable::parse($process['date'])->format('d/m/Y') }}
        · {{ __('Livro de Processos Especiais') }} {{ $process['book'] }}, {{ __('registro') }} {{ $process['number'] }}.</p>
@endforeach

@if($history->years->contains(fn ($year) => filled($year->notes)))
    <p class="notes">
        <span class="label">Observações por ano/série:</span>
        @foreach($history->years->filter(fn ($year) => filled($year->notes)) as $year)
            <strong>{{ $year->label }}:</strong> {{ $year->notes }}@if(! $loop->last) · @endif
        @endforeach
    </p>
@endif

<div class="studies-section">
<div class="section-title">Estudos realizados</div>
<table class="studies-table">
    <thead>
        <tr>
            <th style="width: 18%;">{{ $history->education_stage === 'tecnico' ? 'Período avaliativo' : 'Ano / Série' }}</th>
            <th style="width: 54%;">Estabelecimento / Local</th>
            <th style="width: 14%;">Modalidade</th>
            <th style="width: 14%;">Resultado</th>
        </tr>
    </thead>
    <tbody>
        @if($history->education_stage === 'tecnico' && ($technicalPeriodDurations ?? collect())->isNotEmpty())
        @foreach($technicalPeriodDurations as $period)
            @php($moduleResult = $period->ends_at?->copy()->endOfDay()->isPast() ? 'Concluído' : ($period->starts_at?->isFuture() ? 'A cursar' : 'Cursando'))
            <tr data-pdf-content="history" @if($loop->last) class="last-study-row" @endif>
                <td class="studies-nowrap">{{ $period->name }}</td>
                <td class="studies-nowrap"><strong>{{ $history->school?->name ?: '-' }}</strong>@if($location = collect([$history->school?->city, $history->school?->state ? mb_strtoupper($history->school->state, 'UTF-8') : null, 'Brasil'])->filter()->join(' / ')) — {{ $location }}@endif</td>
                <td>EPT</td>
                <td>{{ $moduleResult }}</td>
            </tr>
        @endforeach
        @else
        @foreach($history->years as $year)
            <tr data-pdf-content="history" @if($loop->last) class="last-study-row" @endif>
                <td class="studies-nowrap">{{ collect([$year->year ?: null, $year->grade_phase ?: $year->label])->filter()->join(' — ') ?: '-' }}</td>
                <td class="studies-nowrap"><strong>{{ $year->school_name ?: '-' }}</strong>@if($location = collect([$year->city, $year->state ? mb_strtoupper($year->state, 'UTF-8') : null, $year->country])->filter()->join(' / ')) — {{ $location }}@endif</td>
                <td>{{ $year->modality ?: '-' }}</td>
                <td>{{ $year->final_result ?: '-' }}</td>
            </tr>
        @endforeach
        @endif
        @if($history->years->isEmpty() && ($technicalPeriodDurations ?? collect())->isEmpty())
            <tr class="last-study-row" data-pdf-content="history"><td colspan="4">Nenhum estudo registrado.</td></tr>
        @endif
    <tr class="history-closing-row"><td colspan="4" class="history-closing-cell">

<table class="legend-table">
    <tr>
        <th>Legenda</th>
        <td>N (nota ou conceito) · CHC (carga horária cursada, em horas) · RF (resultado final) · AP (aproveitamento/progressão global conforme documento de origem). Totais por formação: horas previstas / cursadas.</td>
    </tr>
</table>

<p class="center">
    @if(filled($history->issued_place))
        {{ preg_replace_callback('/-([a-z]{2})$/iu', fn ($match) => '-'.mb_strtoupper($match[1], 'UTF-8'), $history->issued_place) }},
    @endif
    {{ ($issuedDocument->issued_at ?? now())->copy()->timezone('America/Cuiaba')->format('d/m/Y') }}.
</p>

<table class="signatures" data-pdf-signatures="history">
    <tr>
        @include('reports.partials.signature-staff', ['school' => $history->school, 'signatureType' => 'secretarial', 'signatureDate' => $issuedDocument->issued_at ?? now(), 'signatureStaff' => $historySignatureStaff ?? null])
    </tr>
</table>
</td></tr>
</tbody>
</table>
</div>

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
