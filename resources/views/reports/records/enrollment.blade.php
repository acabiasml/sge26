<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 150px 24px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; line-height: 1.32; }
        @include('reports.partials.letterhead-styles')
        h2 { color: #44693D; font-size: 13px; margin: 16px 0 7px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f6f0ea; padding: 6px 7px; width: 32%; }
        td { padding: 6px 7px; border-bottom: .6px solid #eee2dc; }
        .signature { margin-top: 42px; width: 100%; }
        .signature td { border: 0; text-align: center; padding-top: 48px; }
        .line { border-top: 1px solid #6B3D2E; display: inline-block; min-width: 230px; padding-top: 5px; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Ficha de matrícula',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
    ])

    <h2>Dados da matrícula</h2>
    <table>
        <tr><th>Estudante</th><td>{{ $enrollment->student?->full_name }}</td></tr>
        <tr><th>CPF</th><td>{{ $enrollment->student?->cpf ?: '-' }}</td></tr>
        <tr><th>Data de nascimento</th><td>{{ $enrollment->student?->birth_date?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th>Nome da mãe</th><td>{{ $enrollment->student?->mother_name ?: '-' }}</td></tr>
        <tr><th>Nome do pai</th><td>{{ $enrollment->student?->father_name ?: '-' }}</td></tr>
        <tr><th>Escola</th><td>{{ $academicYear->school?->name }}</td></tr>
        <tr><th>Ano letivo</th><td>{{ $academicYear->referenceYearsLabel() }}</td></tr>
        <tr><th>Turma e etapa</th><td>{{ \App\Support\AcademicContextLabel::classWithStages($class->name, $class->courses) }}</td></tr>
        <tr><th>Matriz(es)</th><td>{{ $enrollment->courses->pluck('name')->join(' + ') ?: '-' }}</td></tr>
        <tr><th>Data de matrícula</th><td>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th>Situação</th><td>{{ $enrollment->statusLabel() }} · {{ $enrollment->typeLabel() }}</td></tr>
        @if ($enrollment->transferred_at)
            <tr><th>Data de saída</th><td>{{ $enrollment->transferred_at->format('d/m/Y') }}</td></tr>
        @endif
        @if ($enrollment->cancelled_at)
            <tr><th>Data de cancelamento</th><td>{{ $enrollment->cancelled_at->format('d/m/Y') }}</td></tr>
        @endif
        @if ($enrollment->reclassifiedFrom)
            <tr><th>Reclassificação de origem</th><td>{{ \App\Support\AcademicContextLabel::classWithStages($enrollment->reclassifiedFrom->schoolClass?->name, $enrollment->reclassifiedFrom->schoolClass?->courses ?? collect()) }} em {{ $enrollment->reclassified_at?->format('d/m/Y') ?? '-' }}</td></tr>
        @endif
        <tr><th>Observações</th><td>{{ $enrollment->notes ?: '-' }}</td></tr>
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
            <td><span class="line">Secretaria escolar</span></td>
        </tr>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
