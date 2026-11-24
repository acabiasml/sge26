<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\UserOperationFailure;
use App\Support\UserOperationFailureRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserOperationFailureController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'category' => ['nullable', Rule::in(array_keys(UserOperationFailure::CATEGORIES))],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')]]);
        $timezone = $request->user()->auditTimezone();
        $query = UserOperationFailure::with('school')
            ->when(filled($data['q'] ?? null), fn ($q) => $q->where(fn ($search) => $search->where('actor_name', 'like', '%'.$data['q'].'%')->orWhere('operation', 'like', '%'.$data['q'].'%')->orWhere('message', 'like', '%'.$data['q'].'%')->orWhere('path', 'like', '%'.$data['q'].'%')))
            ->when(filled($data['category'] ?? null), fn ($q) => $q->where('category', $data['category']))
            ->when(filled($data['school_id'] ?? null), fn ($q) => $q->where('school_id', $data['school_id']))
            ->when(filled($data['from'] ?? null), fn ($q) => $q->where('occurred_at', '>=', CarbonImmutable::parse($data['from'], $timezone)->startOfDay()->utc()))
            ->when(filled($data['to'] ?? null), fn ($q) => $q->where('occurred_at', '<=', CarbonImmutable::parse($data['to'], $timezone)->endOfDay()->utc()));

        return view('audit-logs.failures', ['failures' => $query->orderByDesc('id')->paginate(10)->withQueryString(),
            'timezone' => $timezone, 'schools' => School::orderBy('name')->get(['id', 'name']),
            'lastDayCount' => UserOperationFailure::where('occurred_at', '>=', now()->subDay())->count(),
            'totalCount' => UserOperationFailure::count()]);
    }

    public function store(Request $request, UserOperationFailureRecorder $recorder)
    {
        $data = $request->validate(['event_uuid' => ['required', 'uuid'], 'category' => ['required', Rule::in(['browser', 'network', 'validation'])],
            'operation' => ['nullable', 'string', 'max:255'], 'message' => ['required', 'string', 'max:8000'], 'reason' => ['nullable', 'string', 'max:4000'],
            'path' => ['required', 'string', 'max:1024', 'regex:~^/(?!/)[^?#]*$~'], 'method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])],
            'status_code' => ['nullable', 'integer', 'min:0', 'max:599']]);
        $record = $recorder->record($request, $data + ['channel' => 'browser', 'operation' => 'Mensagem exibida na tela']);
        if (! $record) {
            return response()->json(['message' => 'Registro de diagnóstico temporariamente indisponível.'], 503);
        }

        return response()->noContent();
    }
}
