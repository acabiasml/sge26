<?php

namespace App\Support;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\SchoolClassComponent;
use App\Models\SchoolClassComponentSubstitution;
use App\Models\StudentEnrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Leitura da trajetória cadastrada; datas letivas não são datas de contratação. */
final class PersonTrajectory
{
    public function build(Person $person, User $viewer, ?CarbonImmutable $today = null, ?array $restrictSchoolIds = null): array
    {
        $today ??= CarbonImmutable::now('America/Campo_Grande')->startOfDay();
        $schools = $viewer->isAdministrator() ? null : ($viewer->canManagePeople()
            ? $viewer->manageableSchoolIds() : ($viewer->person_id === $person->id ? $viewer->visibleSchoolIds() : []));
        if ($restrictSchoolIds !== null) {
            $schools = $schools === null ? $restrictSchoolIds : array_values(array_intersect($schools, $restrictSchoolIds));
        }
        $visible = fn ($query) => $query->when($schools !== null, fn ($q) => $q->whereIn('school_id', $schools));
        $roles = $person->schoolRoles()->where($visible)->with('school')->get();
        $enrollments = $person->studentEnrollments()->whereHas('schoolClass.academicYear', $visible)
            ->with(['schoolClass.academicYear.school', 'schoolClass.academicYear.periods', 'schoolClass.startsPeriod', 'schoolClass.endsPeriod',
                'courses', 'reclassifiedFrom.schoolClass.academicYear', 'periodConvalidations.period'])->get();
        $studies = $enrollments->map(function ($enrollment) use ($today, $schools): array {
            $class = $enrollment->schoolClass;
            $year = $class->academicYear;
            $start = $enrollment->enrolled_at?->toDateString();
            $end = $enrollment->attendanceEndsAt();
            $closed = $year->isClosed() || ($class->ends_at && $class->ends_at->toDateString() < $today->toDateString());
            $calendarEnd = ! $end && $enrollment->status === StudentEnrollment::STATUS_ENROLLED && $closed;
            if ($calendarEnd) {
                $end = $class->ends_at?->toDateString();
            }
            $ongoing = $enrollment->status === StudentEnrollment::STATUS_ENROLLED && ! $closed && (! $end || $end >= $today->toDateString());
            $periodStart = $start ?? $class->starts_at?->toDateString();
            $periodEnd = $end ?? $class->ends_at?->toDateString();
            $periods = $year->periods->sortBy('position')->filter(fn ($p) => $p->starts_at && $p->ends_at
                && (! $periodStart || $p->ends_at->toDateString() >= $periodStart)
                && (! $periodEnd || $p->starts_at->toDateString() <= $periodEnd)
                && (! $class->startsPeriod || $p->position >= $class->startsPeriod->position)
                && (! $class->endsPeriod || $p->position <= $class->endsPeriod->position));
            $details = [];
            if ($periods->isNotEmpty()) {
                $details[] = ($start && ($end || $ongoing) ? __('Períodos com matrícula') : __('Períodos letivos da turma')).': '.$periods->pluck('name')->join(' · ');
            }
            if ($enrollment->reclassified_at) {
                $details[] = ($enrollment->status === StudentEnrollment::STATUS_RECLASSIFIED ? __('Reclassificação efetiva em') : __('Ingresso por reclassificação em'))
                    .' '.$enrollment->reclassified_at->format('d/m/Y');
            }
            $origin = $enrollment->reclassifiedFrom;
            if ($origin && ($schools === null || in_array($origin->schoolClass->academicYear->school_id, $schools, true))) {
                $details[] = __('Turma de origem').': '.$origin->schoolClass->name;
            }
            if ($enrollment->reclassified_from_enrollment_id) {
                $credited = $enrollment->periodConvalidations->pluck('period')->filter()->unique('id')->sortBy('position')->pluck('name');
                if ($credited->isNotEmpty()) {
                    $details[] = __('Períodos aproveitados da origem').': '.$credited->join(' · ');
                }
            }
            if ($calendarEnd) {
                $details[] = __('Término conforme calendário letivo.');
            }
            $status = $calendarEnd ? __('Ano letivo encerrado') : __($enrollment->statusLabel());

            return $this->item('enrollment-'.$enrollment->id, $class->name, $enrollment->courses->pluck('name')->unique()->join(' · '),
                $year->school->name, $year->reference_year, $start, $end, $ongoing, $status, $today) + [
                    'details' => $details, 'url' => route('enrollments.report-card.show', $enrollment), 'link_label' => __('Abrir boletim')];
        });
        // Um vínculo sem matrícula permanece visível, mas não implica série cursada.
        foreach ($roles->where('role', PersonSchoolRole::ROLE_STUDENT) as $role) {
            if ($enrollments->contains(fn ($e) => $e->schoolClass->academicYear->school_id === $role->school_id)) {
                continue;
            }
            $studies->push($this->roleItem($role, $today) + ['details' => [__('Série e turma não informadas no vínculo.')], 'url' => null, 'link_label' => null]);
        }
        $work = $roles->where('role', '!=', PersonSchoolRole::ROLE_STUDENT)->map(fn ($r) => $this->roleItem($r, $today)
            + ['details' => [], 'url' => null, 'link_label' => null]);

        $relations = ['schoolClass.academicYear.school', 'schoolClass.academicYear.periods', 'schoolClass.startsPeriod', 'schoolClass.endsPeriod',
            'component.startsPeriod', 'component.endsPeriod', 'component.course.startsPeriod', 'component.course.endsPeriod'];
        $assignments = SchoolClassComponent::where('teacher_person_id', $person->id)
            ->whereHas('schoolClass.academicYear', $visible)->with($relations)->get();
        $substitutions = SchoolClassComponentSubstitution::where('substitute_teacher_person_id', $person->id)
            ->whereHas('classComponent.schoolClass.academicYear', $visible)
            ->with(array_map(fn ($r) => 'classComponent.'.$r, $relations))->get();
        $teaching = collect();
        foreach ($assignments as $assignment) {
            $teaching->push($this->teachingItem($assignment, $today));
        }
        foreach ($substitutions as $substitution) {
            $teaching->push($this->teachingItem($substitution->classComponent, $today, $substitution));
        }
        // Uma linha por turma, intervalo e modalidade; vários componentes não duplicam o tempo.
        $teaching = $teaching->groupBy(fn ($i) => implode('|', [$i['class_id'], $i['mode'], $i['start'] ?? '', $i['end'] ?? '', $i['status'], implode(',', $i['periods'])]))
            ->map(function ($items): array {
                $item = $items->first();
                $item['subtitle'] = $items->pluck('subtitle')->unique()->sort()->join(' · ');

                return $item;
            })->values();
        $sort = fn (Collection $items) => $items->sortByDesc(fn ($i) => ($i['start'] ?? ($i['year'] ? $i['year'].'-01-01' : '0000-00-00')).'|'.$i['id'])->values();
        $groups = [
            'estudos' => ['label' => __('Estudos'), 'icon' => 'fas fa-user-graduate', 'entries' => $sort($studies)],
            'trabalho' => ['label' => __('Vínculos profissionais'), 'icon' => 'fas fa-briefcase', 'entries' => $sort($work)],
            'docencia' => ['label' => __('Docência'), 'icon' => 'fas fa-chalkboard-teacher', 'entries' => $sort($teaching)],
        ];

        return ['groups' => $groups, 'default_tab' => collect($groups)->search(fn ($g) => $g['entries']->isNotEmpty()) ?: 'estudos',
            'total' => array_sum(array_map(fn ($g) => $g['entries']->count(), $groups))];
    }

