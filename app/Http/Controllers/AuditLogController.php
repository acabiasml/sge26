<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('audit-logs.index', [
            'auditTimezone' => $user->auditTimezone(),
            'auditTimezones' => User::AUDIT_TIMEZONES,
        ]);
    }

    public function updateTimezone(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);

        $data = $request->validate([
            'audit_timezone' => ['required', Rule::in(array_keys(User::AUDIT_TIMEZONES))],
        ]);

        $request->user()->update([
            'audit_timezone' => $data['audit_timezone'],
        ]);

        return redirect()->route('audit-logs.index')
            ->with('status', 'Fuso horário da auditoria atualizado com sucesso.');
    }

    public function show(Request $request, AuditLog $auditLog): View
    {
        $user = $request->user();

        abort_unless(
            $user->isAdministrator() || ($auditLog->school_id && $user->canManageSchool($auditLog->school_id)),
            403
        );

        return view('audit-logs.show', [
            'auditLog' => $auditLog->load(['actorUser', 'actorPerson', 'school']),
            'auditTimezone' => $user->auditTimezone(),
        ]);
    }
}
