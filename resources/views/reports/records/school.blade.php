<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 12px; }
        header { border-bottom: 2px solid #7a3f27; margin-bottom: 18px; padding-bottom: 10px; }
        h1 { color: #7a3f27; font-size: 22px; margin: 0 0 4px; }
        h2 { color: #5f7f3d; font-size: 15px; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { width: 28%; text-align: left; background: #f6f0ea; padding: 7px; }
        td { padding: 7px; border-bottom: 1px solid #eee2dc; }
        .meta { color: #666; font-size: 10px; line-height: 1.5; }
        footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #eee2dc; padding-top: 8px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <header>
        <h1>Ficha da escola</h1>
        <div class="meta">
            Beabá - Sistema de Gestão Escolar<br>
            Emitido em {{ $issuedDocument->issued_at?->format('d/m/Y H:i:s') }}<br>
            Código de verificação: <strong>{{ $issuedDocument->verification_code }}</strong><br>
            Verificação: {{ $verificationUrl }}
        </div>
    </header>

    <h2>Dados cadastrais</h2>
    <table>
        <tr><th>Nome</th><td>{{ $school->name }}</td></tr>
        <tr><th>Razão social</th><td>{{ $school->legal_name ?: '-' }}</td></tr>
        <tr><th>CNPJ</th><td>{{ $school->cnpj ?: '-' }}</td></tr>
        <tr><th>INEP</th><td>{{ $school->inep ?: '-' }}</td></tr>
        <tr><th>Telefone</th><td>{{ $school->phone ?: '-' }}</td></tr>
        <tr><th>E-mail</th><td>{{ $school->email ?: '-' }}</td></tr>
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

    <footer>
        Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}.
    </footer>
</body>
</html>
