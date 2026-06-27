@extends('layouts.app')

@section('title', 'Pendências')
@section('page-title', 'Pendências de cadastro')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('data-quality.index') }}" class="form-inline">
                <label for="school_id" class="mr-2 mb-2">Filtrar por escola</label>
                <select id="school_id" name="school_id" class="form-control mr-2 mb-2">
                    @if (auth()->user()->isAdministrator())
                        <option value="">Todas as escolas</option>
                    @endif
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected($selectedSchoolId === $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary mb-2" type="submit">Aplicar</button>
                @if ($selectedSchoolId)
                    <a class="btn btn-outline-secondary mb-2 ml-2" href="{{ route('data-quality.index') }}">Limpar</a>
                @endif
            </form>

            @unless (auth()->user()->isAdministrator())
                <p class="small text-gray-600 mb-0">
                    Sua visualização está limitada às escolas em que você possui vínculo ativo de Gestão.
                </p>
            @endunless
        </div>
    </div>

    <div class="row">
        @foreach ([
            ['title' => 'Pessoas', 'checks' => $personChecks, 'icon' => 'fa-users'],
            ['title' => 'Vínculos', 'checks' => $roleChecks, 'icon' => 'fa-id-badge'],
            ['title' => 'Responsáveis', 'checks' => $contactChecks, 'icon' => 'fa-address-book'],
            ['title' => 'Escolas', 'checks' => $schoolChecks, 'icon' => 'fa-school'],
        ] as $group)
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">{{ $group['title'] }}</div>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">
                                    {{ number_format($group['checks']->sum('count'), 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas {{ $group['icon'] }} fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @foreach ([
        'Pessoas' => $personChecks,
        'Vínculos' => $roleChecks,
        'Responsáveis e contatos' => $contactChecks,
        'Escolas' => $schoolChecks,
    ] as $sectionTitle => $checks)
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h2 class="h6 m-0 font-weight-bold text-primary">{{ $sectionTitle }}</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach ($checks as $check)
                        <div class="col-lg-6 mb-4">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h3 class="h6 font-weight-bold text-gray-800 mb-1">{{ $check['title'] }}</h3>
                                        <p class="small text-gray-600 mb-0">{{ $check['description'] }}</p>
                                    </div>
                                    <span class="badge badge-{{ $check['severity'] }} ml-3">
                                        {{ number_format($check['count'], 0, ',', '.') }}
                                    </span>
                                </div>

                                @if ($check['items']->isNotEmpty())
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                @foreach ($check['items'] as $item)
                                                    <tr>
                                                        <td>
                                                            @if ($check['type'] === 'people')
                                                                <a href="{{ route('people.show', $item) }}">{{ $item->full_name }}</a>
                                                                <div class="small text-gray-600">{{ $item->institutional_email ?: 'Sem e-mail institucional' }}</div>
                                                            @elseif ($check['type'] === 'roles')
                                                                @if ($item->person)
                                                                    <a href="{{ route('people.show', $item->person) }}">{{ $item->person->full_name }}</a>
                                                                @else
                                                                    Pessoa não localizada
                                                                @endif
                                                                <div class="small text-gray-600">{{ $item->label() }} / {{ $item->school?->name ?? 'Global' }}</div>
                                                            @elseif ($check['type'] === 'contacts')
                                                                @if ($item->person)
                                                                    <a href="{{ route('people.show', $item->person) }}">{{ $item->name }}</a>
                                                                    <div class="small text-gray-600">Responsável de {{ $item->person->full_name }}</div>
                                                                @else
                                                                    {{ $item->name }}
                                                                    <div class="small text-gray-600">Pessoa não localizada</div>
                                                                @endif
                                                            @else
                                                                @can('manage-schools')
                                                                    <a href="{{ route('schools.edit', $item) }}">{{ $item->name }}</a>
                                                                @else
                                                                    {{ $item->name }}
                                                                @endcan
                                                                <div class="small text-gray-600">{{ trim(($item->city ?? '').' / '.($item->state ?? ''), ' /') ?: 'Sem cidade/UF' }}</div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-gray-600 mb-0 mt-3">Nenhuma pendência encontrada.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@endsection
