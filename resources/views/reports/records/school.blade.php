<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 20px 24px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; line-height: 1.32; }
        @include('reports.partials.letterhead-styles')
        h2 { color: #44693D; font-size: 13px; margin: 16px 0 7px; }
        table { width: 100%; border-collapse: collapse; }
        th { width: 28%; text-align: left; background: #f6f0ea; padding: 6px 7px; }
        td { padding: 6px 7px; border-bottom: .6px solid #eee2dc; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Ficha da escola',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <h2>Dados cadastrais</h2>
    <table>
        <tr><th>Nome</th><td>{{ $school->name }}</td></tr>
        <tr><th>Razão social</th><td>{{ $school->legal_name ?: '-' }}</td></tr>
        <tr><th>CNPJ</th><td>{{ $school->cnpj ?: '-' }}</td></tr>
        <tr><th>INEP</th><td>{{ $school->inep ?: '-' }}</td></tr>
        <tr><th>Fundação</th><td>{{ $school->founded_at?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th>Telefone</th><td>{{ $school->phone ?: '-' }}</td></tr>
        <tr><th>E-mail</th><td>{{ $school->email ?: '-' }}</td></tr>
        <tr><th>Site</th><td>{{ $school->website ?: '-' }}</td></tr>
        <tr><th>Texto institucional</th><td>{{ $school->letterhead_text ?: '-' }}</td></tr>
        <tr><th>Situação</th><td>{{ $school->active ? 'Ativa' : 'Inativa' }}</td></tr>
    </table>

    <h2>Endereço</h2>
    <table>
        <tr><th>Logradouro</th><td>{{ $school->address ?: '-' }}</td></tr>
        <tr><th>Número</th><td>{{ $school->number ?: '-' }}</td></tr>
        <tr><th>Bairro</th><td>{{ $school->district ?: '-' }}</td></tr>
        <tr><th>Cidade/UF</th><td>{{ $school->city ?: '-' }}{{ $school->state ? '/'.$school->state : '' }}</td></tr>
        <tr><th>CEP</th><td>{{ $school->postal_code ?: '-' }}</td></tr>
    </table>

    <h2>Vínculos ativos</h2>
    <table>
        <thead>
            <tr>
                <th>Pessoa</th>
                <th>Papel</th>
                <th>Início</th>
                <th>Fim</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($school->roles->filter(fn ($role) => $role->isActiveForDate()) as $role)
                <tr>
                    <td>{{ $role->person?->full_name }}</td>
                    <td>{{ $role->label() }}</td>
                    <td>{{ $role->started_at?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $role->ended_at?->format('d/m/Y') ?? 'Indeterminado' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhum vínculo ativo cadastrado.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
