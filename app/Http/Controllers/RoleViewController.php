<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleViewController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['role_view' => ['required', 'string']]);
        abort_unless(in_array($data['role_view'], $request->user()->availableViews(), true), 403);

        $request->user()->update(['preferred_role_view' => $data['role_view']]);

        return redirect()->route('dashboard');
    }
}
