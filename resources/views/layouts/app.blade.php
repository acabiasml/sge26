<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>{{ config('app.name', 'Beabá') }} - @yield('title', 'Início')</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link rel="preload" href="{{ asset('template/fonts/atkinson-hyperlegible-next/atkinson-hyperlegible-next-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link href="{{ asset('template/css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/rappasoft/livewire-tables/css/laravel-livewire-tables.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/rappasoft/livewire-tables/css/laravel-livewire-tables-thirdparty.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/css/sge-brand.css') }}?v={{ filemtime(public_path('template/css/sge-brand.css')) }}" rel="stylesheet">
    @livewireStyles
</head>

<body id="page-top">
    <a class="sge-skip-link" href="#main-content">Ir para o conteúdo</a>
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar" aria-label="Menu principal">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route('dashboard') }}">
                <div class="sidebar-brand-icon sge-sidebar-brand-mark">
                    <img class="sge-brand-logo-sm" src="{{ asset('brand/logo.png') }}" alt="{{ config('app.name', 'Beabá') }}">
                </div>
                <div class="sidebar-brand-text mx-3">Beabá</div>
            </a>

            <hr class="sidebar-divider my-0">

            @php
                $currentUser = auth()->user();
                $canManageSchools = $currentUser->canManageSchools();
                $canManagePeople = $currentUser->canManagePeople();
                $hasTeachingArea = $currentUser->hasActiveRole(\App\Models\PersonSchoolRole::ROLE_TEACHER) || $currentUser->hasTeachingDiaries();
                $hasStudentArea = $currentUser->hasStudentMap();
                $routePerson = request()->route('person');
                $routePersonId = $routePerson instanceof \App\Models\Person ? $routePerson->id : (is_numeric($routePerson) ? (int) $routePerson : null);
                $studentLifeActive = request()->routeIs('people.student-map.show') || request()->routeIs('people.histories.*');
                $ownStudentLifeActive = $studentLifeActive && $routePersonId === $currentUser->person_id;
                $peopleRegistryActive = request()->routeIs('people.index')
                    || request()->routeIs('people.create')
                    || request()->routeIs('people.store')
                    || request()->routeIs('people.show')
                    || request()->routeIs('people.edit')
                    || request()->routeIs('people.update')
                    || request()->routeIs('people.contacts.*')
                    || request()->routeIs('people.roles.*');
                $personalMenuActive = request()->routeIs('profile.*') || request()->routeIs('student-diaries.*') || $ownStudentLifeActive || request()->routeIs('teacher-schedules.*');
                $schoolManagementActive = request()->routeIs('schools.*') || request()->routeIs('academic-years.*') || $peopleRegistryActive || request()->routeIs('data-quality.*');
                $academicRoutineActive = request()->routeIs('enrollments.*') || request()->routeIs('classes.enrollments.*') || request()->routeIs('attendance-justifications.*') || request()->routeIs('teacher-diaries.*') || ($studentLifeActive && ! $ownStudentLifeActive);
                $documentsMenuActive = request()->routeIs('document-issuance.*') || request()->routeIs('official-documents.*') || request()->routeIs('documents.verify.*');
            @endphp

            <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('dashboard') }}">
                    <i class="fas fa-fw fa-home" aria-hidden="true"></i>
                    <span>Início</span>
                </a>
            </li>

            <div class="sidebar-heading">Meu espaço</div>

            <li class="nav-item {{ $personalMenuActive ? 'active' : '' }}">
                <a class="nav-link {{ $personalMenuActive ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapsePersonal"
                    aria-expanded="{{ $personalMenuActive ? 'true' : 'false' }}" aria-controls="collapsePersonal">
                    <i class="fas fa-fw fa-user-circle" aria-hidden="true"></i>
                    <span>Minha área</span>
                </a>
                <div id="collapsePersonal" class="collapse {{ $personalMenuActive ? 'show' : '' }}" aria-labelledby="headingPersonal" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                            <i class="fas fa-id-card" aria-hidden="true"></i>
                            <span>Meu cadastro</span>
                        </a>
                        <a class="collapse-item" href="{{ route('dashboard') }}#calendar-heading">
                            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                            <span>Calendário escolar</span>
                        </a>
                        @if ($hasTeachingArea)
                            <a class="collapse-item {{ request()->routeIs('teacher-schedules.*') ? 'active' : '' }}" href="{{ route('teacher-schedules.index') }}">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                                <span>Meus horários</span>
                            </a>
                        @endif
                        @if ($hasStudentArea)
                            <a class="collapse-item {{ request()->routeIs('student-diaries.*') ? 'active' : '' }}" href="{{ route('student-diaries.index') }}">
                                <i class="fas fa-book-reader" aria-hidden="true"></i>
                                <span>Meu diário</span>
                            </a>
                            <a class="collapse-item {{ $ownStudentLifeActive ? 'active' : '' }}" href="{{ route('people.student-map.show', $currentUser->person_id) }}">
                                <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
                                <span>Minha vida escolar</span>
                            </a>
                        @endif
                    </div>
                </div>
            </li>

            @if ($canManageSchools || $canManagePeople)
                <div class="sidebar-heading">Gestão escolar</div>

                <li class="nav-item {{ $schoolManagementActive ? 'active' : '' }}">
                    <a class="nav-link {{ $schoolManagementActive ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseSchoolManagement"
                        aria-expanded="{{ $schoolManagementActive ? 'true' : 'false' }}" aria-controls="collapseSchoolManagement">
                        <i class="fas fa-fw fa-school" aria-hidden="true"></i>
                        <span>Cadastros básicos</span>
                    </a>
                    <div id="collapseSchoolManagement" class="collapse {{ $schoolManagementActive ? 'show' : '' }}" aria-labelledby="headingSchoolManagement" data-parent="#accordionSidebar">
                        <div class="bg-white py-2 collapse-inner rounded">
                            @if ($canManageSchools)
                                <a class="collapse-item {{ request()->routeIs('schools.*') || request()->routeIs('academic-years.*') ? 'active' : '' }}" href="{{ route('schools.index') }}">
                                    <i class="fas fa-building" aria-hidden="true"></i>
                                    <span>Escolas e anos letivos</span>
                                </a>
                            @endif
                            @if ($canManagePeople)
                                <a class="collapse-item {{ $peopleRegistryActive ? 'active' : '' }}" href="{{ route('people.index') }}">
                                    <i class="fas fa-users" aria-hidden="true"></i>
                                    <span>Pessoas</span>
                                </a>
                                <a class="collapse-item {{ request()->routeIs('data-quality.*') ? 'active' : '' }}" href="{{ route('data-quality.index') }}">
                                    <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                                    <span>Conformidade</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </li>
            @endif

            @if ($hasTeachingArea && ! $canManagePeople)
                <li class="nav-item {{ request()->routeIs('teacher-diaries.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('teacher-diaries.index') }}">
                        <i class="fas fa-fw fa-book" aria-hidden="true"></i>
                        <span>Diários</span>
                    </a>
                </li>
            @endif

            @if ($canManagePeople)
                <div class="sidebar-heading">Rotina acadêmica</div>

                <li class="nav-item {{ $academicRoutineActive ? 'active' : '' }}">
                    <a class="nav-link {{ $academicRoutineActive ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseAcademicRoutine"
                        aria-expanded="{{ $academicRoutineActive ? 'true' : 'false' }}" aria-controls="collapseAcademicRoutine">
                        <i class="fas fa-fw fa-book-open" aria-hidden="true"></i>
                        <span>Rotina acadêmica</span>
                    </a>
                    <div id="collapseAcademicRoutine" class="collapse {{ $academicRoutineActive ? 'show' : '' }}" aria-labelledby="headingAcademicRoutine" data-parent="#accordionSidebar">
                        <div class="bg-white py-2 collapse-inner rounded">
                            @if ($canManagePeople)
                                <a class="collapse-item {{ request()->routeIs('enrollments.*') || request()->routeIs('classes.enrollments.*') ? 'active' : '' }}" href="{{ route('enrollments.index') }}">
                                    <i class="fas fa-user-graduate" aria-hidden="true"></i>
                                    <span>Matrículas</span>
                                </a>
                                @if ($studentLifeActive && ! $ownStudentLifeActive && request()->route('person'))
                                    <a class="collapse-item active" href="{{ route('people.student-map.show', request()->route('person')) }}">
                                        <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
                                        <span>Vida escolar</span>
                                    </a>
                                @endif
                                <a class="collapse-item {{ request()->routeIs('attendance-justifications.*') ? 'active' : '' }}" href="{{ route('attendance-justifications.index') }}">
                                    <i class="fas fa-notes-medical" aria-hidden="true"></i>
                                    <span>Justificativas de ausência</span>
                                </a>
                            @endif
                            <a class="collapse-item {{ request()->routeIs('teacher-diaries.*') ? 'active' : '' }}" href="{{ route('teacher-diaries.index') }}">
                                <i class="fas fa-book" aria-hidden="true"></i>
                                <span>Gestão dos diários</span>
                            </a>
                        </div>
                    </div>
                </li>
            @endif

            <div class="sidebar-heading">Documentos</div>

            <li class="nav-item {{ $documentsMenuActive ? 'active' : '' }}">
                <a class="nav-link {{ $documentsMenuActive ? '' : 'collapsed' }}" href="#" data-toggle="collapse" data-target="#collapseDocuments"
                    aria-expanded="{{ $documentsMenuActive ? 'true' : 'false' }}" aria-controls="collapseDocuments">
                    <i class="fas fa-fw fa-file-alt" aria-hidden="true"></i>
                    <span>Documentos</span>
                </a>
                <div id="collapseDocuments" class="collapse {{ $documentsMenuActive ? 'show' : '' }}" aria-labelledby="headingDocuments" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        @if ($canManagePeople)
                            <a class="collapse-item {{ request()->routeIs('document-issuance.*') ? 'active' : '' }}" href="{{ route('document-issuance.index') }}">
                                <i class="fas fa-print" aria-hidden="true"></i>
                                <span>Central de emissão</span>
                            </a>
                            <a class="collapse-item {{ request()->routeIs('official-documents.*') ? 'active' : '' }}" href="{{ route('official-documents.create') }}">
                                <i class="fas fa-file-signature" aria-hidden="true"></i>
                                <span>Editor de documentos</span>
                            </a>
                        @endif
                        <a class="collapse-item {{ request()->routeIs('documents.verify.*') ? 'active' : '' }}" href="{{ route('documents.verify.form') }}">
                            <i class="fas fa-certificate" aria-hidden="true"></i>
                            <span>Verificar autenticidade</span>
                        </a>
                    </div>
                </div>
            </li>

            @if ($canManagePeople)
                <div class="sidebar-heading">Comunicação</div>

                <li class="nav-item {{ request()->routeIs('announcements.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('announcements.index') }}">
                        <i class="fas fa-fw fa-bullhorn" aria-hidden="true"></i>
                        <span>Recados</span>
                    </a>
                </li>
            @endif

            @if ($currentUser->isAdministrator())
                <div class="sidebar-heading">Administração</div>

                <li class="nav-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('audit-logs.index') }}">
                        <i class="fas fa-fw fa-history" aria-hidden="true"></i>
                        <span>Auditoria</span>
                    </a>
                </li>
            @endif

            <hr class="sidebar-divider d-none d-md-block">
        </ul>

        <button class="sge-sidebar-backdrop d-lg-none" type="button" aria-label="Fechar menu lateral" hidden></button>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top" aria-label="Barra superior">
                    <button id="sidebarToggleTop" class="btn btn-link d-lg-none rounded-circle mr-3 sge-sidebar-toggle" type="button" aria-label="Abrir ou recolher menu lateral">
                        <i class="fa fa-bars" aria-hidden="true"></i>
                    </button>

                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle sge-topbar-icon-button" href="#" id="alertsDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Abrir alertas" title="Alertas">
                                <i class="fas fa-bell fa-fw" aria-hidden="true"></i>
                                @if ($topbarAlertCount > 0)
                                    <span class="badge badge-danger badge-counter">{{ $topbarAlertCount }}</span>
                                @endif
                            </a>
                            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in sge-alert-dropdown"
                                aria-labelledby="alertsDropdown">
                                <h2 class="dropdown-header">Alertas</h2>

                                @foreach ($topbarAnnouncements as $announcement)
                                    <a class="dropdown-item d-flex align-items-start" href="{{ route('dashboard') }}">
                                        <div class="mr-3">
                                            <div class="icon-circle bg-primary">
                                                <i class="fas fa-bullhorn text-white" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="small text-gray-600">{{ $announcement->school?->name ?? 'Global' }}</div>
                                            <span class="font-weight-bold">{{ $announcement->title }}</span>
                                            @if ($announcement->highlight)
                                                <span class="badge badge-warning ml-1">Destaque</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach

                                @foreach ($topbarDiaryAlerts as $alert)
                                    <a class="dropdown-item d-flex align-items-start" href="{{ route('teacher-diaries.show', [$alert->schoolClass, $alert->component, 'period' => $alert->academic_period_id]) }}">
                                        <div class="mr-3">
                                            <div class="icon-circle bg-warning">
                                                <i class="fas fa-book text-white" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="small text-gray-600">
                                                {{ $alert->schoolClass?->name }} · {{ $alert->period?->name }}
                                            </div>
                                            <span class="font-weight-bold">Alerta da gestão em {{ $alert->component?->name }}</span>
                                            <div class="small text-gray-700">{{ \Illuminate\Support\Str::limit($alert->message, 90) }}</div>
                                        </div>
                                    </a>
                                @endforeach

                                @if ($topbarAnnouncements->isEmpty() && $topbarDiaryAlerts->isEmpty())
                                    <div class="dropdown-item text-center small text-gray-600">Nenhum recado ativo.</div>
                                @endif
                            </div>
                        </li>

                        <li class="topbar-divider d-none d-sm-block" aria-hidden="true"></li>

                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Abrir menu do usuário" title="Menu do usuário">
                                @if (auth()->user()->avatar)
                                    <img class="img-profile rounded-circle mr-2" src="{{ auth()->user()->avatar }}" alt="">
                                @else
                                    <span class="sge-user-initial mr-2">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                                @endif
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small text-right">
                                    <span class="d-block">{{ auth()->user()->name }}</span>
                                    <span class="d-block text-xs">{{ auth()->user()->activeRoleLabel() }}</span>
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-500" aria-hidden="true"></i>
                                    Meu cadastro
                                </a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit">
                                        <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-500" aria-hidden="true"></i>
                                        Sair
                                    </button>
                                </form>
                            </div>
                        </li>
                    </ul>
                </nav>

                <main id="main-content" class="container-fluid" tabindex="-1">
                    <div class="sge-page-header d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <div class="sge-page-kicker">Beabá</div>
                            <h1 class="h3 mb-0 text-gray-800">@yield('page-title', 'Início')</h1>
                        </div>
                        <div class="d-flex align-items-center sge-page-actions">
                            @yield('page-actions')
                        </div>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success" role="status" aria-live="polite">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger sge-validation-summary" role="alert" tabindex="-1" data-validation-summary>
                            <strong>Não foi possível concluir esta ação.</strong>
                            <span>Confira os campos indicados:</span>
                            <ul class="mb-0 mt-2 pl-4">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    <script src="{{ asset('template/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('template/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('template/vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('template/js/sb-admin-2.min.js') }}"></script>
    <script src="{{ asset('vendor/rappasoft/livewire-tables/js/laravel-livewire-tables.min.js') }}"></script>
    <script src="{{ asset('vendor/rappasoft/livewire-tables/js/laravel-livewire-tables-thirdparty.min.js') }}"></script>
    @livewireScripts(['url' => asset('livewire/livewire.min.js')])
    <script>
        const inputMasks = {
            cpf(value) {
                return value.replace(/\D/g, '').slice(0, 11)
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            },
            cnpj(value) {
                return value.replace(/\D/g, '').slice(0, 14)
                    .replace(/(\d{2})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1/$2')
                    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
            },
            cep(value) {
                return value.replace(/\D/g, '').slice(0, 8)
                    .replace(/(\d{5})(\d{1,3})$/, '$1-$2');
            },
            phone(value) {
                const digits = value.replace(/\D/g, '').slice(0, 11);

                if (digits.length <= 10) {
                    return digits
                        .replace(/(\d{2})(\d)/, '($1) $2')
                        .replace(/(\d{4})(\d)/, '$1-$2');
                }

                return digits
                    .replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{5})(\d)/, '$1-$2');
            },
            email(value) {
                return value.replace(/\s+/g, '').toLowerCase();
            },
            digits(value, input) {
                const limit = Number(input.dataset.maskMax || input.maxLength || 0);
                const digits = value.replace(/\D/g, '');

                return limit > 0 ? digits.slice(0, limit) : digits;
            },
            year(value) {
                return value.replace(/\D/g, '').slice(0, 4);
            },
            decimal(value) {
                const normalized = value.replace(/[^\d,.]/g, '').replace(/\./g, ',');
                const parts = normalized.split(',');

                if (parts.length === 1) {
                    return parts[0];
                }

                return `${parts.shift()},${parts.join('').slice(0, 2)}`;
            },
            percentage(value) {
                const digits = value.replace(/\D/g, '').slice(0, 3);
                const number = Math.min(Number(digits || 0), 100);

                return digits === '' ? '' : String(number);
            },
        };

        const applyInputMask = (input) => {
            const maskName = input.dataset.mask || (input.type === 'email' ? 'email' : null);
            const mask = maskName ? inputMasks[maskName] : null;

            if (!mask) {
                return;
            }

            const cursorAtEnd = input.selectionStart === input.value.length;
            input.value = mask(input.value, input);

            if (cursorAtEnd && typeof input.setSelectionRange === 'function') {
                try {
                    input.setSelectionRange(input.value.length, input.value.length);
                } catch (error) {
                }
            }
        };

        document.querySelectorAll('[data-mask], input[type="email"]').forEach(applyInputMask);

        document.addEventListener('input', (event) => {
            if (event.target instanceof HTMLInputElement && (event.target.dataset.mask || event.target.type === 'email')) {
                applyInputMask(event.target);
            }
        });

        let generatedFieldId = 0;
        const connectFormLabels = (scope = document) => {
            const groups = [];

            if (scope instanceof Element && scope.matches('.form-group')) {
                groups.push(scope);
            }

            scope.querySelectorAll?.('.form-group').forEach((group) => groups.push(group));

            groups.forEach((group) => {
                const controls = Array.from(group.querySelectorAll('input:not([type="hidden"]), select, textarea'))
                    .filter((control) => control.closest('.form-group') === group);
                const label = group.querySelector('label');

                if (controls.length !== 1 || !label || label.contains(controls[0])) {
                    return;
                }

                const control = controls[0];
                const currentIdIsUnique = control.id && document.getElementById(control.id) === control;

                if (!currentIdIsUnique) {
                    generatedFieldId += 1;
                    control.id = `sge-field-${generatedFieldId}`;
                    control.dataset.sgeGeneratedId = 'true';
                }

                label.htmlFor = control.id;
            });
        };

        connectFormLabels();

        const formLabelObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof Element) {
                        connectFormLabels(node);
                    }
                });
            });
        });

        formLabelObserver.observe(document.getElementById('main-content'), {
            childList: true,
            subtree: true,
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

        const resetSubmitLoading = (targetForm = null) => {
            document.querySelector('.sge-submit-loading-bar')?.remove();

            const forms = targetForm ? [targetForm] : document.querySelectorAll('form[data-processing="true"]');

            forms.forEach((form) => {
                form.dataset.processing = 'false';
                form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                    button.disabled = false;
                    button.classList.remove('is-processing');
                    button.removeAttribute('aria-busy');

                    if (!button.dataset.originalHtml) {
                        return;
                    }

                    if (button.tagName === 'BUTTON') {
                        button.innerHTML = button.dataset.originalHtml;
                    } else {
                        button.value = button.dataset.originalHtml;
                    }
                });
            });
        };

        const showSubmitLoading = (form, submitter) => {
            if (form.dataset.noLoading === 'true' || form.dataset.processing === 'true') {
                return;
            }

            form.dataset.processing = 'true';

            const button = submitter?.matches?.('button, input[type="submit"]')
                ? submitter
                : form.querySelector('button[type="submit"], input[type="submit"]');

            if (button) {
                button.dataset.originalHtml = button.tagName === 'BUTTON' ? button.innerHTML : button.value;
                button.classList.add('is-processing');
                button.setAttribute('aria-busy', 'true');
                button.disabled = true;

                const loadingLabel = button.dataset.loadingLabel || 'Processando...';

                if (button.tagName === 'BUTTON') {
                    button.innerHTML = `<span class="sge-button-spinner" aria-hidden="true"></span>${loadingLabel}`;
                } else {
                    button.value = loadingLabel;
                }
            }

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((otherButton) => {
                if (otherButton !== button) {
                    otherButton.disabled = true;
                }
            });

            if (!document.querySelector('.sge-submit-loading-bar')) {
                const bar = document.createElement('div');
                bar.className = 'sge-submit-loading-bar';
                bar.setAttribute('role', 'progressbar');
                bar.setAttribute('aria-label', 'Solicitação em processamento');
                document.body.appendChild(bar);
            }

            if (form.dataset.downloadForm === 'true') {
                window.setTimeout(() => resetSubmitLoading(form), 4500);
            }
        };

        const isLivewireForm = (form) => Array.from(form.attributes)
            .some((attribute) => attribute.name.startsWith('wire:submit'));

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented || isLivewireForm(form)) {
                    return;
                }

                showSubmitLoading(form, event.submitter);
            });
        });

        document.querySelectorAll('.nav-item.active > .nav-link, .collapse-item.active').forEach((link) => {
            link.setAttribute('aria-current', 'page');
        });

        const mobileMenuButton = document.getElementById('sidebarToggleTop');
        const mobileMenuBackdrop = document.querySelector('.sge-sidebar-backdrop');
        const sidebar = document.getElementById('accordionSidebar');

        const setMobileMenuOpen = (open) => {
            const isMobileViewport = window.matchMedia('(max-width: 991.98px)').matches;

            if (!open && isMobileViewport && sidebar?.contains(document.activeElement)) {
                mobileMenuButton?.focus();
            }

            document.body.classList.toggle('sge-mobile-menu-open', open);
            mobileMenuButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
            sidebar?.toggleAttribute('inert', isMobileViewport && !open);

            if (mobileMenuBackdrop) {
                mobileMenuBackdrop.hidden = !open;
            }

            if (open && isMobileViewport) {
                window.requestAnimationFrame(() => {
                    sidebar?.querySelector('.nav-link[href]')?.focus();
                });
            }
        };

        mobileMenuButton?.setAttribute('aria-controls', 'accordionSidebar');
        mobileMenuButton?.setAttribute('aria-expanded', 'false');
        mobileMenuButton?.addEventListener('click', () => {
            setMobileMenuOpen(!document.body.classList.contains('sge-mobile-menu-open'));
        });
        mobileMenuBackdrop?.addEventListener('click', () => setMobileMenuOpen(false));
        sidebar?.querySelectorAll('a[href]').forEach((link) => {
            if (link.getAttribute('href')?.startsWith('#')) {
                return;
            }

            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 991.98px)').matches) {
                    setMobileMenuOpen(false);
                }
            });
        });

        const mobileViewport = window.matchMedia('(max-width: 991.98px)');
        const handleMobileViewportChange = (event) => {
            if (!event.matches) {
                setMobileMenuOpen(false);
            }
        };

        if (typeof mobileViewport.addEventListener === 'function') {
            mobileViewport.addEventListener('change', handleMobileViewportChange);
        } else {
            mobileViewport.addListener(handleMobileViewportChange);
        }

        setMobileMenuOpen(false);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.body.classList.contains('sge-mobile-menu-open')) {
                setMobileMenuOpen(false);
                mobileMenuButton?.focus();
            }
        });

        const syncResponsiveTableHints = () => {
            document.querySelectorAll('.table-responsive').forEach((container) => {
                const visible = container.offsetParent !== null && container.getBoundingClientRect().height > 0;
                const needsHorizontalScroll = visible && container.scrollWidth > container.clientWidth + 2;
                const currentHint = container.previousElementSibling?.matches('[data-table-scroll-hint]')
                    ? container.previousElementSibling
                    : null;

                container.classList.toggle('sge-has-horizontal-scroll', needsHorizontalScroll);

                if (needsHorizontalScroll && !currentHint) {
                    const hint = document.createElement('p');
                    hint.className = 'sge-table-scroll-hint';
                    hint.dataset.tableScrollHint = '';
                    hint.innerHTML = '<i class="fas fa-arrows-alt-h" aria-hidden="true"></i><span>Deslize a tabela para o lado para ver todas as colunas.</span>';
                    container.before(hint);
                } else if (!needsHorizontalScroll && currentHint) {
                    currentHint.remove();
                }
            });
        };

        let tableHintFrame = null;
        const scheduleResponsiveTableHints = () => {
            if (tableHintFrame !== null) {
                window.cancelAnimationFrame(tableHintFrame);
            }

            tableHintFrame = window.requestAnimationFrame(() => {
                tableHintFrame = null;
                syncResponsiveTableHints();
            });
        };

        window.addEventListener('resize', scheduleResponsiveTableHints);
        new MutationObserver(scheduleResponsiveTableHints).observe(document.getElementById('content') ?? document.body, {
            childList: true,
            subtree: true,
        });
        scheduleResponsiveTableHints();

        const validationSummary = document.querySelector('[data-validation-summary]');

        if (validationSummary) {
            validationSummary.focus();
        }

        window.addEventListener('pageshow', () => resetSubmitLoading());
    </script>
    @stack('scripts')
</body>

</html>
