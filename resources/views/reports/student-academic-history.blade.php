<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 18px 22px 34px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.2; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 8px; padding-bottom: 6px; }
        .document-title { font-size: 14px; text-transform: uppercase; margin: 5px 0 2px; }
        .student-box, .history-table, .studies-table { border-collapse: collapse; width: 100%; }
        .student-box td { border: 0; padding: 2px 4px; }
        .label { font-weight: 600; }
        .history-table { margin-top: 8px; }
        .history-table th, .history-table td, .studies-table th, .studies-table td { border: .55px solid #111; padding: 3px 4px; vertical-align: middle; }
        .history-table th, .studies-table th { background: #f1ede9; font-size: 11px; text-transform: uppercase; }
        .history-table td, .studies-table td { font-size: 11px; }
        .center { text-align: center; }
        .muted { color: #666; }
        .section-title { font-size: 11px; font-weight: 600; margin: 10px 0 4px; text-transform: uppercase; }
        .notes { margin-top: 7px; }
        .signatures { border-collapse: collapse; margin-top: 42px; width: 100%; }
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
<table class="history-table">
    <thead>
        <tr>
            <th style="width: 13%;">Formação</th>
            <th style="width: 16%;">Área</th>
            <th>Componente curricular</th>
            @foreach($history->years as $year)
                <th class="center" style="width: {{ max(8, 40 / max(1, $history->years->count())) }}%;">{{ $year->label }}<br><span class="muted">N/CH/F</span></th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($history->components->groupBy(fn ($component) => $component->formation ?: '-') as $formation => $formationComponents)
            @php($formationFirst = true)
            @foreach($formationComponents->groupBy(fn ($component) => $component->knowledge_area ?: '-') as $area => $areaComponents)
                @php($areaFirst = true)
                @foreach($areaComponents as $component)
                <tr>
                    @if($formationFirst)<td rowspan="{{ $formationComponents->count() }}"><strong>{{ $formation }}</strong></td>@php($formationFirst = false)@endif
                    @if($areaFirst)<td rowspan="{{ $areaComponents->count() }}">{{ $area }}</td>@php($areaFirst = false)@endif
                    <td>{{ $component->name }}</td>
                    @foreach($history->years as $year)
                        @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                        <td class="center">@if($record){{ $record->score_label ?: '-' }}<br><span class="muted">CH {{ $record->workload_hours !== null ? number_format((float) $record->workload_hours, 2, ',', '.') : '-' }}</span>@if($record->frequency_label)<br><span class="muted">F {{ $record->frequency_label }}</span>@endif @if($record->absences !== null)<br><span class="muted">Faltas {{ $record->absences }}</span>@endif @else - @endif</td>
                    @endforeach
                </tr>
                @endforeach
            @endforeach
        @empty
            <tr>
                <td colspan="{{ 3 + $history->years->count() }}" class="center">Histórico cadastrado sem transcrição de componentes curriculares.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="3"><strong>Carga horária total</strong></td>
            @foreach($history->years as $year)
                <td class="center"><strong>{{ $year->transcript_mode === 'no_transcription' ? '-' : ($year->workload_hours !== null ? number_format((float) $year->workload_hours, 2, ',', '.') : '-') }}</strong></td>
            @endforeach
        </tr>
    </tbody>
</table>

<div class="section-title">Estudos realizados</div>
<table class="studies-table">
    <thead>
        <tr>
            <th>Etapa</th>
            <th>Ano</th>
            <th>Modalidade</th>
            <th>Série/Ano/Fase</th>
            <th>Estabelecimento de ensino</th>
            <th>Ato autorizativo</th>
            <th>Município</th>
            <th>UF</th>
            <th>País</th>
            <th>Tipo</th>
            <th>Dias/Frequência</th>
            <th>RF</th>
        </tr>
    </thead>
    <tbody>
        @foreach($history->years as $year)
            <tr>
                <td>{{ $year->stage ?: $history->stage ?: '-' }}</td>
                <td>{{ $year->year ?: '-' }}</td>
                <td>{{ $year->modality ?: '-' }}</td>
                <td>{{ $year->grade_phase ?: $year->label }}</td>
                <td>{{ $year->school_name ?: '-' }}</td>
                <td>{{ $year->school_authorization ?: '-' }}@if($year->source_document)<br><span class="muted">{{ $year->source_document }}</span>@endif</td>
                <td>{{ $year->city ?: '-' }}</td>
                <td>{{ $year->state ?: '-' }}</td>
                <td>{{ $year->country ?: 'Brasil' }}</td>
                <td>{{ $transcriptModeLabels[$year->transcript_mode] ?? 'Detalhada' }}</td>
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
    Legenda: N - nota ou conceito; CH - carga horária; F - frequência; RF - resultado final; AP - aproveitamento/progressão global conforme documento de origem.
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

<div class="document-footer">
    Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}.
    Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }} por {{ $issuedDocument->issuedBy?->person?->full_name ?? 'usuário identificado' }}.
</div>
</body>
</html>
