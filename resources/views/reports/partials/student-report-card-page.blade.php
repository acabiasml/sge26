@php
    use App\Support\CurriculumCatalog;

    $enrollment = $report['enrollment'];
    $student = $report['student'];
    $academicYear = $report['academicYear'];
    $schoolClass = $report['schoolClass'];
    $periods = $report['periods'];
    $periodShortLabel = fn ($period): string => preg_replace(
        ['/\bBimestre\b/iu', '/\bTrimestre\b/iu', '/\bSemestre\b/iu'],
        ['Bim.', 'Trim.', 'Sem.'],
        $period->name,
    );
    $courses = $report['courses'];
    $school = $academicYear->school;
    $naturalidade = collect([
        $student->birth_city ?: ($student->legacy_metadata['naturalidade'] ?? null),
        $student->birth_state ?: ($student->legacy_metadata['naturalidade_uf'] ?? null),
    ])->filter()->join(', ');
    $nacionalidade = $student->nationality ?: ($student->legacy_metadata['nacionalidade'] ?? null);
    $formatCpf = function (?string $cpf): string {
        $digits = preg_replace('/\D/', '', (string) $cpf);

        return strlen($digits) === 11
            ? substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2)
            : ($cpf ?: '-');
    };
    $formatHours = fn (float|int|null $value): string => $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $formatHoursWithUnit = fn (float|int|null $value): string => $value === null ? '-' : $formatHours($value).' h';
    $sortText = fn (?string $value): string => mb_strtolower(\Illuminate\Support\Str::ascii((string) $value), 'UTF-8');
    $friendlyConceptRange = function ($concept): string {
        $minimum = $concept->minimum_score !== null ? rtrim(rtrim(number_format((float) $concept->minimum_score, 1, ',', '.'), '0'), ',') : null;
        $maximum = $concept->maximum_score !== null ? rtrim(rtrim(number_format((float) $concept->maximum_score, 1, ',', '.'), '0'), ',') : null;

        if ($minimum === null && $maximum === null) {
            return 'para qualquer nota';
        }

        if ($minimum === null) {
            return ($concept->maximum_inclusive ? 'até ' : 'menor que ').$maximum;
        }

        if ($maximum === null) {
            return ($concept->minimum_inclusive ? 'a partir de ' : 'maior que ').$minimum;
        }

        return ($concept->minimum_inclusive ? 'de ' : 'maior que ')
            .$minimum
            .($concept->maximum_inclusive ? ' até ' : ' até menor que ')
            .$maximum;
    };
    $scoreLabel = function ($score, $date = null) use ($academicYear, $scoreView): string {
        if ($score === null || $score === '') {
            return '-';
        }

        if ($scoreView === 'conceitos') {
            $concept = $academicYear->school?->conceptForScore((float) $score, $date);

            return $concept?->shortLabel() ?? '-';
        }

        return number_format((float) $score, 1, ',', '.');
    };
    $stageLine = $courses
        ->pluck('stage')
        ->map(fn ($stage) => \App\Models\AcademicCourse::STAGE_LABELS[$stage] ?? $stage)
        ->filter()
        ->unique()
        ->join(' / ');
    $classLine = collect([
        $schoolClass->name,
        $stageLine,
        $academicYear->referenceYearsLabel(),
    ])->filter()->unique()->join(' · ');
    $annualComponents = $report['annualComponents']
        ->sort(function (array $first, array $second) use ($courses, $sortText): int {
            $firstComponent = $first['component'];
            $secondComponent = $second['component'];
            $firstFormation = CurriculumCatalog::formationLabelForArea($firstComponent->course ?? $courses->first(), $firstComponent->area);
            $secondFormation = CurriculumCatalog::formationLabelForArea($secondComponent->course ?? $courses->first(), $secondComponent->area);
            $formationComparison = CurriculumCatalog::formationOrder($firstFormation) <=> CurriculumCatalog::formationOrder($secondFormation);

            if ($formationComparison !== 0) {
                return $formationComparison;
            }

            $areaComparison = strnatcasecmp(
                $sortText($firstComponent->area?->name ?? 'Área não definida'),
                $sortText($secondComponent->area?->name ?? 'Área não definida'),
            );

            if ($areaComparison !== 0) {
                return $areaComparison;
            }

            return strnatcasecmp($sortText($firstComponent->name), $sortText($secondComponent->name));
        })
        ->values();
    $groupedComponents = $annualComponents
        ->groupBy(fn (array $summary): string => CurriculumCatalog::formationLabelForArea($summary['component']->course ?? $courses->first(), $summary['component']->area))
        ->map(fn ($formationItems, string $formation): array => [
            'formation' => $formation,
            'rowspan' => $formationItems->count(),
            'areas' => $formationItems
                ->groupBy(fn (array $summary): string => $summary['component']->area?->name ?? 'Área não definida')
                ->map(fn ($areaItems, string $area): array => [
                    'area' => $area,
                    'rowspan' => $areaItems->count(),
                    'items' => $areaItems->values(),
                ])
                ->values(),
        ])
        ->values();
    $plannedHoursTotal = $annualComponents->sum(fn (array $summary): float => $summary['component']->calculatedWorkloadHours($summary['component']->course ?? $courses->first()));
    $completedHoursTotal = $annualComponents->sum(function (array $summary) use ($courses): float {
        $course = $summary['component']->course ?? $courses->first();
        $minutes = (int) ($course?->class_hour_minutes ?? 0);

        return round(((int) ($summary['attendance']['lessons'] ?? 0) * $minutes) / 60, 2);
    });
    $conceptLegend = $school?->conceptsForDate($academicYear->ends_at ?? now()) ?? collect();
