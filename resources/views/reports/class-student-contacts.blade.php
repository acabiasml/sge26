<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 150px 22px 96px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.15; color: #111; }
        @include('reports.partials.letterhead-styles')
        .document-title { font-size: 14px; margin: 5px 0 0; }
        .context { margin-bottom: 12px; }
        .muted { color: #555; font-size: 11px; }
        .students { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .students > tbody > tr { page-break-inside: avoid; }
        .student { padding: 0 0 10px; }
        .identity, .family { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .identity td, .family td { padding: 4px 6px; border: .5px solid #c6c6c6; vertical-align: top; overflow-wrap: break-word; }
        .name { background: #eee; font-size: 13px; }
        .label { color: #555; font-size: 10px; }
        .family td { border-top: 0; }
        .contact { margin: 2px 0 4px; }
    </style>
</head>
<body>
@include('reports.partials.letterhead', ['title' => 'Alunos e responsáveis da turma', 'showTechnicalRegulation' => false])
<div class="context">
    <strong>{{ $academicYear->school?->name }}</strong><br>
    Turma: {{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $schoolClass->courses) }} · Ano letivo: {{ $academicYear->referenceYearsLabel() }}<br>
    <span class="muted">{{ $rows->count() }} aluno(s) com matrícula ativa · Idades em {{ $referenceDate->format('d/m/Y') }}</span>
</div>
<table class="students"><tbody>
@forelse($rows as $student)
    <tr><td class="student">
        <table class="identity">
            <tr><td class="name" colspan="3"><strong>{{ $loop->iteration }}. {{ $student['name'] }}</strong></td></tr>
            <tr>
                <td><span class="label">Nascimento:</span> {{ $student['birth_date'] }}</td>
                <td><span class="label">Idade:</span> {{ $student['age'] !== null ? $student['age'].' anos' : '-' }}</td>
                <td><span class="label">CPF:</span> {{ $student['cpf'] }}</td>
            </tr>
        </table>
        <table class="family">
            <tr>
                <td><span class="label">Mãe:</span> {{ $student['mother'] }}</td>
                <td><span class="label">Pai:</span> {{ $student['father'] }}</td>
            </tr>
            <tr><td colspan="2">
                <span class="label">Responsáveis e contatos cadastrados — telefones</span>
                @forelse($student['contacts'] as $contact)
                    <div class="contact"><strong>{{ $contact['name'] ?: '-' }}</strong> ({{ $contact['relationship'] }}) — {{ implode(' / ', $contact['phones']) ?: 'Telefone não informado' }}</div>
                @empty
                    <div>Nenhum contato cadastrado.</div>
                @endforelse
            </td></tr>
        </table>
    </td></tr>
@empty
    <tr><td>Nenhum aluno com matrícula ativa nesta turma.</td></tr>
@endforelse
</tbody></table>
@include('reports.partials.document-footer')
</body>
</html>
