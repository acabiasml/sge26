<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificação de Documento - Beabá</title>
    <link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('template/css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/css/sge-brand.css') }}" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
    <main class="container py-5">
        <div class="card shadow mx-auto" style="max-width: 680px;">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <img src="{{ asset('brand/logo.png') }}" alt="Beabá" style="width: 96px;">
                    <h1 class="h4 text-gray-900 mt-3">Documento verificado</h1>
                    <p class="text-gray-700 mb-0">Este código foi emitido pelo Beabá.</p>
                </div>

                <dl class="row">
                    <dt class="col-sm-4">Código</dt>
                    <dd class="col-sm-8">{{ $document->verification_code }}</dd>
                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8">{{ $document->type }}</dd>
                    <dt class="col-sm-4">Emitido em</dt>
                    <dd class="col-sm-8">{{ $document->issued_at?->format('d/m/Y H:i:s') }}</dd>
                    <dt class="col-sm-4">Emitido por</dt>
                    <dd class="col-sm-8">{{ $document->issuedBy?->name ?? 'Sistema' }}</dd>
                    <dt class="col-sm-4">Situação</dt>
                    <dd class="col-sm-8">{{ $document->revoked_at ? 'Revogado' : 'Válido' }}</dd>
                </dl>
            </div>
        </div>
    </main>
</body>
</html>