    private function roleItem(PersonSchoolRole $role, CarbonImmutable $today): array
    {
        $active = $role->isActiveForDate($today);
        $future = $role->started_at && $role->started_at->toDateString() > $today->toDateString();
        $title = __($role->position ? $role->positionLabel() : $role->label());

        return $this->item('role-'.$role->id, $title, $role->position ? __($role->label()) : '',
            $role->school?->name ?? __('Atuação global'), null, $role->started_at?->toDateString(), $role->ended_at?->toDateString(),
            $active, $future ? __('Vínculo previsto') : ($active ? __('Vínculo atual') : __('Vínculo encerrado ou inativo')), $today);
    }

    private function teachingItem(SchoolClassComponent $assignment, CarbonImmutable $today, ?SchoolClassComponentSubstitution $substitution = null): array
    {
        $class = $assignment->schoolClass;
        $year = $class->academicYear;
        $component = $assignment->component;
        $course = $component->course;
        $periods = $year->periods->sortBy('position')->filter(fn ($p) => $component->isActiveInPeriod($p)
            && (! $class->startsPeriod || $p->position >= $class->startsPeriod->position)
            && (! $class->endsPeriod || $p->position <= $class->endsPeriod->position)
            && (! $course->startsPeriod || $p->position >= $course->startsPeriod->position)
            && (! $course->endsPeriod || $p->position <= $course->endsPeriod->position));
        $calendarStart = $periods->first()?->starts_at?->toDateString() ?? $class->starts_at?->toDateString();
        $calendarEnd = $periods->last()?->ends_at?->toDateString() ?? $class->ends_at?->toDateString();
        $start = $substitution?->starts_at?->toDateString() ?? $calendarStart;
        $end = $substitution ? $substitution->ends_at?->toDateString() : $calendarEnd;
        $calendarSubstitutionEnd = $substitution && ! $end && $calendarEnd && $calendarEnd < $today->toDateString();
        if ($calendarSubstitutionEnd) {
            $end = $calendarEnd;
        }
        $future = $start && $start > $today->toDateString();
        $past = $end && $end < $today->toDateString();
        $ongoing = ! $future && ! $past && ! $year->isClosed() && ($substitution || $assignment->active);
        $status = $future ? __('Previsto') : ($past || $year->isClosed() ? __('Período encerrado') : ($ongoing ? __('Em andamento') : __('Atribuição inativa')));
        $details = [($substitution ? __('Substituição') : __('Atribuição de turma'))];
        if ($calendarSubstitutionEnd) {
            $details[] = __('Término limitado ao calendário letivo; data de encerramento da substituição não informada.');
        }
        if ($periods->isNotEmpty()) {
            $activePeriods = $periods->filter(fn ($p) => (! $start || $p->ends_at?->toDateString() >= $start)
                && (! $end || $p->starts_at?->toDateString() <= $end));
            $details[] = __('Períodos letivos').': '.$activePeriods->pluck('name')->join(' · ');
        }
        $item = $this->item(($substitution ? 'substitution-'.$substitution->id : 'assignment-'.$assignment->id), $class->name,
            $component->name, $year->school->name, $year->reference_year, $start, $end, (bool) $ongoing, $status, $today);
        if (! $substitution) {
            $item['duration'] = __('Referência do calendário letivo');
        }

        return $item + ['details' => $details, 'class_id' => $class->id, 'mode' => $substitution ? 'substitute' : 'assigned',
            'periods' => $periods->modelKeys(), 'url' => route('academic-years.classes.show', [$year, $class]), 'link_label' => __('Abrir turma')];
    }

    private function item(string $id, string $title, string $subtitle, string $school, ?int $year, ?string $start, ?string $end,
        bool $ongoing, string $status, CarbonImmutable $today): array
    {
        $future = $start && $start > $today->toDateString();
        $period = ($start ? CarbonImmutable::parse($start)->format('d/m/Y') : __('Início não informado')).' — '
            .($end ? CarbonImmutable::parse($end)->format('d/m/Y') : ($ongoing ? __('até o momento') : __('término não informado')));
        $stop = $end && $end <= $today->toDateString() ? $end : ($ongoing ? $today->toDateString() : null);
        $duration = __('Duração não determinada');
        if ($future) {
            $duration = __('Ainda não iniciado');
        } elseif ($start && $stop && $start <= $stop) {
            $duration = CarbonImmutable::parse($start)->locale(app()->getLocale())->diffForHumans(
                CarbonImmutable::parse($stop)->addDay(), ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]);
        }

        return compact('id', 'title', 'subtitle', 'school', 'year', 'start', 'end', 'ongoing', 'status', 'period', 'duration');
    }
}
