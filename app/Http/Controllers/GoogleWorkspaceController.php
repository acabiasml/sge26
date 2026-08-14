<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Services\GoogleWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class GoogleWorkspaceController extends Controller
{
    public function store(Request $request, Person $person, GoogleWorkspaceService $workspace): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);

        try {
            $result = $workspace->provision($person);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        if (! $result['created']) {
            return back()->with('status', 'A conta já existe no Google Workspace e foi vinculada ao cadastro.');
        }

        return back()
            ->with('status', 'Conta criada no Google Workspace. A senha abaixo será exibida somente agora.')
            ->with('workspace_temporary_password', $result['temporary_password']);
    }
}