@endphp

@include('reports.partials.letterhead', [
    'title' => 'Boletim escolar',
    'letterhead' => $letterhead,
    'repeatOnEveryPage' => false,
    'issuedDocument' => $issuedDocument,
    'verificationUrl' => $verificationUrl,
])

<div class="class-line">
    {{ $classLine }}
</div>

<section class="student-meta">
    <p><strong>Estudante:</strong> {{ $student->full_name }}</p>
    <p>
        <strong>Naturalidade:</strong> {{ $naturalidade ?: '-' }}.
        <strong>Nacionalidade:</strong> {{ $nacionalidade ?: '-' }}.
        <strong>Data de nascimento:</strong> {{ $student->birth_date?->format('d/m/Y') ?? '-' }}.
    </p>
    <p><strong>Mãe:</strong> {{ $student->mother_name ?: '-' }}. <strong>Pai:</strong> {{ $student->father_name ?: '-' }}.</p>
    <p><strong>CPF:</strong> {{ $formatCpf($student->cpf) }} | <strong>Tel.:</strong> {{ $student->phone ?: '-' }}</p>
</section>

<table class="report-table">
    <thead>
        <tr>
            <th colspan="3" rowspan="2">Componentes curriculares</th>
            @foreach($periods as $period)
                <th colspan="2">{{ $periodShortLabel($period) }}</th>
            @endforeach
            <th rowspan="2">TF</th>
            <th rowspan="2">CHP (h)</th>
            <th rowspan="2">CHC (h)</th>
        </tr>
        <tr>
            @foreach($periods as $period)
                <th>N</th>
                <th>F</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($groupedComponents as $formationGroup)
            @foreach($formationGroup['areas'] as $areaGroup)
                @foreach($areaGroup['items'] as $summary)
                    @php
                        $component = $summary['component'];
                        $course = $component->course ?? $courses->first();
                        $plannedHours = $component->calculatedWorkloadHours($course);
                        $completedHours = round(((int) ($summary['attendance']['lessons'] ?? 0) * (int) ($course?->class_hour_minutes ?? 0)) / 60, 2);
                    @endphp
                    <tr>
                        @if($loop->parent->first && $loop->first)
                            <td class="formation-cell" rowspan="{{ $formationGroup['rowspan'] }}">{{ mb_strtoupper($formationGroup['formation'], 'UTF-8') }}</td>
                        @endif
                        @if($loop->first)
                            <td class="area-cell" rowspan="{{ $areaGroup['rowspan'] }}">{{ $areaGroup['area'] }}</td>
                        @endif
                        <td class="component-cell">{{ $component->name }}</td>
                        @foreach($periods as $period)
                            @php
                                $periodComponent = $summary['periods']->first(fn (array $item): bool => $item['component']->id === $component->id && $period->is($item['period'] ?? $period));
                                $periodDate = $period->ends_at ?? $period->starts_at;
                            @endphp
                            <td class="center">{{ $periodComponent ? $scoreLabel($periodComponent['average']['value'], $periodDate) : '-' }}</td>
                            <td class="center">{{ $periodComponent ? (int) ($periodComponent['attendance']['absent'] ?? 0) : '-' }}</td>
                        @endforeach
                        <td class="center">{{ (int) ($summary['attendance']['absent'] ?? 0) }}</td>
                        <td class="center">{{ $formatHoursWithUnit($plannedHours) }}</td>
                        <td class="center">{{ $formatHoursWithUnit($completedHours) }}</td>
                    </tr>
                @endforeach
            @endforeach
        @empty
            <tr>
                <td colspan="{{ 6 + ($periods->count() * 2) }}" class="center">Nenhum componente no boletim.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="3"><strong>Comportamento</strong></td>
            @foreach($periods as $period)
                @php
                    $periodReport = $report['periodReports']->first(fn (array $item): bool => $period->is($item['period']));
                    $periodDate = $period->ends_at ?? $period->starts_at;
                @endphp
                <td class="center">{{ $scoreLabel($periodReport['behavior']?->score ?? null, $periodDate) }}</td>
                <td class="center">-</td>
            @endforeach
            <td class="center">-</td>
            <td class="center">-</td>
            <td class="center">-</td>
        </tr>
    </tbody>
