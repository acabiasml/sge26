<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 155px 24px 100px; }
        body { font: 12px 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; }
        @include('reports.partials.letterhead-styles')
        table { width: 100%; border-collapse: collapse; }
        .course-rows th, .course-rows td { border: 1px solid #b9b9b9; padding: 4px; }
        .number { text-align: right; }
        .technical-history-closing { page-break-inside: avoid; }
        .signature { margin-top: 8px; page-break-inside: avoid; text-align: center; table-layout: fixed; }
        .line { border-top: 1px solid #555; display: block; margin: 0 14px; }
        .signature td { padding-top: 32px; width: 50%; }
        @include('reports.partials.student-document-chrome-styles')
    </style>
</head>
<body>
@include('reports.partials.letterhead', ['title' => $title, 'letterhead' => $letterhead, 'issuedDocument' => $issuedDocument])
@include('reports.partials.student-identification', ['student' => $enrollment->student, 'showCredentialIdentity' => true, 'showStudentEmails' => false])
<p><strong>{{ __('Ato autorizativo') }}</strong> {{ $course->technicalProgram->authorizationReference() }}</p>
<p><strong>{{ __('Ingresso') }}</strong> {{ $enrollment->enrolled_at?->format('d/m/Y') }} · {{ ucfirst(app(\App\Support\TechnicalEnrollmentOffer::class)->resolve($enrollment, $course) ?? '') }}</p>
<p><strong>{{ __('Carga horária') }}</strong> {{ number_format($modules->sum('hours'), 2, ',', '.') }} h ·
    <strong>{{ __('Conclusão') }}</strong> {{ $modules->max('completed_at') ? \Carbon\Carbon::parse($modules->max('completed_at'))->format('d/m/Y') : '—' }}</p>
<table class="course-rows">
    <thead><tr><th>{{ __('Componente curricular') }}</th><th>{{ __('Carga (h)') }}</th><th>{{ __('Nota') }}</th><th>{{ __('Frequência (%)') }}</th></tr></thead>
    <tbody>
    @foreach($modules as $module)
        <tr><th colspan="4">{{ $module['name'] }} @if($module['qualification']) · {{ $module['qualification'] }} @endif</th></tr>
        @foreach($module['components'] as $component)
            <tr>
                <td>{{ $component['name'] }}</td>
                <td class="number">{{ $component['hours'] }}</td>
                <td class="number">{{ $component['final_average'] ?? '—' }}</td>
                <td class="number">{{ $component['attendance_percentage'] ?? '—' }}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
<p><strong>{{ __('Perfil profissional') }}</strong> {{ $issuedDocument->payload['professional_profile'] ?? __('A preencher na emissão') }}</p>
<p><strong>{{ __('Prática profissional e estágio') }}</strong> {{ $issuedDocument->payload['professional_practice'] ?? __('A preencher na emissão') }}</p>
<section class="technical-history-closing">
<p data-pdf-content="technical-history"><strong>{{ __('Titulação ou qualificação') }}</strong> {{ isset($issuedDocument->payload['module_key']) ? $modules->first()['qualification'] : $course->technicalProgram->graduate_title }}</p>
<table class="signature" data-pdf-signatures="technical-history"><tr>
    @include('reports.partials.signature-staff', ['school' => $enrollment->schoolClass->academicYear->school, 'signatureType' => 'secretarial', 'signatureLineClass' => 'line', 'signatureDate' => $issuedDocument->issued_at])
</tr></table>
</section>
@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
