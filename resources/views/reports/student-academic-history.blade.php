<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 14px 18px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.12; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 8px; padding-bottom: 6px; }
        .document-title { font-size: 14px; text-transform: uppercase; margin: 5px 0 2px; }
        .student-box, .history-table, .studies-table { border-collapse: collapse; width: 100%; }
        .student-box td { border: 0; padding: 2px 4px; }
        .label { font-weight: 600; }
        .history-table { margin: 0 0 4px; table-layout: fixed; }
        .history-table th, .history-table td, .studies-table th, .studies-table td { border: .55px solid #111; padding: 2px 3px; vertical-align: middle; }
        .history-table th, .studies-table th { background: #f1ede9; font-size: 11px; text-transform: uppercase; }
        .history-table td, .studies-table td { font-size: 11px; }
        .center { text-align: center; }
        .muted { color: #666; }
        .section-title { font-size: 11px; font-weight: 600; margin: 6px 0 3px; text-transform: uppercase; page-break-after: avoid; }
        .formation-title { background: #e7dfd9; border: .55px solid #111; font-size: 11px; font-weight: 700; margin-top: 4px; padding: 3px 5px; text-transform: uppercase; page-break-after: avoid; }
        .area-group { page-break-inside: avoid; }
        .score-cell { white-space: nowrap; }
        .studies-section { page-break-inside: avoid; }
        .notes { margin-top: 7px; }
        .signatures { border-collapse: collapse; margin-top: 65px; width: 100%; }
        .signatures td { border: 0; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 280px; padding-top: 6px; }
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
        <td><span class="label">CPF:</span> {{ $formatCpf($person->cpf) }}</td>
        <td><span class="label">Data de nascimento:</span> {{ $person->birth_date?->format('d/m/Y') ?? '-' }}</td>
    </tr>
    <tr>
        <td><span class="label">Naturalidade:</span> {{ collect([$person->birth_city ?: ($person->legacy_metadata['naturalidade'] ?? null), $person->birth_state ?: ($person->legacy_metadata['naturalidade_uf'] ?? null)])->filter()->join(' / ') ?: '-' }}</td>
        <td><span class="label">INEP:</span> {{ $person->student_inep ?: '-' }}</td>
        <td><span class="label">Mãe:</span> {{ $person->mother_name ?: '-' }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="label">Pai:</span> {{ $person->father_name ?: '-' }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="label">Fundamento legal:</span> {{ $history->legal_basis ?: '-' }}</td>
    </tr>
</table>

<div class="section-title">Componentes curriculares</div>
@forelse($history->components->groupBy(fn ($component) => $component->formation ?: '-') as $formation => $formationComponents)
    <div class="formation-title">{{ $formation }}</div>
    <table class="history-table">
        <thead>
            <tr><th rowspan="2" style="width: 22%;">Área</th><th rowspan="2" style="width: 28%;">Componente curricular</th>@foreach($history->years as $year)<th colspan="2" class="center" style="width: {{ 50 / max(1, $history->years->count()) }}%;">{{ $year->label }}</th>@endforeach</tr>
            <tr>@foreach($history->years as $year)<th class="center" style="width: {{ 25 / max(1, $history->years->count()) }}%;">N</th><th class="center" style="width: {{ 25 / max(1, $history->years->count()) }}%;">CH</th>@endforeach</tr>
        </thead>
        @foreach($formationComponents->groupBy(fn ($component) => $component->knowledge_area ?: '-') as $area => $areaComponents)
        <tbody class="area-group">
            @foreach($areaComponents as $component)
            <tr>
                @if($loop->first)<td rowspan="{{ $areaComponents->count() }}">{{ $area }}</td>@endif
                <td>{{ $component->name }}</td>
                @foreach($history->years as $year)
                    @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                    <td class="center score-cell">{{ $record?->score_label ?: '-' }}</td>
                    <td class="center score-cell">{{ $record?->workload_hours !== null ? number_format((float) $record->workload_hours, 0, ',', '.').'h' : '-' }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
        @endforeach
        <tfoot>
            <tr>
                <td colspan="2"><strong>Subtotal de carga horária — {{ $formation }}</strong></td>
                @foreach($history->years as $year)
                    @php($formationWorkload = $formationComponents->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $year->id)?->workload_hours ?? 0)))
                    <td class="center">-</td>
                    <td class="center"><strong>{{ $year->transcript_mode === 'no_transcription' ? '-' : number_format($formationWorkload, 0, ',', '.').'h' }}</strong></td>
                @endforeach
            </tr>
        </tfoot>
    </table>
@empty
    <table class="history-table"><tr><td colspan="{{ 2 + (2 * $history->years->count()) }}" class="center">Histórico cadastrado sem transcrição de componentes curriculares.</td></tr></table>
@endforelse

<table class="history-table">
    <tr><td style="width: 50%;" colspan="2"><strong>Carga horária total geral</strong></td>@foreach($history->years as $year)@php($generalWorkload = $history->components->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $year->id)?->workload_hours ?? 0)))<td colspan="2" class="center" style="width: {{ 50 / max(1, $history->years->count()) }}%;"><strong>{{ $year->transcript_mode === 'no_transcription' ? '-' : number_format($generalWorkload, 0, ',', '.').'h' }}</strong></td>@endforeach</tr>
</table>

<div class="studies-section">
<div class="section-title">Estudos realizados</div>
<table class="studies-table">
    <thead>
        <tr>
            <th style="width: 13%;">Ano / Série</th>
            <th style="width: 43%;">Estabelecimento / Local / Ato</th>
            <th style="width: 13%;">Modalidade</th>
            <th style="width: 17%;">Dias / Frequência</th>
            <th style="width: 14%;">Resultado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($history->years as $year)
            <tr>
                <td>{{ $year->year ?: '-' }}<br>{{ $year->grade_phase ?: $year->label }}</td>
                <td><strong>{{ $year->school_name ?: '-' }}</strong><br>{{ collect([$year->city, $year->state, $year->country])->filter()->join(' / ') }}@if($year->school_authorization)<br><span class="muted">{{ $year->school_authorization }}</span>@endif @if($year->source_document)<br><span class="muted">{{ $year->source_document }}</span>@endif</td>
                <td>{{ $year->modality ?: '-' }}</td>
                <td>
                    {{ collect([
                        $year->school_days ? $year->school_days.' dias' : null,
                        $year->attendance_label ?: null,
                        $year->minimum_attendance_percentage ? 'mín. '.number_format((float) $year->minimum_attendance_percentage, 0, ',', '.').'%' : null,
                    ])->filter()->join(' · ') ?: '-' }}
                </td>
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
        <td><span class="signature-line">Direção escolar</span></td>
        <td><span class="signature-line">Secretaria escolar</span></td>
    </tr>
</table>
</div>

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