</table>

<section class="summary">
    <p><strong>Dias letivos:</strong> {{ $academicYear->schoolDayCount() }}</p>
    <p><strong>Critérios de aprovação:</strong> mínimo de {{ $formatHours($report['passingPoints']) }} pontos por componente curricular e frequência mínima de {{ $report['minimumAttendance'] }}%.</p>
    <p><strong>Frequência anual efetiva:</strong> {{ $report['annualAttendance']['percentage'] !== null ? number_format((float) $report['annualAttendance']['percentage'], 1, ',', '.').'%' : '-' }}</p>
    <p><strong>Carga horária total prevista:</strong> {{ $formatHoursWithUnit($plannedHoursTotal) }} | <strong>Carga horária total cumprida:</strong> {{ $formatHoursWithUnit($completedHoursTotal) }}</p>
    <p><strong>Data da matrícula:</strong> {{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</p>
</section>

<section class="legend">
    <strong>Legenda:</strong>
    N (nota); F (falta); TF (total de faltas); CHP (carga horária prevista); CHC (carga horária cursada).
    Faltas justificadas permanecem registradas, mas contam na frequência efetiva para aprovação.
    @if($conceptLegend->isNotEmpty())
        <div class="concept-legend">
            <strong>Conceitos:</strong>
            @foreach($conceptLegend as $concept)
                <span>{{ $concept->shortLabel() }} = {{ $concept->name }} ({{ $friendlyConceptRange($concept) }})</span>
            @endforeach
        </div>
    @endif
</section>

<table class="signatures">
    <tr>
        @include('reports.partials.signature-staff', ['signatureType' => 'pedagogical', 'signatureDate' => $issuedDocument->issued_at ?? now()])
    </tr>
</table>
