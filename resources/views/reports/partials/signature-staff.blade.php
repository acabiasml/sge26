@php
    $signatureType = $signatureType ?? 'secretarial';
    $signatureLineClass = $signatureLineClass ?? 'signature-line';
    $staff = \App\Support\SchoolSignatureStaff::forSchool($school ?? null, $signatureDate ?? now());
    $secondPosition = $signatureType === 'pedagogical' ? 'coordenador' : 'secretário';
    $secondRole = $signatureType === 'pedagogical' ? 'Coordenação escolar' : 'Secretaria escolar';
@endphp
<td>
    <span class="{{ $signatureLineClass }}">
        @if($staff['diretor'])<span class="signature-name">{{ $staff['diretor']->full_name }}</span>@endif
        <span class="signature-role">Direção escolar</span>
    </span>
</td>
<td>
    <span class="{{ $signatureLineClass }}">
        @if($staff[$secondPosition])<span class="signature-name">{{ $staff[$secondPosition]->full_name }}</span>@endif
        <span class="signature-role">{{ $secondRole }}</span>
    </span>
</td>
