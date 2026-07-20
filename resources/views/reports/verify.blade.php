<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificação de Documento - Beabá</title>
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
                    <h1 class="h4 text-gray-900 mt-3">Documento verificado</h1>
                    <p class="text-gray-700 mb-0">{{ $verification['description'] }}</p>
                </div>

                @php
                    $issuer = $document->issuedBy?->person?->full_name
                        ?? $document->issuedBy?->name
                        ?? $document->issuedBy?->email
                        ?? 'Sistema';
                @endphp

                <div class="alert alert-{{ $verification['revoked'] ? 'danger' : 'success' }}" role="alert">
                    <strong>{{ $verification['revoked'] ? 'Documento revogado' : 'Documento válido' }}.</strong>
                    @if (! $verification['revoked'])
                        As informações abaixo conferem com um documento emitido pelo sistema.
                    @endif
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-4">Código</dt>
                    <dd class="col-sm-8"><strong>{{ $document->verification_code }}</strong></dd>

                    <dt class="col-sm-4">Documento</dt>
                    <dd class="col-sm-8">{{ $verification['type_label'] }}</dd>

                    @if ($verification['title'])
                        <dt class="col-sm-4">Título</dt>
                        <dd class="col-sm-8">{{ $verification['title'] }}</dd>
                    @endif

                    @if ($verification['school_name'])
                        <dt class="col-sm-4">Instituição</dt>
                        <dd class="col-sm-8">{{ $verification['school_name'] }}</dd>
                    @endif

                    @if ($verification['scope_label'])
                        <dt class="col-sm-4">Abrangência</dt>
                        <dd class="col-sm-8">{{ $verification['scope_label'] }}</dd>
                    @endif

                    @if ($verification['rows_count'] !== null)
                        <dt class="col-sm-4">Registros</dt>
                        <dd class="col-sm-8">{{ $verification['rows_count'] }}</dd>
                    @endif

                    <dt class="col-sm-4">Emitido em</dt>
                    <dd class="col-sm-8">
                        {{ $document->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
                        <span class="text-muted small">(horário de Brasília)</span>
                    </dd>

                    <dt class="col-sm-4">Emitido por</dt>
                    <dd class="col-sm-8">{{ $issuer }}</dd>
                </dl>

                <p class="small text-gray-600 mt-4 mb-0">
                    Esta consulta confirma a emissão e a situação do documento. Por segurança, dados pessoais e conteúdo completo do documento não são exibidos nesta página pública.
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
