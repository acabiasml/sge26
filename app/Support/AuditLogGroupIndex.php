<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A disposable projection; the original audit records remain the source of truth. */
final class AuditLogGroupIndex
{
    private const REQUEST_SCOPES = 'sge.audit_index.scopes';

    public function available(): bool
    {
        $request = app()->bound('request') ? request() : null;
        if ($request?->attributes->has('sge.audit_index.available')) {
            return $request->attributes->get('sge.audit_index.available');
        }
        $available = Schema::hasTable('audit_log_group_states') && Schema::hasTable('audit_log_group_rows')
            && Schema::hasTable('audit_log_group_revision');
        $request?->attributes->set('sge.audit_index.available', $available);

        return $available;
    }

    public function scopeKey(User $user): string
    {
        $schools = $user->isAdministrator() ? null : $user->manageableSchoolIds();
        if (is_array($schools)) {
            $schools = array_values(array_unique($schools));
            sort($schools, SORT_NUMERIC);
        }

        return hash('sha256', json_encode(['version' => 1, 'schools' => $schools]));
    }

    public function ensure(User $user): ?string
    {
        // During deployment the code can arrive before its migration.
        if (! $this->available()) {
            return null;
        }

        $scope = $this->scopeKey($user);
        if (app()->bound('request') && isset(request()->attributes->get(self::REQUEST_SCOPES, [])[$scope])) {
            return $scope;
        }
        $maximum = (int) AuditLog::query()->max('id');
        $revision = (int) DB::table('audit_log_group_revision')->where('id', 1)->value('revision');
        $state = DB::table('audit_log_group_states')->where('scope_key', $scope)->first();
        if ($state && (int) $state->source_revision === $revision && (int) $state->last_log_id === $maximum) {
            $this->rememberScope($scope);

            return $scope;
        }

        DB::transaction(function () use ($scope, $user): void {
            DB::table('audit_log_group_states')->insertOrIgnore([
                'scope_key' => $scope, 'last_log_id' => 0, 'source_revision' => 0,
            ]);
            $state = DB::table('audit_log_group_states')->where('scope_key', $scope)->lockForUpdate()->first();
            $maximum = (int) AuditLog::query()->max('id');
            $revision = (int) DB::table('audit_log_group_revision')->where('id', 1)->value('revision');
            if ((int) $state->source_revision === $revision && (int) $state->last_log_id === $maximum) {
                return;
            }

            $rows = DB::table('audit_log_group_rows')->where('scope_key', $scope);
            $rebuild = (int) $state->source_revision !== $revision || (int) $state->last_log_id > $maximum;
            $lastGroup = $rebuild ? null : (clone $rows)->orderByDesc('last_id')->first();
            // Include the final group: new records may continue it across the checkpoint.
            $fromId = $lastGroup ? (int) $lastGroup->first_id : null;
            if ($lastGroup) {
                (clone $rows)->where('first_id', $lastGroup->first_id)->delete();
            } else {
                $rows->delete();
            }

            $groups = DB::query()->fromSub(AuditLogGroups::groups($user, $fromId, $maximum), 'regrouped')
                ->selectRaw('? as scope_key, first_id, last_id, group_count', [$scope]);
            DB::table('audit_log_group_rows')->insertUsing(['scope_key', 'first_id', 'last_id', 'group_count'], $groups);
            DB::table('audit_log_group_states')->where('scope_key', $scope)
                ->update(['last_log_id' => $maximum, 'source_revision' => $revision]);
        }, 3);
        $this->rememberScope($scope);

        return $scope;
    }

    public function invalidate(): void
    {
        self::forgetRequest();
        if ($this->available()) {
            // Writers never lock scope checkpoints: readers can rebuild without
            // reversing the lock order of a transaction that edits source records.
            DB::table('audit_log_group_revision')->where('id', 1)->increment('revision');
        }
    }

    public static function forgetRequest(): void
    {
        if (app()->bound('request')) {
            request()->attributes->remove(self::REQUEST_SCOPES);
        }
    }

    private function rememberScope(string $scope): void
    {
        if (app()->bound('request')) {
            $scopes = request()->attributes->get(self::REQUEST_SCOPES, []);
            $scopes[$scope] = true;
            request()->attributes->set(self::REQUEST_SCOPES, $scopes);
        }
    }
}
