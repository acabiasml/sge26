<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\User;

class DashboardShortcuts
{
    public static function make(User $user, array $defaults): array
    {
        $items = collect($defaults)->keyBy('url');
        if ($user->canManagePeople()) {
            foreach ([
                ['people.index', 'Pessoas', 'Cadastros e responsáveis', 'fa-users'],
                ['document-issuance.index', 'Central de emissão', 'Documentos disponíveis para seu perfil', 'fa-print'],
                ['student-histories.index', 'Históricos escolares', 'Consultar e editar históricos', 'fa-history'],
            ] as [$route, $label, $description, $icon]) {
                $url = route($route);
                $items->put($url, compact('url', 'icon') + ['label' => __($label), 'description' => __($description)]);
            }
        }
        $mapping = [
            'Person' => 'people.index', 'PersonContact' => 'people.index', 'PersonSchoolRole' => 'people.index',
            'StudentEnrollment' => 'enrollments.index', 'StudentPeriodConvalidation' => 'enrollments.index',
            'StudentAcademicHistory' => 'student-histories.index', 'StudentAcademicHistoryYear' => 'student-histories.index',
            'IssuedDocument' => 'document-issuance.index', 'OfficialDocument' => 'document-issuance.index',
            'DiaryAttendanceRecord' => 'teacher-diaries.index', 'DiaryAttendanceEntry' => 'teacher-diaries.index',
            'DiaryAssessmentResult' => 'teacher-diaries.index', 'DiaryContent' => 'teacher-diaries.index',
            'School' => 'schools.index', 'AcademicYear' => 'schools.index',
        ];
        $role = $user->person?->primaryActiveRole()?->role;
        $recent = AuditLog::query()->where('created_at', '>=', now()->subDays(90));
        $columns = ['actor_user_id', 'auditable_type', 'created_at'];
        $logs = (clone $recent)->where('actor_user_id', $user->id)->latest('id')->limit(1000)->get($columns);
        if ($role) {
            $peers = (clone $recent)->where('actor_role', $role)->where('actor_user_id', '!=', $user->id)
                ->when(! $user->isAdministrator(), fn ($q) => $q->whereIn('school_id', $user->visibleSchoolIds()))
                ->latest('id')->limit(1000)->get($columns);
            $logs = $logs->concat($peers);
        }
        $counts = $logs->map(function ($log) use ($mapping) {
            $route = $mapping[class_basename($log->auditable_type)] ?? null;
            return $route ? ['url' => route($route), 'actor' => $log->actor_user_id, 'minute' => $log->created_at->format('Y-m-d H:i')] : null;
        })->filter()->unique(fn ($item) => $item['url'].'|'.$item['actor'].'|'.$item['minute']);
        $own = $counts->where('actor', $user->id)->countBy('url');
        $peers = $counts->where('actor', '!=', $user->id)->countBy('url');
        $ranked = $items->sort(function ($a, $b) use ($own, $peers) {
            return (($own[$b['url']] ?? 0) <=> ($own[$a['url']] ?? 0))
                ?: (($peers[$b['url']] ?? 0) <=> ($peers[$a['url']] ?? 0));
        })->take(6);

        $periods = collect();
        if ($user->canManagePeople()) {
            $today = now('America/Cuiaba')->toDateString();
            $periods = AcademicYear::query()->with('school')->where('active', true)
                ->whereDate('starts_at', '<=', $today)->whereDate('ends_at', '>=', $today)
                ->when(! $user->isAdministrator(), fn ($q) => $q->whereIn('school_id', $user->manageableSchoolIds()))
                ->orderBy('school_id')->get()->map(fn ($year) => [
                    'url' => route('academic-years.periods.index', $year), 'icon' => 'fa-calendar-check',
                    'label' => __('Períodos').' · '.$year->school->name,
                    'description' => $year->name.' · '.$year->referenceYearsLabel(),
                ]);
        }

        return $periods->concat($ranked)->values()->all();
    }
}
