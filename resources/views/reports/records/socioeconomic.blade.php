<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: {{ $contentTopMargin ?? 195 }}px 24px 128px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.12; color: #111; }
        @include('reports.partials.letterhead-styles')
        .letterhead-repeat { top: -{{ ($contentTopMargin ?? 195) - 14 }}px; }
        .document-footer { bottom: -87px !important; }
        .document-title { font-size: 13px; }
        h2 { font-size: 12px; margin: 6px 0 3px; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: .5px solid #777; padding: 1px 3px; vertical-align: top; }
        th { text-align: left; background: #f1ede9; font-weight: 600; }
        tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .subtitle { text-align: center; margin: 0 0 6px; }
        .choices { border: .5px solid #777; padding: 4px; }
        .choices span { display: inline-block; width: 48%; margin: 1px 0; }
        .declaration { border: .5px solid #777; padding: 5px; margin-top: 6px; page-break-inside: avoid; }
        .enrollment-page { page-break-before: always; }
        .enrollment-table th { text-align: center; }
        .enrollment-table td { height: 30px; }
        .signatures { margin-top: 38px; page-break-inside: avoid; }
        .signatures td { border: 0; text-align: center; padding-top: 38px; }
        .signature-line { display: block; border-top: .6px solid #111; padding-top: 5px; margin: 0 12px; }
        .notes { white-space: pre-line; }
    </style>
</head>
<body>
@php
    $fields = \App\Support\SocioeconomicProfile::fields();
    $value = fn ($key) => \App\Support\SocioeconomicProfile::label($key, $profile[$key] ?? null);
    $dateValue = fn ($key) => filled($profile[$key] ?? null) ? \Illuminate\Support\Carbon::parse($profile[$key])->format('d/m/Y') : 'Não informado';
    $phones = collect([$student->phone, $guardian?->phone, $guardian?->secondary_phone])
        ->concat($student->contacts->flatMap(fn ($contact) => [$contact->phone, $contact->secondary_phone]))->filter()->unique()->take(2)->join(' / ');
    $benefitYear = (int) ($profile['benefit_year'] ?? $academicYear->reference_year);
    $benefits = $benefitYear === (int) $academicYear->reference_year ? ($profile['benefits'] ?? []) : [];
    $staff = \App\Support\SchoolSignatureStaff::forSchool($academicYear->school, $issuedDocument->issued_at);
    $studentCanSign = $student->birth_date && $student->birth_date->copy()->addYears(18)->format('Y-m-d') <= $issuedDocument->issued_at->copy()->timezone('America/Cuiaba')->format('Y-m-d');
@endphp
@include('reports.partials.letterhead', ['title' => 'Ficha socioeconômica', 'letterhead' => $letterhead, 'issuedDocument' => $issuedDocument])
<p class="subtitle"><strong>Termo de concessão de benefícios</strong> · {{ $enrollment->courses->map(fn ($course) => $course->stageLabel())->unique()->join(' / ') ?: 'Estudante' }} · Ano letivo: {{ $academicYear->referenceYearsLabel() }}<br>INEP do estudante: {{ $student->student_inep ?: 'Não informado' }}</p>

<table>
    <tr><td colspan="3"><strong>Aluno(a):</strong> {{ $student->full_name }}</td><td><strong>Sexo:</strong> {{ $value('sex') }}</td></tr>
    <tr><td><strong>Nascimento:</strong> {{ $student->birth_date?->format('d/m/Y') ?: 'Não informado' }}</td><td colspan="2"><strong>Naturalidade / UF:</strong> {{ collect([$student->birth_city, $student->birth_state])->filter()->join(' / ') ?: 'Não informado' }}</td><td><strong>Nacionalidade:</strong> {{ $student->nationality ?: 'Não informado' }}</td></tr>
    <tr><td><strong>RG:</strong> {{ $value('rg_number') }}</td><td><strong>Emissão:</strong> {{ $dateValue('rg_issued_at') }}</td><td><strong>Órgão expedidor:</strong> {{ $value('rg_issuer') }}</td><td><strong>CPF:</strong> {{ $student->cpf ?: 'Não informado' }}</td></tr>
    <tr><td><strong>NIS:</strong> {{ $student->nis ?: 'Não informado' }}</td><td><strong>Cartão do SUS:</strong> {{ $value('sus_card') }}</td><td colspan="2"><strong>Possui deficiência (PCD):</strong> {{ $value('has_disability') }}@if(filled($profile['disability_details'] ?? null)) — {{ $profile['disability_details'] }}@endif</td></tr>
    <tr><td><strong>CEP:</strong> {{ $student->postal_code ?: 'Não informado' }}</td><td colspan="3"><strong>Endereço:</strong> {{ $student->address ?: 'Não informado' }} · {{ collect([$student->city, $student->state])->filter()->join(' / ') }}</td></tr>
    <tr><td><strong>Nº:</strong> {{ $student->number ?: 'Não informado' }}</td><td colspan="2"><strong>Complemento:</strong> {{ $student->address_complement ?: 'Não informado' }}</td><td><strong>Unidade consumidora:</strong> {{ $value('consumer_unit') }}</td></tr>
    <tr><td colspan="2"><strong>Bairro:</strong> {{ $student->district ?: 'Não informado' }}</td><td colspan="2"><strong>Localização residencial:</strong> {{ $value('residential_zone') }}</td></tr>
    <tr><td colspan="4"><strong>Cor / raça declarada:</strong> {{ $value('race') }}</td></tr>
    <tr><td colspan="4"><strong>Filiação — Mãe:</strong> {{ $student->mother_name ?: 'Não informado' }}<br><strong>Pai:</strong> {{ $student->father_name ?: 'Não informado' }}</td></tr>
    <tr><td colspan="4"><strong>Responsável legal:</strong> {{ $guardian?->name ?: 'Não informado' }}</td></tr>
    <tr><td colspan="3"><strong>Telefones:</strong> {{ $phones ?: 'Não informado' }}</td><td><strong>Tipo sanguíneo:</strong> {{ $value('blood_type') }}</td></tr>
</table>

<h2>Informações sociofamiliares e cuidados</h2>
<table>
    <tr><td colspan="2"><strong>Reside com:</strong> {{ $value('lives_with') }}@if(filled($profile['lives_with_other'] ?? null)) · {{ $profile['lives_with_other'] }}@endif</td></tr>
    <tr><td colspan="2"><strong>Renda familiar:</strong> {{ $value('family_income_range') }}</td></tr>
    <tr><td><strong>Recebe Bolsa Família:</strong> {{ $value('receives_bolsa_familia') }}</td><td><strong>NIS do beneficiário:</strong> {{ $value('bolsa_familia_nis') }}</td></tr>
    <tr><td colspan="2"><strong>Apresenta alergia:</strong> {{ $value('has_allergies') }}@if(filled($profile['allergy_details'] ?? null)) — {{ $profile['allergy_details'] }}@endif</td></tr>
    <tr><td colspan="2"><strong>Alergia a medicamentos / alimentos:</strong> {{ $value('has_medication_food_allergies') }}@if(filled($profile['medication_food_allergy_details'] ?? null)) — {{ $profile['medication_food_allergy_details'] }}@endif</td></tr>
    <tr><td colspan="2"><strong>Mãe / responsáveis trabalham fora:</strong> {{ $value('guardians_work_outside') }}</td></tr>
    <tr><td colspan="2"><strong>Estudante pode ser entregue a:</strong> {{ $value('authorized_pickup') }}</td></tr>
    <tr><td><strong>Transporte escolar:</strong> {{ $value('uses_school_transport') }}</td><td><strong>Autorização de uso de imagem:</strong> {{ $value('image_use_authorized') }}</td></tr>
</table>

<h2>1. Situação sociofamiliar para concessão de benefícios integrais</h2>
<p>Nos termos da Lei Complementar nº 187/2021 e do Decreto nº 11.791/2023.</p>
<div class="choices"><strong>Benefícios concedidos — referência {{ $academicYear->reference_year }}:</strong><br>
    @foreach($fields['benefits']['options'] as $key => $label)<span>({{ in_array($key, $benefits, true) ? 'X' : ' ' }}) {{ $label }}</span>@endforeach
    @if($benefits === [])<div>Concessão de benefícios não informada para este ano.</div>@endif
</div>
<div class="declaration">
    <strong>2. Declaração do(a) bolsista / responsável legal</strong>
    <p>Para os fins de recebimento dos benefícios assinalados nesta ficha (bolsa de estudo integral gratuita), declaro que:</p>
    <p>I — Minha renda familiar bruta mensal per capita não excede o valor de 1,5 (um inteiro e cinco décimos) salário mínimo;</p>
    <p>II — Os benefícios concedidos serão por mim utilizados no período de {{ $academicYear->reference_year }};</p>
    <p>III — Estou ciente de que a não conformidade nas informações prestadas poderá implicar a perda dos benefícios concedidos e a sujeição a possíveis ações judiciais de responsabilidade.</p>
</div>

<div class="enrollment-page">
<h2 class="center">Matrículas</h2>
<table class="enrollment-table">
    <thead><tr><th style="width:10%">Data</th><th style="width:10%">Ano letivo</th><th style="width:10%">Turno</th><th style="width:26%">Turma, etapa e modalidade</th><th style="width:10%">Ano do curso</th><th style="width:22%">Assinatura do responsável ou estudante maior de idade</th><th style="width:12%">Assinatura da direção</th></tr></thead>
    <tbody>
    @foreach($enrollments as $item)
        <tr><td>{{ $item->enrolled_at?->format('d/m/Y') ?: '-' }}</td><td>{{ $item->schoolClass->academicYear->referenceYearsLabel() }}</td><td>{{ $item->schoolClass->shift ?: '-' }}</td><td>{{ $item->schoolClass->name }}<br>{{ $item->courses->map(fn ($course) => $course->stageLabel().' / '.$course->modalityLabel())->unique()->join(' + ') }}</td><td>{{ $item->enrolled_at?->year ?: '-' }}</td><td></td><td></td></tr>
    @endforeach
    @for($blankRow = $enrollments->count(); $blankRow < 7; $blankRow++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>@endfor
    </tbody>
</table>
<p class="notes"><strong>Observações:</strong> {{ $profile['notes'] ?? '________________________________________________________________________' }}</p>
@if(filled($profile['assessed_at'] ?? null))<p><strong>Atualização dos dados socioeconômicos:</strong> {{ $dateValue('assessed_at') }}</p>@endif
<p>DECLARO ainda que o estudante, objeto desta matrícula, é contemplado com os benefícios assinalados nesta ficha, concedidos por esta instituição conforme especificado anteriormente.</p>
<p class="center">{{ collect([$academicYear->school->city, $academicYear->school->state])->filter()->join(' / ') }}, {{ $issuedDocument->issued_at->timezone('America/Cuiaba')->format('d/m/Y') }}.</p>
<table class="signatures">
    <tr><td colspan="2"><span class="signature-line">{{ $guardian?->name ?: ($studentCanSign ? $student->full_name : '') }}<br>Assinatura do(a) bolsista maior de idade / responsável legal</span></td></tr>
    <tr><td><span class="signature-line">{{ $staff[\App\Models\PersonSchoolRole::POSITION_DIRECTOR]?->full_name }}<br>Direção</span></td><td><span class="signature-line">Assistente social<br>Nº de registro: ________________________</span></td></tr>
</table>
</div>
@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
