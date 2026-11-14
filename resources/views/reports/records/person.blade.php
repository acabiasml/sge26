<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 150px 24px 128px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; line-height: 1.32; }
        @include('reports.partials.letterhead-styles')
        h2 { color: #44693D; font-size: 13px; margin: 16px 0 7px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f6f0ea; padding: 6px 7px; }
        td { padding: 6px 7px; border-bottom: .6px solid #eee2dc; }
        .label { width: 30%; }
        h2, h3 { page-break-after: avoid; }
        h3 { font-size: 11px; margin: 12px 0 5px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        td { vertical-align: top; }
        .trajectory-note { color: #655951; font-size: 11px; margin-top: 3px; }
        .trajectory-table { table-layout: fixed; }
        .trajectory-entry { page-break-inside: avoid; }
        .trajectory-kind { color: #44693D; font-size: 11px; font-weight: 600; margin-bottom: 4px; }
        .document-footer { bottom: -87px !important; }
        .trajectory-overview { table-layout: fixed; margin-bottom: 14px; }
        .trajectory-overview td { background: #f6f0ea; border: 1px solid #e6d9cf; vertical-align: top; }
        .trajectory-overview ul { margin: 5px 0 0; padding-left: 13px; }
        .person-reference { font-size: 13px; margin: 0 0 8px; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Ficha da pessoa',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <p class="person-reference"><strong>{{ $person->full_name }}</strong></p>
    <h2>Resumo da trajetória</h2>
    <table class="trajectory-overview"><tr>
        @foreach ($trajectory['groups'] as $group)
            <td>
                <strong>{{ $group['label'] }}</strong>
                <div>{{ trans_choice(':count registro|:count registros', $group['entries']->count(), ['count' => $group['entries']->count()]) }}</div>
                @if ($group['entries']->isNotEmpty())
                    <ul>
                        @foreach ($group['entries']->take(3) as $entry)
                            <li>{{ $entry['title'] }}@if ($entry['year']) · {{ $entry['year'] }}@endif
                                @if ($entry['subtitle'] && $entry['subtitle'] !== $entry['title'])<div>{{ $entry['subtitle'] }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div>Nenhum registro cadastrado.</div>
                @endif
            </td>
        @endforeach
    </tr></table>
    <p class="trajectory-note">Os períodos, as datas e a duração de cada vínculo estão detalhados na seção Trajetória e movimentações.</p>

    <h2>Dados pessoais</h2>
    <table>
        <tr><th class="label">Nome completo</th><td>{{ $person->full_name }}</td></tr>
        <tr><th class="label">Nome social</th><td>{{ $person->social_name ?: '-' }}</td></tr>
        <tr><th class="label">CPF</th><td>{{ $person->cpf ?: '-' }}</td></tr>
        <tr><th class="label">Data de nascimento</th><td>{{ $person->birth_date?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th class="label">Nome da mãe</th><td>{{ $person->mother_name ?: '-' }}</td></tr>
        <tr><th class="label">Nome do pai</th><td>{{ $person->father_name ?: '-' }}</td></tr>
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
        <tr><th class="label">Situação</th><td>{{ $person->hasActiveRoleForDate() ? 'Ativa' : 'Inativa' }}</td></tr>
    </table>

    <h2>Trajetória e movimentações</h2>
    <p class="trajectory-note">Na docência, os intervalos das turmas seguem o calendário letivo. As substituições usam as datas cadastradas; o tempo do vínculo profissional consta em Vínculos profissionais.</p>
    @foreach ($trajectory['groups'] as $key => $group)
        @forelse ($group['entries'] as $entry)
            <div class="trajectory-entry">
                <table class="trajectory-table"><tbody>
                    <tr>
                        <td style="width: 66%">
                            <div class="trajectory-kind">{{ $group['label'] }}</div>
                            <strong>{{ $entry['title'] }}</strong> · {{ $entry['status'] }}
                            @if ($entry['subtitle'] && $entry['subtitle'] !== $entry['title'])<div>{{ $entry['subtitle'] }}</div>@endif
                            <div class="trajectory-note">{{ $entry['school'] }}@if ($entry['year']) · {{ $entry['year'] }}@endif</div>
                            @foreach ($entry['details'] as $detail)<div class="trajectory-note">{{ $detail }}</div>@endforeach
                        </td>
                        <td><div class="trajectory-kind">Período e duração</div>{{ $entry['period'] }}<div class="trajectory-note">{{ $entry['duration'] }}</div></td>
                    </tr>
                </tbody></table>
            </div>
        @empty
            <p><strong>{{ $group['label'] }}:</strong> nenhum registro cadastrado nesta área.</p>
        @endforelse
    @endforeach

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
