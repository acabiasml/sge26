<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 150px 24px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11.2px; line-height: 1.42; }
        @include('reports.partials.letterhead-styles')
        .certificate-text { font-size: 12px; text-align: justify; margin: 18px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 13px; }
        th { text-align: left; background: #f6f0ea; padding: 5px 6px; border: .7px solid #ead8cc; }
        td { padding: 5px 6px; border: .7px solid #ead8cc; }
        .summary th { width: 30%; }
        .matrices { font-size: 11px; margin-top: 15px; }
        .matrices th { background: #6b3d2e; color: #fff; text-align: center; }
        .matrices td { text-align: center; }
        .matrices td:first-child, .matrices td:nth-child(2) { text-align: left; }
        .signature { margin-top: 65px; }
        .signature td { border: 0; text-align: center; padding-top: 45px; width: 50%; }
        .line { border-top: 1px solid #6b3d2e; display: inline-block; min-width: 245px; padding-top: 6px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@foreach ($certificates as $certificate)
    @php
        $enrollment = $certificate['enrollment'];
        $student = $enrollment->student;
        $attendance = $certificate['attendance'];
        $percentage = $attendance['percentage'] !== null ? number_format((float) $attendance['percentage'], 1, ',', '.').'%' : null;
        $courses = $enrollment->courses->pluck('name')->join(' + ') ?: '-';
        $stages = \App\Support\AcademicContextLabel::stages($enrollment->courses);
    @endphp
    @include('reports.partials.letterhead', ['title' => 'Atestado de frequência', 'letterhead' => $letterhead, 'issuedDocument' => $issuedDocument])
    <p class="certificate-text">Atestamos, para os devidos fins, que <strong>{{ $student?->full_name }}</strong>, CPF {{ $student?->cpf ?: '-' }}, filho(a) de {{ $student?->mother_name ?: '-' }}, encontra-se matriculado(a) nesta instituição no ano letivo <strong>{{ $academicYear->referenceYearsLabel() }}</strong>, na turma <strong>{{ $class->name }}</strong>, etapa <strong>{{ $stages }}</strong>, vinculada à(s) matriz(es) <strong>{{ $courses }}</strong>.</p>
    <p class="certificate-text">No recorte <strong>{{ $scope['label'] }}</strong>, de <strong>{{ $scope['starts_at']->format('d/m/Y') }}</strong> a <strong>{{ $scope['ends_at']->format('d/m/Y') }}</strong>, @if($percentage) constam {{ $attendance['lessons'] }} aula(s), {{ $attendance['effective_attended'] }} presença(s) consideradas, {{ $attendance['absent'] }} falta(s), sendo {{ $attendance['justified'] }} justificada(s), correspondendo a <strong>{{ $percentage }}</strong> de frequência. @else ainda não há lançamentos suficientes para o cálculo percentual. @endif</p>
    <table class="summary">
        <tr><th>Estudante</th><td>{{ $student?->full_name }}</td></tr><tr><th>Escola</th><td>{{ $academicYear->school?->name }}</td></tr>
        <tr><th>Ano letivo</th><td>{{ $academicYear->referenceYearsLabel() }}</td></tr><tr><th>Turma</th><td>{{ $class->name }}</td></tr>
        <tr><th>Recorte</th><td>{{ $scope['label'] }}</td></tr><tr><th>Aulas lançadas</th><td>{{ $attendance['lessons'] }}</td></tr>
        <tr><th>Faltas</th><td>{{ $attendance['absent'] }} ({{ $attendance['justified'] }} justificada(s))</td></tr><tr><th>Frequência</th><td>{{ $percentage ?? '-' }}</td></tr>
    </table>
    <table class="matrices"><thead><tr><th>Matriz</th><th>Etapa</th><th>Aulas</th><th>Presenças</th><th>Faltas</th><th>Frequência</th></tr></thead><tbody>
    @forelse($certificate['matrices'] as $matrix)<tr><td>{{ $matrix['course']->name }}</td><td>{{ $matrix['stage'] }}</td><td>{{ $matrix['attendance']['lessons'] }}</td><td>{{ $matrix['attendance']['effective_attended'] }}</td><td>{{ $matrix['attendance']['absent'] }}</td><td>{{ $matrix['attendance']['percentage'] !== null ? number_format((float)$matrix['attendance']['percentage'], 1, ',', '.').'%' : '-' }}</td></tr>@empty<tr><td colspan="6">Nenhuma matriz vinculada.</td></tr>@endforelse
    </tbody></table>
    <table class="signature"><tr><td><span class="line">Direção escolar</span></td><td><span class="line">Secretaria escolar</span></td></tr></table>
    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
    @unless($loop->last)<div class="page-break"></div>@endunless
@endforeach
</body></html>
