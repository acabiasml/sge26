<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 150px 24px 96px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; line-height: 1.32; }
        @include('reports.partials.letterhead-styles')
        h2 { color: #44693D; font-size: 13px; margin: 16px 0 7px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f6f0ea; padding: 6px 7px; width: 32%; }
        td { padding: 6px 7px; border-bottom: .6px solid #eee2dc; }
        .meta-table td { border: .45px solid #d8ccc4; padding: 1.5px 3px; vertical-align: top; line-height: 1.06; }
        .signature { margin-top: 42px; width: 100%; }
        .signature td { border: 0; text-align: center; padding-top: 48px; }
        .line { border-top: 1px solid #6B3D2E; display: inline-block; min-width: 230px; padding-top: 5px; }
        .signature-name { display: block; font-weight: 600; }
        .signature-role { display: block; margin-top: 2px; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Ficha de matrícula',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
    ])

    <h2>IDENTIFICAÇÃO DO ESTUDANTE</h2>
    @include('reports.partials.student-identification', ['student' => $enrollment->student, 'mode' => 'individual', 'showTitle' => false])

    @php
        $enrollmentFields = [
            ['Ano letivo', $academicYear->referenceYearsLabel()],
            ['Turma e etapa', \App\Support\AcademicContextLabel::classWithStages($class->name, $class->courses)],
            ['Matriz(es)', $enrollment->courses->pluck('name')->join(' + ') ?: '-'],
            ['Data de matrícula', $enrollment->enrolled_at?->format('d/m/Y') ?? '-'],
            ['Situação', $enrollment->statusLabel().' · '.$enrollment->typeLabel()],
        ];
        if ($enrollment->transferred_at) $enrollmentFields[] = ['Data de saída', $enrollment->transferred_at->format('d/m/Y')];
        if ($enrollment->cancelled_at) $enrollmentFields[] = ['Data de cancelamento', $enrollment->cancelled_at->format('d/m/Y')];
    @endphp
    <h2>DADOS DA MATRÍCULA</h2>
    <table class="meta-table enrollment-identification">
        <colgroup>
            @for($column = 0; $column < 6; $column++)<col style="width: {{ 100 / 6 }}%">@endfor
        </colgroup>
        @foreach(array_chunk($enrollmentFields, 3) as $fields)
            <tr>
                @foreach($fields as [$label, $value])
                    <td colspan="{{ 6 / count($fields) }}"><strong>{{ $label }}:</strong> {{ $value }}</td>
                @endforeach
            </tr>
        @endforeach
        @if ($enrollment->reclassifiedFrom)
            <tr><td colspan="6"><strong>Reclassificação de origem:</strong> {{ \App\Support\AcademicContextLabel::classWithStages($enrollment->reclassifiedFrom->schoolClass?->name, $enrollment->reclassifiedFrom->schoolClass?->courses ?? collect()) }} em {{ $enrollment->reclassified_at?->format('d/m/Y') ?? '-' }}</td></tr>
        @endif
        <tr><td colspan="6"><strong>Observações:</strong> {{ $enrollment->notes ?: '-' }}</td></tr>
    </table>

    <h2>Responsáveis e contatos</h2>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Relação</th>
                <th>Contato</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($enrollment->student?->contacts ?? [] as $contact)
                <tr>
                    <td>{{ $contact->name }}</td>
                    <td>{{ $contact->label() }}</td>
                    <td>{{ collect([$contact->phone, $contact->secondary_phone, $contact->email])->filter()->join(' | ') ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Nenhum contato cadastrado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td><span class="line">Responsável pelo estudante</span></td>
            @php($secretary = \App\Support\SchoolSignatureStaff::forSchool($academicYear->school, $issuedDocument->issued_at ?? now())['secretário'])
            <td><span class="line">@if($secretary)<span class="signature-name">{{ $secretary->full_name }}</span>@endif<span class="signature-role">Secretaria escolar</span></span></td>
        </tr>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
