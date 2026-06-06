<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>{{ config('app.name', 'Beabá') }} - @yield('title', 'Início')</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <link href="{{ asset('template/css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/css/sge-brand.css') }}" rel="stylesheet">
    @livewireStyles
</head>

<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route('dashboard') }}">
                <div class="sidebar-brand-icon sge-sidebar-brand-mark">
                    <img src="{{ asset('brand/logo.png') }}" alt="{{ config('app.name', 'Beabá') }}" style="width: 42px; height: auto;">
                </div>
                <div class="sidebar-brand-text mx-3">Beabá</div>
            </a>

            <hr class="sidebar-divider my-0">

            <li class="nav-item">
                <a class="nav-link" href="{{ route('dashboard') }}">
                    <i class="fas fa-fw fa-home"></i>
                    <span>Início</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('documents.verify.form') }}">
                    <i class="fas fa-fw fa-certificate"></i>
                    <span>Verificar documento</span>
                </a>
            </li>

            @can('manage-schools')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('schools.index') }}">
                        <i class="fas fa-fw fa-school"></i>
                        <span>Escolas</span>
                    </a>
                </li>
            @endcan

            @can('manage-people')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('people.index') }}">
                        <i class="fas fa-fw fa-users"></i>
                        <span>Pessoas</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="{{ route('people.roles.index') }}">
                        <i class="fas fa-fw fa-id-badge"></i>
                        <span>Vínculos</span>
                    </a>
                </li>
            @endcan

            @can('manage-people')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('data-quality.index') }}">
                        <i class="fas fa-fw fa-clipboard-check"></i>
                        <span>Pendências</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="{{ route('academic-years.index') }}">
                        <i class="fas fa-fw fa-calendar-alt"></i>
                        <span>Anos letivos</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="{{ route('calendar-events.index') }}">
                        <i class="fas fa-fw fa-calendar-day"></i>
                        <span>Eventos</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="{{ route('announcements.index') }}">
                        <i class="fas fa-fw fa-bullhorn"></i>
                        <span>Recados</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="{{ route('audit-logs.index') }}">
                        <i class="fas fa-fw fa-history"></i>
                        <span>Auditoria</span>
                    </a>
                </li>
            @endcan

            <hr class="sidebar-divider d-none d-md-block">
        </ul>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <span class="nav-link">
                                @if (auth()->user()->avatar)
                                    <img class="img-profile rounded-circle mr-2" src="{{ auth()->user()->avatar }}" alt="">
                                @endif
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small text-right">
                                    <span class="d-block">{{ auth()->user()->name }}</span>
                                    <span class="d-block text-xs">{{ auth()->user()->activeRoleLabel() }}</span>
                                </span>
                            </span>
                        </li>
                    </ul>
                </nav>

                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">@yield('page-title', 'Início')</h1>
                        <div class="d-flex align-items-center">
                            @yield('page-actions')
                            <form method="POST" action="{{ route('logout') }}" class="ml-2">
                                @csrf
                                <button class="btn btn-sm btn-primary shadow-sm" type="submit">
                                    <i class="fas fa-sign-out-alt fa-sm text-white-50"></i> Sair
                                </button>
                            </form>
                        </div>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            Verifique os campos destacados e tente novamente.
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('template/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('template/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('template/vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('template/js/sb-admin-2.min.js') }}"></script>
    @livewireScripts
    <script>
        document.querySelectorAll('[data-mask="cpf"]').forEach((input) => {
            input.addEventListener('input', () => {
                const value = input.value.replace(/\D/g, '').slice(0, 11);
                input.value = value
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            });
        });

        document.querySelectorAll('[data-mask="cnpj"]').forEach((input) => {
            input.addEventListener('input', () => {
                const value = input.value.replace(/\D/g, '').slice(0, 14);
                input.value = value
                    .replace(/(\d{2})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1/$2')
                    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
            });
        });

        document.querySelectorAll('[data-mask="cep"]').forEach((input) => {
            input.addEventListener('input', () => {
                const value = input.value.replace(/\D/g, '').slice(0, 8);
                input.value = value.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
            });
        });

        document.querySelectorAll('[data-mask="phone"]').forEach((input) => {
            input.addEventListener('input', () => {
                const value = input.value.replace(/\D/g, '').slice(0, 11);

                if (value.length <= 10) {
                    input.value = value
                        .replace(/(\d{2})(\d)/, '($1) $2')
                        .replace(/(\d{4})(\d)/, '$1-$2');
                    return;
                }

                input.value = value
                    .replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{5})(\d)/, '$1-$2');
            });
        });

        const syncExportLink = (link) => {
            link.href = `${link.dataset.baseHref}${window.location.search}`;
        };

        document.querySelectorAll('.js-current-query-export').forEach((link) => {
            syncExportLink(link);
            ['click', 'mousedown', 'focus', 'mouseenter'].forEach((eventName) => {
                link.addEventListener(eventName, () => syncExportLink(link));
            });
        });

        document.querySelectorAll('[data-role-select]').forEach((roleSelect) => {
            const form = roleSelect.closest('form');
            const positionSelect = form?.querySelector('[data-manager-position]');

            if (!positionSelect) {
                return;
            }

            const syncPosition = () => {
                const isManager = roleSelect.value === @json(\App\Models\PersonSchoolRole::ROLE_MANAGER);

                positionSelect.disabled = !isManager;
                positionSelect.required = isManager;

                if (!isManager) {
                    positionSelect.value = '';
                }
            };

            roleSelect.addEventListener('change', syncPosition);
            syncPosition();
        });
    </script>
</body>

</html>
