<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documento não localizado - Beabá</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('template/css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/css/sge-brand.css') }}" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
    <main class="container py-5">
        <div class="card shadow mx-auto sge-narrow-card-lg">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <img class="sge-verification-logo-lg" src="{{ asset('brand/logo.png') }}" alt="Beabá">
                    <h1 class="h4 text-gray-900 mt-3">Documento não localizado</h1>
                    <p class="text-gray-700 mb-0">
                        Não encontramos documento emitido pelo Beabá com este código de verificação.
                    </p>
                </div>

                <div class="alert alert-warning" role="alert">
                    Confira se o código foi digitado exatamente como aparece no rodapé do documento.
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-4">Código consultado</dt>
                    <dd class="col-sm-8"><strong>{{ $code }}</strong></dd>

                    <dt class="col-sm-4">Situação</dt>
                    <dd class="col-sm-8">Não localizado nos registros públicos de verificação.</dd>
                </dl>

                <p class="small text-gray-600 mt-4 mb-0">
                    Se o código estiver correto e o problema continuar, procure a secretaria da escola que emitiu o documento.
                </p>

                <div class="text-center mt-4">
                    <a class="btn btn-outline-primary" href="https://ctjj.org/#verificar-documento">Verificar outro documento</a>
                    <a class="btn btn-primary" href="https://ctjj.org/">Voltar ao site do CTJJ</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
