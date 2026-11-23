@php
    $identityStudent = $student ?? $person;
    $bulletinIdentity = ($mode ?? 'full') === 'bulletin';
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
    $identitySpans = ['CPF' => 2, 'Naturalidade' => 4, 'Ano letivo' => 2, 'Turma e etapa' => 4];
    $identityFields = $bulletinIdentity
        ? [
            ['Nascimento', $identityStudent->birth_date?->format('d/m/Y') ?? '-'],
            ['Código da pasta', $identityStudent->legacy_code ?: '-'],
        ]
        : [
            ['Nascimento', $identityStudent->birth_date?->format('d/m/Y') ?? '-'],
            ['CPF', $identityCpf],
            ['Naturalidade', $identityBirthPlace ?: '-'],
            ['Nacionalidade', $identityNationality ?: '-'],
            ['Telefone', $identityStudent->phone ?: '-'],
            ['Código da pasta', $identityStudent->legacy_code ?: '-'],
        ];
    if ($bulletinIdentity && isset($academicYear, $schoolClass, $courses)) {
        $identityFields[] = ['Ano letivo', $academicYear->referenceYearsLabel()];
        $identityFields[] = ['Turma e etapa', \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $courses)];
    }
    if (! $bulletinIdentity) {
        if ($identityStudent->nis || $identityStudent->receives_federal_aid) {
            $identityFields[] = ['NIS', $identityStudent->nis ?: '-'];
            $identityFields[] = ['Auxílio federal', $identityStudent->receives_federal_aid ? 'Sim' : 'Não'];
        }
    }
@endphp

@if($showTitle ?? true)
    <div class="section-title">IDENTIFICAÇÃO DO ESTUDANTE</div>
@endif
<table class="meta-table student-identification">
    <colgroup>
        @for($identityColumn = 0; $identityColumn < 12; $identityColumn++)
            <col style="width: {{ 100 / 12 }}%">
        @endfor
    </colgroup>
    <tr>
        <td colspan="9"><strong>Estudante:</strong> {{ $identityStudent->full_name }}</td>
        <td colspan="3" class="identity-inep"><strong>INEP:</strong> {{ $identityStudent->student_inep ?: '-' }}</td>
    </tr>
    @if($identityStudent->social_name)
        <tr><td colspan="12"><strong>Nome social:</strong> {{ $identityStudent->social_name }}</td></tr>
    @endif
    @foreach(array_chunk($identityFields, 4) as $identityRow)
        <tr>
            @foreach($identityRow as [$identityLabel, $identityValue])
                <td colspan="{{ count($identityRow) === 4 ? ($identitySpans[$identityLabel] ?? 3) : 12 / count($identityRow) }}"><strong>{{ $identityLabel }}:</strong> {{ $identityValue }}</td>
            @endforeach
        </tr>
    @endforeach
    @if($showCredentialIdentity ?? false)
        <tr><td colspan="6"><strong>CIN / documento oficial:</strong> {{ $identityStudent->schoolPhotoIdentification() ?: '—' }}</td>
        <td colspan="6"><strong>Gênero:</strong> {{ $identityStudent->school_gender ?: '—' }}@if($identityStudent->school_voter_registration) · <strong>Título de eleitor:</strong> {{ $identityStudent->school_voter_registration }}@endif</td></tr>
    @endif
    @if(! $bulletinIdentity && ($identityStudent->personal_email || $identityStudent->institutional_email))
        <tr>
            <td colspan="6"><strong>E-mail:</strong> {{ $identityStudent->personal_email ?: '-' }}</td>
            <td colspan="6"><strong>E-mail institucional:</strong> {{ $identityStudent->institutional_email ?: '-' }}</td>
        </tr>
    @endif
    @if(! $bulletinIdentity)
        <tr>
            <td colspan="6"><strong>Mãe:</strong> {{ $identityStudent->mother_name ?: '-' }}</td>
            <td colspan="6"><strong>Pai:</strong> {{ $identityStudent->father_name ?: '-' }}</td>
        </tr>
    @endif
    @if(($mode ?? 'full') === 'full' && isset($academicYear, $schoolClass, $courses))
        <tr>
            <td colspan="8"><strong>Turma e etapa:</strong> {{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $courses) }}</td>
            <td colspan="4"><strong>Ano letivo:</strong> {{ $academicYear->referenceYearsLabel() }}</td>
        </tr>
    @endif
    @if(! $bulletinIdentity && $identityAddress)
        <tr><td colspan="12"><strong>Endereço:</strong> {{ $identityAddress }}</td></tr>
    @endif
</table>
