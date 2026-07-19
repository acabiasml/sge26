<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificar Documento - Beabá</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link href="{{ asset('template/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('template/css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/css/sge-brand.css') }}" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
    <main class="container py-5">
        <div class="card shadow mx-auto sge-narrow-card">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <img class="sge-verification-logo" src="{{ asset('brand/logo.png') }}" alt="Beabá">
                    <h1 class="h4 text-gray-900 mt-3">Verificar documento</h1>
                    <p class="text-gray-700 mb-0">Informe o código de verificação impresso no documento.</p>
                </div>

                <form method="POST" action="{{ route('documents.verify.lookup') }}">
                    @csrf
                    <div class="form-group">
                        <label for="code">Código de verificação</label>
                        <input id="code" name="code" class="form-control form-control-lg text-uppercase @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="BEABA-XXXX-XXXX-XXXX" required autofocus>
                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search fa-sm"></i> Verificar autenticidade
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a class="small" href="https://ctjj.org/#verificar-documento">Voltar ao verificador no site do CTJJ</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
