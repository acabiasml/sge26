<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 17px 20px 30px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #1f1713; font-size: 11px; line-height: 1.18; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 7px; padding-bottom: 5px; }
        .document-title { font-size: 11.6px; margin-top: 4px; text-transform: uppercase; }
        .matrix-title {
            border: .8px solid #2f241f;
            border-bottom: 0;
            text-align: center;
            font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 3px 5px;
        }
        .matrix-subtitle { text-align: center; font-size: 11px; color: #534741; margin: 4px 0 5px; }
        table { width: 100%; border-collapse: collapse; }
        .matrix-table { border: .8px solid #2f241f; table-layout: fixed; }
        .matrix-table th {
            background: #ece8e4;
            color: #1f1713;
            border: .6px solid #2f241f;
            padding: 2.6px 3.2px;
            text-align: center;
            vertical-align: middle;
            font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif;
            font-weight: 600;
        }
        .matrix-table td { border: .5px solid #2f241f; padding: 2.6px 3.2px; vertical-align: middle; }
        .formation-cell {
            width: 22px;
            min-height: 92px;
            height: 92px;
            background: #e3dfdc;
            padding: 0;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
        }
        .formation-text {
            display: block;
            width: 104px;
            margin-left: -41px;
            font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
            text-transform: uppercase;
            transform: rotate(-90deg);
            white-space: nowrap;
        }
        .area-cell { width: 25%; text-align: center; background: #f5f2ef; font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; }
        .component-cell { width: 32%; font-size: 11px; }
        .center-cell { text-align: center; width: auto; }
        .total-row td { font-weight: 600; background: #eee5df; text-transform: uppercase; }
        .meta-grid { margin-top: 7px; }
        .meta-grid td { border: .5px solid #d8c8bf; padding: 3.4px 4px; }
        .meta-label { width: 26%; background: #f8f1eb; color: #6B3D2E; font-weight: 600; }
        .course-summary { margin-top: 7px; }
        .course-summary th { background: #f8f1eb; color: #6B3D2E; border: .5px solid #d8c8bf; padding: 3.4px 4px; text-align: left; }
        .course-summary td { border: .5px solid #d8c8bf; padding: 3.4px 4px; }
        .signature-block { width: 38%; margin-left: auto; margin-top: 30px; text-align: center; font-size: 11px; }
        .signature-date { margin-bottom: 48px; }
        .signature-line { border-top: .8px solid #6B3D2E; padding-top: 5px; font-weight: 600; }
        .signature-role { display: block; font-weight: 400; margin-top: 2px; }
        .document-footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 11px; }
    </style>
</head>
<body>
    @forelse ($matrixGroups as $matrixGroup)
        <section class="matrix-page">
            @include('reports.partials.letterhead', [
                'title' => 'Matriz curricular - '.$academicYear->name,
                'letterhead' => $letterhead,
                'issuedDocument' => $issuedDocument,
                'verificationUrl' => $verificationUrl,
            ])

            <div class="matrix-title">{{ $matrixGroup['title'] }}</div>
            <div class="matrix-subtitle">
                Ano letivo {{ $academicYear->referenceYearsLabel() }}
                &middot; Calendário: {{ $academicYear->name }}
                &middot; {{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}
                &middot; Dias letivos: {{ $academicYear->schoolDayCount() }}
                &middot; Aprovação: {{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos e {{ $academicYear->minimum_attendance_percentage }}% de frequência
            </div>

            <table class="matrix-table">
                <thead>
                    @if ($matrixGroup['courses']->count() === 1)
                        <tr>
                            <th style="width: 22px;"></th>
                            <th>Área</th>
                            <th>Componente curricular</th>
                            <th>Aulas semanais</th>
                            <th>Carga horária calculada</th>
                        </tr>
                    @else
                        <tr>
                            <th style="width: 22px;" rowspan="2"></th>
                            <th rowspan="2">Área</th>
                            <th rowspan="2">Componente curricular</th>
                            <th colspan="{{ $matrixGroup['courses']->count() }}">Aulas semanais por matriz</th>
                        </tr>
                        <tr>
                            @foreach ($matrixGroup['courses'] as $course)
                                <th>{{ $course->name }}</th>
                            @endforeach
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @foreach ($matrixGroup['rows'] as $formationGroup)
                        @php($formationPrinted = false)
                        @foreach ($formationGroup['areas'] as $areaGroup)
                            @php($areaPrinted = false)
                            @foreach ($areaGroup['components'] as $component)
                                <tr>
                                    @if (! $formationPrinted)
                                        <td class="formation-cell" rowspan="{{ $formationGroup['rowspan'] }}">
                                            <span class="formation-text">{{ $formationGroup['formation'] }}</span>
                                        </td>
                                        @php($formationPrinted = true)
                                    @endif
                                    @if (! $areaPrinted)
                                        <td class="area-cell" rowspan="{{ $areaGroup['rowspan'] }}">{{ $areaGroup['area'] }}</td>
                                        @php($areaPrinted = true)
                                    @endif
                                    <td class="component-cell">{{ $component['component'] }}</td>
                                    @if ($matrixGroup['courses']->count() === 1)
                                        @php($course = $matrixGroup['courses']->first())
                                        @php($weeklyLessons = $component['weekly_lessons'][$course->id] ?? null)
                                        <td class="center-cell">{{ $weeklyLessons !== null ? (int) $weeklyLessons : '-' }}</td>
                                        <td class="center-cell">
                                            {{ $weeklyLessons !== null ? number_format(((int) $weeklyLessons * (int) $course->class_hour_minutes * 40) / 60, 2, ',', '.') : '-' }} h
                                        </td>
                                    @else
                                        @foreach ($matrixGroup['courses'] as $course)
                                            <td class="center-cell">
                                                {{ isset($component['weekly_lessons'][$course->id]) && $component['weekly_lessons'][$course->id] !== null ? (int) $component['weekly_lessons'][$course->id] : '-' }}
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3">Total</td>
                        @if ($matrixGroup['courses']->count() === 1)
                            @php($course = $matrixGroup['courses']->first())
                            <td class="center-cell">{{ $matrixGroup['total_weekly_lessons'][$course->id] ?? 0 }}</td>
                            <td class="center-cell">{{ $matrixGroup['total_hours'][$course->id] ?? $course->formattedCalculatedWorkloadHours() }} h</td>
                        @else
                            @foreach ($matrixGroup['courses'] as $course)
                                <td class="center-cell">{{ $matrixGroup['total_weekly_lessons'][$course->id] ?? 0 }}</td>
                            @endforeach
                        @endif
                    </tr>
                </tbody>
            </table>

            <table class="meta-grid">
                <tr>
                    <td class="meta-label">Ano letivo</td>
                    <td>{{ $academicYear->referenceYearsLabel() }}</td>
                    <td class="meta-label">Período do calendário</td>
                    <td>{{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Dias letivos</td>
                    <td>{{ $academicYear->schoolDayCount() }}</td>
                    <td class="meta-label">Critérios de aprovação</td>
                    <td>{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos · {{ $academicYear->minimum_attendance_percentage }}% de frequência</td>
                </tr>
                <tr>
                    <td class="meta-label">Matrizes impressas</td>
                    <td colspan="3">{{ $matrixGroup['courses']->pluck('name')->join(', ') }}</td>
                </tr>
            </table>

            <table class="course-summary">
                <thead>
                    <tr>
                        <th>Matriz</th>
                        <th>Etapa</th>
                        <th>Modalidade</th>
                        <th>Hora-aula</th>
                        <th>Carga horária calculada</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrixGroup['courses'] as $course)
                        <tr>
                            <td>{{ $course->name }}</td>
                            <td>{{ $course->stageLabel() }}</td>
                            <td>{{ $course->modalityLabel() }}</td>
                            <td>{{ $course->class_hour_minutes }} minutos</td>
                            <td>{{ $matrixGroup['total_hours'][$course->id] ?? $course->formattedCalculatedWorkloadHours() }} h</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="signature-block">
                <div class="signature-date">
                    {{ $academicYear->school?->city ?: 'Poxoréu' }}{{ $academicYear->school?->state ? '-'.$academicYear->school->state : '-MT' }},
                    {{ $signatureDate?->format('d/m/Y') }}.
                </div>
                <div class="signature-line">
                    {{ $directorName ?: 'Direção escolar' }}
                    @if ($directorName)
                        <span class="signature-role">Direção escolar</span>
                    @endif
                </div>
            </div>
        </section>
        @if (! $loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @empty
        @include('reports.partials.letterhead', [
            'title' => 'Matrizes curriculares - '.$academicYear->name,
            'letterhead' => $letterhead,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => $verificationUrl,
        ])
        <p>Nenhuma matriz cadastrada para este ano letivo.</p>
    @endforelse

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
