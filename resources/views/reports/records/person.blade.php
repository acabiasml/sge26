<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 12px; }
        @include('reports.partials.letterhead-styles')
        h2 { color: #44693D; font-size: 15px; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f6f0ea; padding: 7px; }
        td { padding: 7px; border-bottom: 1px solid #eee2dc; }
        .label { width: 30%; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Ficha da pessoa',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

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
                    @if ($person->number), {{ $person->number }} @endif
                    @if ($person->district) - {{ $person->district }} @endif
                    <br>
                    {{ collect([$person->city, $person->state])->filter()->join(' - ') }}
                    @if ($person->postal_code) | CEP {{ $person->postal_code }} @endif
                    @if ($person->address_complement) <br>{{ $person->address_complement }} @endif
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
                <th>Nome</th>
                <th>Relação</th>
                <th>Contato</th>
                <th>Observações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($person->contacts as $contact)
                <tr>
                    <td>{{ $contact->name }}</td>
                    <td>
                        {{ $contact->label() }}
                        @if ($contact->legal_guardian)
                            <br>Responsável legal
                        @endif
                        @if ($contact->emergency_contact)
                            <br>Contato de emergência
                        @endif
                    </td>
                    <td>
                        {{ $contact->phone ?: '-' }}
                        @if ($contact->secondary_phone)
                            <br>{{ $contact->secondary_phone }}
                        @endif
                        @if ($contact->email)
                            <br>{{ $contact->email }}
                        @endif
                    </td>
                    <td>{{ $contact->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhum contato cadastrado.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
