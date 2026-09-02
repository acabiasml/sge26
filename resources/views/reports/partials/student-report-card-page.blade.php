@php
    use App\Support\CurriculumCatalog;

    $enrollment = $report['enrollment'];
    $student = $report['student'];
    $academicYear = $report['academicYear'];
    $schoolClass = $report['schoolClass'];
    $periodShortLabel = fn ($period): string => preg_replace(
        ['/\bBimestre\b/iu', '/\bTrimestre\b/iu', '/\bSemestre\b/iu'],
        ['Bim.', 'Trim.', 'Sem.'],
        $period->name,
    );
    $courses = $report['courses'];
    $technicalCourses = $courses->where('stage', \App\Models\AcademicCourse::STAGE_TECHNICAL)->unique('id')->values();
    $school = $academicYear->school;
    $naturalidade = collect([
        $student->birth_city ?: ($student->legacy_metadata['naturalidade'] ?? null),
        mb_strtoupper((string) ($student->birth_state ?: ($student->legacy_metadata['naturalidade_uf'] ?? null)), 'UTF-8') ?: null,
    ])->filter()->join(', ');
    $nacionalidade = $student->nationality ?: ($student->legacy_metadata['nacionalidade'] ?? null);
    $address = collect([
        $student->address,
        $student->number,
        $student->address_complement,
        $student->district,
        collect([$student->city, $student->state ? mb_strtoupper($student->state, 'UTF-8') : null])->filter()->join(' - '),
        $student->postal_code ? 'CEP '.$student->postal_code : null,
    ])->filter()->join(', ');
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
            'periods' => $formationItems
                ->flatMap(fn (array $summary) => $summary['periods']->pluck('period'))
                ->unique('id')
                ->sortBy(fn ($period): string => sprintf('%s-%010d', $period->starts_at?->format('Y-m-d') ?? '9999-12-31', $period->position))
                ->values(),
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
    $issuedAt = ($issuedDocument->issued_at ?? now('America/Cuiaba'))->timezone('America/Cuiaba');
    $issuedDate = $issuedAt->toDateString();
    $currentAcademicPeriod = $report['periods']->first(fn ($period): bool =>
        $period->academic_year_id === $academicYear->id
        && $period->starts_at
        && $period->ends_at
        && $issuedDate >= $period->starts_at->toDateString()
        && $issuedDate <= $period->ends_at->toDateString()
    );
@endphp

@include('reports.partials.letterhead', [
    'title' => 'Boletim escolar',
    'letterhead' => $letterhead,
    'repeatOnEveryPage' => false,
    'issuedDocument' => $issuedDocument,
    'verificationUrl' => $verificationUrl,
    'showTechnicalRegulation' => false,
])

<div class="class-line">
    {{ $classLine }}
</div>

<section class="student-meta">
    <p><strong>Estudante:</strong> {{ $student->full_name }}</p>
    @if($student->social_name)
        <p><strong>Nome social:</strong> {{ $student->social_name }}</p>
    @endif
    <p>
        <strong>CPF:</strong> {{ $formatCpf($student->cpf) }}
        @if($student->student_inep) | <strong>INEP:</strong> {{ $student->student_inep }} @endif
        @if($student->nis) | <strong>NIS:</strong> {{ $student->nis }} @endif
        @if($student->legacy_code) | <strong>Código da pasta:</strong> {{ $student->legacy_code }} @endif
    </p>
    <p>
        <strong>Naturalidade:</strong> {{ $naturalidade ?: '-' }}.
        <strong>Nacionalidade:</strong> {{ $nacionalidade ?: '-' }}.
        <strong>Data de nascimento:</strong> {{ $student->birth_date?->format('d/m/Y') ?? '-' }}.
    </p>
    <p><strong>Mãe:</strong> {{ $student->mother_name ?: '-' }}. <strong>Pai:</strong> {{ $student->father_name ?: '-' }}.</p>
    @if($student->phone || $student->personal_email || $student->institutional_email)
        <p>
            @if($student->phone)<strong>Telefone:</strong> {{ $student->phone }}@endif
            @if($student->phone && ($student->personal_email || $student->institutional_email)) | @endif
            @if($student->personal_email)<strong>E-mail:</strong> {{ $student->personal_email }}@endif
            @if($student->personal_email && $student->institutional_email) | @endif
            @if($student->institutional_email)<strong>E-mail institucional:</strong> {{ $student->institutional_email }}@endif
        </p>
    @endif
    @if($address)
        <p><strong>Endereço:</strong> {{ $address }}</p>
    @endif
