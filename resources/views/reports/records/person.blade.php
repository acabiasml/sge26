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
        th { text-align: left; background: #f6f0ea; padding: 7px; }
        td { padding: 7px; border-bottom: 1px solid #eee2dc; }
        .label { width: 30%; }
        .meta { color: #666; font-size: 10px; line-height: 1.5; }
        footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #eee2dc; padding-top: 8px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <header>
        <h1>Ficha da pessoa</h1>
        <div class="meta">
            Beabá - Sistema de Gestão Escolar<br>
            Emitido em {{ $issuedDocument->issued_at?->format('d/m/Y H:i:s') }}<br>
            Código de verificação: <strong>{{ $issuedDocument->verification_code }}</strong><br>
            Verificação: {{ $verificationUrl }}
        </div>
    </header>

    <h2>Dados pessoais</h2>
    <table>
        <tr><th class="label">Nome completo</th><td>{{ $person->full_name }}</td></tr>
        <tr><th class="label">Nome social</th><td>{{ $person->social_name ?: '-' }}</td></tr>
        <tr><th class="label">CPF</th><td>{{ $person->cpf ?: '-' }}</td></tr>
        <tr><th class="label">Data de nascimento</th><td>{{ $person->birth_date?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th class="label">E-mail institucional</th><td>{{ $person->institutional_email ?: '-' }}</td></tr>
        <tr><th class="label">E-mail pessoal</th><td>{{ $person->personal_email ?: '-' }}</td></tr>
        <tr><th class="label">Telefone</th><td>{{ $person->phone ?: '-' }}</td></tr>
        <tr>
            <th class="label">Endereço</th>
            <td>
                @if ($person->address || $person->city || $person->state)
                    {{ $person->address }}
                    @if ($person->number)
                        , {{ $person->number }}
                    @endif
                    @if ($person->district)
                        - {{ $person->district }}
                    @endif
                    <br>
                    {{ collect([$person->city, $person->state])->filter()->join(' - ') }}
                    @if ($person->postal_code)
                        | CEP {{ $person->postal_code }}
                    @endif
                    @if ($person->address_complement)
                        <br>{{ $person->address_complement }}
                    @endif
                @else
                    -
                @endif
            </td>
        </tr>
        <tr><th class="label">Situação</th><td>{{ $person->active ? 'Ativa' : 'Inativa' }}</td></tr>
    </table>

    <h2>Vínculos e papéis</h2>
    <table>
        <thead>
            <tr>
                <th>Papel</th>
                <th>Escola</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($person->schoolRoles as $role)
                <tr>
                    <td>{{ $role->label() }}</td>
                    <td>{{ $role->school?->name ?? 'Global' }}</td>
                    <td>{{ $role->started_at?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $role->ended_at?->format('d/m/Y') ?? 'Indeterminado' }}</td>
                    <td>{{ $role->isActiveForDate() ? 'Ativo' : 'Inativo' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Nenhum vínculo cadastrado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Responsáveis e contatos</h2>
    <table>
        <thead>
            <tr>
                <th>Pessoa</th>
                <th>Relação</th>
                <th>Contato</th>
                <th>Observações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($person->relationships as $relationship)
                <tr>
                    <td>{{ $relationship->relatedPerson?->full_name }}</td>
                    <td>
                        {{ $relationship->label() }}
                        @if ($relationship->legal_guardian)
                            <br>Responsável legal
                        @endif
                        @if ($relationship->emergency_contact)
                            <br>Contato de emergência
                        @endif
                    </td>
                    <td>{{ $relationship->relatedPerson?->phone ?: '-' }}</td>
                    <td>{{ $relationship->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhuma relação cadastrada.</td></tr>
            @endforelse
        </tbody>
    </table>

    <footer>
        Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}.
    </footer>
</body>
</html>
