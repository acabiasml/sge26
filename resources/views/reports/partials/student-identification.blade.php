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
    $identityFields = $bulletinIdentity
        ? [
            ['Nascimento', $identityStudent->birth_date?->format('d/m/Y') ?? '-'],
            ['Código da pasta', $identityStudent->legacy_code ?: '-'],
            ['INEP', $identityStudent->student_inep ?: '-'],
        ]
        : [
            ['Nascimento', $identityStudent->birth_date?->format('d/m/Y') ?? '-'],
            ['CPF', $identityCpf],
            ['INEP', $identityStudent->student_inep ?: '-'],
            ['Naturalidade', $identityBirthPlace ?: '-'],
            ['Nacionalidade', $identityNationality ?: '-'],
            ['Telefone', $identityStudent->phone ?: '-'],
            ['Código da pasta', $identityStudent->legacy_code ?: '-'],
        ];
    if (! $bulletinIdentity) {
        if ($identityStudent->nis || $identityStudent->receives_federal_aid) {
            $identityFields[] = ['NIS', $identityStudent->nis ?: '-'];
            $identityFields[] = ['Auxílio federal', $identityStudent->receives_federal_aid ? 'Sim' : 'Não'];
        }
        if ($identityStudent->personal_email || $identityStudent->institutional_email) {
            $identityFields[] = ['E-mail', $identityStudent->personal_email ?: '-'];
            $identityFields[] = ['E-mail institucional', $identityStudent->institutional_email ?: '-'];
        }
    }
@endphp

@if($showTitle ?? true)
    <div class="section-title">IDENTIFICAÇÃO DO ESTUDANTE</div>
@endif
<table class="meta-table student-identification">
    <colgroup>
        @for($identityColumn = 0; $identityColumn < 6; $identityColumn++)
            <col style="width: {{ 100 / 6 }}%">
        @endfor
    </colgroup>
    <tr>
        <td colspan="6"><strong>Estudante:</strong> {{ $identityStudent->full_name }}</td>
    </tr>
    @if($identityStudent->social_name)
        <tr><td colspan="6"><strong>Nome social:</strong> {{ $identityStudent->social_name }}</td></tr>
    @endif
    @foreach(array_chunk($identityFields, 3) as $identityRow)
        <tr>
            @foreach($identityRow as [$identityLabel, $identityValue])
                <td colspan="{{ 6 / count($identityRow) }}"><strong>{{ $identityLabel }}:</strong> {{ $identityValue }}</td>
            @endforeach
        </tr>
    @endforeach
    @if(! $bulletinIdentity)
        <tr>
            <td colspan="3"><strong>Mãe:</strong> {{ $identityStudent->mother_name ?: '-' }}</td>
            <td colspan="3"><strong>Pai:</strong> {{ $identityStudent->father_name ?: '-' }}</td>
        </tr>
    @endif
    @if(($mode ?? 'full') !== 'individual' && isset($academicYear, $schoolClass, $courses))
        <tr>
            <td colspan="4"><strong>Turma e etapa:</strong> {{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $courses) }}</td>
            <td colspan="2"><strong>Ano letivo:</strong> {{ $academicYear->referenceYearsLabel() }}</td>
        </tr>
    @endif
    @if(! $bulletinIdentity && $identityAddress)
        <tr><td colspan="6"><strong>Endereço:</strong> {{ $identityAddress }}</td></tr>
    @endif
</table>