</section>

@forelse($groupedComponents as $formationGroup)
    @php
        $formationPeriods = $formationGroup['periods'];
        $itineraryNames = $formationGroup['areas']
            ->flatMap(fn (array $areaGroup) => $areaGroup['items'])
            ->map(fn (array $summary): string => CurriculumCatalog::areaLabelForComponent(
                $summary['component']->course ?? $courses->first(),
                $summary['component']->area,
            ))
            ->filter()
            ->unique()
            ->join(' / ');
    @endphp
    @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_ITINERARY && $technicalCourses->isNotEmpty())
        @include('reports.partials.technical-course-regulations', ['technicalCourses' => $technicalCourses])
    @endif
    <table class="report-table">
        <thead>
            <tr>
                <th colspan="2" rowspan="2">
                    {{ mb_strtoupper($formationGroup['formation'], 'UTF-8') }}
                    @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_ITINERARY && $itineraryNames)
                        <span class="formation-area-label">{{ mb_strtoupper($itineraryNames, 'UTF-8') }}</span>
                    @endif
                </th>
                @foreach($formationPeriods as $period)
                    <th colspan="2">{{ $periodShortLabel($period) }}</th>
                @endforeach
                <th rowspan="2">TF</th>
                <th rowspan="2">CHP (h)</th>
                <th rowspan="2">CHC (h)</th>
            </tr>
            <tr>
                @foreach($formationPeriods as $period)
                    <th>N</th>
                    <th>F</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($formationGroup['areas'] as $areaGroup)
                @foreach($areaGroup['items'] as $summary)
                    @php
                        $component = $summary['component'];
                        $course = $component->course ?? $courses->first();
                        $plannedHours = $component->calculatedWorkloadHours($course);
                        $completedHours = round(((int) ($summary['attendance']['lessons'] ?? 0) * (int) ($course?->class_hour_minutes ?? 0)) / 60, 2);
                    @endphp
                    <tr>
                        @if($formationGroup['formation'] !== CurriculumCatalog::FORMATION_ITINERARY && $loop->first)
                            <td class="area-cell" rowspan="{{ $areaGroup['rowspan'] }}">{{ $areaGroup['area'] }}</td>
                        @endif
                        <td class="component-cell" @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_ITINERARY) colspan="2" @endif>{{ $component->name }}</td>
                        @foreach($formationPeriods as $period)
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
            @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_FGB)
                <tr>
                    <td colspan="2"><strong>Comportamento</strong></td>
                    @foreach($formationPeriods as $period)
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
            @endif
        </tbody>
    </table>
@empty
    <table class="report-table">
        <tr><td class="center">Nenhum componente no boletim.</td></tr>
    </table>
@endforelse

<table class="report-summary-table">
    <tr>
        <td class="summary-label">Dias letivos</td>
        <td>{{ $academicYear->schoolDayCount() }}</td>
        <td class="summary-label">Frequência anual efetiva</td>
        <td>{{ $report['annualAttendance']['percentage'] !== null ? number_format((float) $report['annualAttendance']['percentage'], 1, ',', '.').'%' : '-' }}</td>
    </tr>
    <tr>
        <td colspan="2"><strong>Total de faltas justificadas (Atestados Médicos):</strong> {{ (int) ($report['annualAttendance']['justified'] ?? 0) }}</td>
        <td colspan="2"><strong>Período letivo:</strong> {{ $currentAcademicPeriod ? 'Em andamento: '.$currentAcademicPeriod->name : 'Nenhum período em andamento' }}</td>
    </tr>
    <tr>
        <td class="summary-label">Carga horária prevista</td>
        <td>{{ $formatHoursWithUnit($plannedHoursTotal) }}</td>
        <td class="summary-label">Carga horária cumprida</td>
        <td>{{ $formatHoursWithUnit($completedHoursTotal) }}</td>
    </tr>
    <tr>
        <td class="summary-label">Data da matrícula</td>
        <td colspan="3">{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</td>
    </tr>
</table>

<section class="legend">
    <div><strong>Legenda:</strong> N (nota) · F (falta) · TF (total de faltas) · CHP (carga horária prevista) · CHC (carga horária cursada).</div>
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
