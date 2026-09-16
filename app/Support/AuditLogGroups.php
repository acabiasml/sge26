<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class AuditLogGroups
{
    /** Group consecutive records before pagination, preserving the original audit trail. */
    public static function query(User $user): Builder
    {
        return AuditLog::query()->joinSub(self::groups($user), 'audit_groups', fn ($join) => $join->on('audit_logs.id', '=', 'audit_groups.last_id'))
            ->with(['actorUser', 'actorPerson', 'school']);
    }

    public static function groups(User $user): QueryBuilder
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $json = static fn (string $column, string $key): string => $sqlite
            ? "json_extract($column, '$.$key')"
            : "nullif(json_unquote(json_extract($column, '$.$key')), 'null')";
        $context = [];
        $bindings = [];
        foreach ([DiaryAttendanceEntry::class => ['diary_attendance_record_id', 'diary_attendance_entries'], DiaryAssessmentResult::class => ['diary_assessment_id', 'diary_assessment_results']] as $type => [$field, $table]) {
            $context[] = 'when auditable_type = ? then coalesce('.$json('metadata', 'group_context').', '.$json('new_values', $field).', '.$json('old_values', $field).", (select $field from $table where $table.id = audit_logs.auditable_id), -audit_logs.id)";
            $bindings[] = $type;
        }
        $base = DB::table('audit_logs')->select(['id', 'created_at', 'actor_user_id', 'actor_person_id', 'school_id', 'auditable_type', 'action', 'actor_role', 'actor_position'])
            ->selectRaw('case '.implode(' ', $context).' else 0 end as context_id', $bindings)
            ->when(! $user->isAdministrator(), fn (QueryBuilder $query) => $query->whereIn('school_id', $user->manageableSchoolIds()));
        $keys = ['actor_user_id', 'actor_person_id', 'school_id', 'auditable_type', 'action', 'actor_role', 'actor_position', 'context_id'];
        $keyed = DB::query()->fromSub($base, 'source')->select('id', 'created_at')
            ->selectRaw('json_array('.implode(', ', array_map(fn ($key) => "cast($key as char)", $keys)).') as group_key');
        $lagged = DB::query()->fromSub($keyed, 'keyed')->select('keyed.*')
            ->selectRaw('lag(created_at) over (order by id) as previous_time, lag(group_key) over (order by id) as previous_key');
        $gap = $sqlite ? 'abs((julianday(created_at) - julianday(previous_time)) * 86400) > 300' : 'abs(timestampdiff(second, previous_time, created_at)) > 300';
        $boundaries = DB::query()->fromSub($lagged, 'previous')->select('id')
            ->selectRaw("case when previous_time is null or $gap or group_key <> previous_key then 1 else 0 end as starts_group");
        $numbered = DB::query()->fromSub($boundaries, 'boundaries')->select('id')->selectRaw('sum(starts_group) over (order by id rows unbounded preceding) as group_number');

        return DB::query()->fromSub($numbered, 'numbered')->groupBy('group_number')
            ->selectRaw('min(id) as first_id, max(id) as last_id, count(*) as group_count');
    }

    public static function label(AuditLog $log): string
    {
        if ((int) $log->group_count < 2) {
            return AuditLogPresenter::recordLabel($log);
        }
        $field = match ($log->auditable_type) {
            DiaryAttendanceEntry::class => 'diary_attendance_record_id',
            DiaryAssessmentResult::class => 'diary_assessment_id',
            default => null,
        };
        if ($field) {
            $id = $log->metadata['group_context'] ?? $log->new_values[$field] ?? $log->old_values[$field] ?? $log->auditable?->getAttribute($field);
            if ($id) {
                return AuditLogPresenter::modelLabel($log->auditable_type).' — '.AuditLogPresenter::value($id, $field);
            }
        }

        return AuditLogPresenter::modelLabel($log->auditable_type);
    }
}
