<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>{{ config('app.name', 'Beabá') }} - Entrar</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                                        <svg class="btn-google-icon" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                                            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.91c1.7-1.57 2.69-3.88 2.69-6.62z"/>
                                            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.91-2.26c-.81.54-1.84.86-3.05.86-2.34 0-4.33-1.58-5.04-3.71H.96v2.33A9 9 0 0 0 9 18z"/>
                                            <path fill="#FBBC05" d="M3.96 10.71a5.41 5.41 0 0 1 0-3.42V4.96H.96a9 9 0 0 0 0 8.08l3-2.33z"/>
                                            <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.45 3.44 1.35l2.58-2.58C13.46.9 11.42 0 9 0A9 9 0 0 0 .96 4.96l3 2.33C4.67 5.16 6.66 3.58 9 3.58z"/>
                                        </svg>
                                        <span>Entrar com Google</span>
                                    </a>

                                    <hr>

                                    <form method="POST" action="{{ route('documents.verify.lookup') }}" class="mb-3">
                                        @csrf
                                        <label class="small text-gray-700" for="verification_code">Verificar autenticidade de documento</label>
                                        <div class="input-group">
                                            <input id="verification_code" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="BEABA-XXXX-XXXX-XXXX" aria-label="Código de verificação">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-primary" type="submit">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            @error('code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>
                                    </form>

                                    <div class="text-center">
                                        <span class="small text-gray-600">Acesso institucional</span>
                                        <div class="sge-institutional-logos" aria-label="Instituições vinculadas ao sistema">
                                            <div class="sge-institutional-logo">
                                                <span>Mantenedora</span>
                                                <img src="{{ asset('brand/centro-tecnico-juvenil-de-jarudore.png') }}" alt="Centro Técnico Juvenil de Jarudore">
                                            </div>
                                            <div class="sge-institutional-logo">
                                                <span>Apoio</span>
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
    </div>

    <script src="{{ asset('template/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('template/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('template/vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('template/js/sb-admin-2.min.js') }}"></script>

</body>

</html>

