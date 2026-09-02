@php
    $identityStudent = $student ?? $person;
    $identityBirthPlace = collect([
        $identityStudent->birth_city ?: ($identityStudent->legacy_metadata['naturalidade'] ?? null),
        mb_strtoupper((string) ($identityStudent->birth_state ?: ($identityStudent->legacy_metadata['naturalidade_uf'] ?? null)), 'UTF-8') ?: null,
    ])->filter()->join(', ');
    $identityNationality = $identityStudent->nationality ?: ($identityStudent->legacy_metadata['nacionalidade'] ?? null);
    $identityAddress = collect([
        $identityStudent->address,
        $identityStudent->number,
        $identityStudent->address_complement,
        $identityStudent->district,
        collect([
            $identityStudent->city,
            $identityStudent->state ? mb_strtoupper($identityStudent->state, 'UTF-8') : null,
        ])->filter()->join(' - '),
        $identityStudent->postal_code ? 'CEP '.$identityStudent->postal_code : null,
    ])->filter()->join(', ');
    $identityCpfDigits = preg_replace('/\D/', '', (string) $identityStudent->cpf);
    $identityCpf = strlen($identityCpfDigits) === 11
        ? substr($identityCpfDigits, 0, 3).'.'.substr($identityCpfDigits, 3, 3).'.'.substr($identityCpfDigits, 6, 3).'-'.substr($identityCpfDigits, 9, 2)
        : ($identityStudent->cpf ?: '-');
@endphp

@if($showTitle ?? true)
    <div class="section-title">Identificação do estudante</div>
@endif
<table class="meta-table student-identification">
    <tr>
        <td class="label">Estudante</td>
        <td colspan="3">{{ $identityStudent->full_name }}</td>
    </tr>
    @if(($mode ?? 'full') === 'bulletin')
        @if($identityStudent->social_name)
            <tr>
                <td class="label">Nome social</td>
                <td colspan="3">{{ $identityStudent->social_name }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Código da pasta</td>
            <td>{{ $identityStudent->legacy_code ?: '-' }}</td>
            <td class="label">INEP</td>
            <td>{{ $identityStudent->student_inep ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nascimento</td>
            <td colspan="3">{{ $identityStudent->birth_date?->format('d/m/Y') ?? '-' }}</td>
        </tr>
    @else
    @if($identityStudent->social_name || $identityStudent->legacy_code)
        <tr>
            <td class="label">Nome social</td>
            <td>{{ $identityStudent->social_name ?: '-' }}</td>
            <td class="label">Código da pasta</td>
            <td>{{ $identityStudent->legacy_code ?: '-' }}</td>
        </tr>
    @endif
    <tr>
        <td class="label">CPF</td>
        <td>{{ $identityCpf }}</td>
        <td class="label">INEP</td>
        <td>{{ $identityStudent->student_inep ?: '-' }}</td>
    </tr>
    @if($identityStudent->nis || $identityStudent->receives_federal_aid)
        <tr>
            <td class="label">NIS</td>
            <td>{{ $identityStudent->nis ?: '-' }}</td>
            <td class="label">Auxílio federal</td>
            <td>{{ $identityStudent->receives_federal_aid ? 'Sim' : 'Não' }}</td>
        </tr>
    @endif
    <tr>
        <td class="label">Nascimento</td>
        <td>{{ $identityStudent->birth_date?->format('d/m/Y') ?? '-' }}</td>
        <td class="label">Naturalidade</td>
        <td>{{ $identityBirthPlace ?: '-' }}</td>
    </tr>
    <tr>
        <td class="label">Nacionalidade</td>
        <td>{{ $identityNationality ?: '-' }}</td>
        <td class="label">Telefone</td>
        <td>{{ $identityStudent->phone ?: '-' }}</td>
    </tr>
    @if($identityStudent->personal_email || $identityStudent->institutional_email)
        <tr>
            <td class="label">E-mail</td>
            <td>{{ $identityStudent->personal_email ?: '-' }}</td>
            <td class="label">E-mail institucional</td>
            <td>{{ $identityStudent->institutional_email ?: '-' }}</td>
        </tr>
    @endif
    <tr>
        <td class="label">Mãe</td>
        <td>{{ $identityStudent->mother_name ?: '-' }}</td>
        <td class="label">Pai</td>
        <td>{{ $identityStudent->father_name ?: '-' }}</td>
    </tr>
    @if($identityAddress)
        <tr>
            <td class="label">Endereço</td>
            <td colspan="3">{{ $identityAddress }}</td>
        </tr>
    @endif
    @endif
</table>
