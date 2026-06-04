<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>{{ config('app.name', 'Beabá') }} - Entrar</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/logo.png') }}">

    <link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <link href="{{ asset('template/css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/css/sge-brand.css') }}" rel="stylesheet">
</head>

<body class="bg-gradient-primary">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-12 col-md-9">
                <div class="card o-hidden border-0 shadow-lg my-5 sge-login-card">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-6 d-none d-lg-block sge-login-image">
                                <div class="sge-login-image-inner">
                                    <img src="{{ asset('brand/logo.png') }}" alt="{{ config('app.name', 'Beabá') }}">
                                    <p class="sge-login-image-caption">Sistema de Gestão Escolar</p>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="p-5">
                                    <div class="text-center">
                                        <img class="sge-login-logo" src="{{ asset('brand/logo.png') }}" alt="{{ config('app.name', 'Beabá') }}">
                                        <h1 class="h4 text-gray-900 mb-2">Beabá</h1>
                                        <p class="sge-login-kicker mb-4">Sistema de Gestão Escolar</p>
                                    </div>

                                    @if (session('status'))
                                        <div class="alert alert-warning small" role="alert">
                                            {{ session('status') }}
                                        </div>
                                    @endif

                                    <a href="{{ route('auth.google.redirect') }}" class="btn btn-google btn-user btn-block">
                                        <i class="fab fa-google fa-fw"></i> Entrar com Google
                                    </a>

                                    <hr>

                                    <div class="text-center">
                                        <span class="small text-gray-600">Acesso institucional</span>
                                        <div class="sge-maintainer" aria-label="Mantenedora: Operação Mato Grosso">
                                            <span>Mantenedora</span>
                                            <img src="{{ asset('brand/operacao-mato-grosso.jpg') }}" alt="Operação Mato Grosso">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('template/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('template/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('template/vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('template/js/sb-admin-2.min.js') }}"></script>

</body>

</html>

