@extends(auth()->check() ? 'layouts.app' : 'layouts.verification')

@section('title', __('Resultado da verificação'))
@section('page-title', __('Verificar autenticidade'))

@section('content')
        <div class="card shadow mx-auto sge-narrow-card-lg">
            <div class="card-body p-3 p-sm-5">
                <div class="text-center mb-4">
                    <img class="sge-verification-logo-lg" src="{{ asset('brand/logo.png') }}" alt="Beabá">
                    <h1 class="h4 text-gray-900 mt-3">{{ __('Documento verificado') }}</h1>
                    <p class="text-gray-700 mb-0">{{ __($verification['description']) }}</p>
                </div>

                @php
                    $issuer = $document->issuedBy?->person?->full_name
                        ?? $document->issuedBy?->name
                        ?? $document->issuedBy?->email
                        ?? __('Sistema');
                @endphp

                <div class="alert alert-{{ $verification['revoked'] ? 'danger' : 'success' }}" role="alert">
                    <strong>{{ $verification['revoked'] ? __('Documento revogado') : __('Documento válido') }}.</strong>
                    @if (! $verification['revoked'])
                        {{ __('As informações abaixo conferem com um documento emitido pelo sistema.') }}
                    @endif
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Código') }}</dt>
                    <dd class="col-sm-8"><strong>{{ $document->verification_code }}</strong></dd>

                    <dt class="col-sm-4">{{ __('Documento') }}</dt>
                    <dd class="col-sm-8">{{ __($verification['type_label']) }}</dd>

                    @if ($verification['title'])
                        <dt class="col-sm-4">{{ __('Título') }}</dt>
                        <dd class="col-sm-8">{{ $verification['title'] }}</dd>
                    @endif

                    @if ($verification['school_name'])
                        <dt class="col-sm-4">{{ __('Instituição') }}</dt>
                        <dd class="col-sm-8">{{ $verification['school_name'] }}</dd>
                    @endif

                    @if ($verification['scope_label'])
                        <dt class="col-sm-4">{{ __('Abrangência') }}</dt>
                        <dd class="col-sm-8">{{ $verification['scope_label'] }}</dd>
                    @endif

                    @if ($verification['rows_count'] !== null)
                        <dt class="col-sm-4">{{ __('Registros') }}</dt>
                        <dd class="col-sm-8">{{ $verification['rows_count'] }}</dd>
                    @endif

                    <dt class="col-sm-4">{{ __('Emitido em') }}</dt>
                    <dd class="col-sm-8">
                        {{ $document->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
                        <span class="text-muted small">{{ __('(horário de Brasília)') }}</span>
                    </dd>

                    <dt class="col-sm-4">{{ __('Emitido por') }}</dt>
                    <dd class="col-sm-8">{{ $issuer }}</dd>
                </dl>

                <p class="small text-gray-600 mt-4 mb-0">
                    {{ __('Esta consulta confirma a emissão e a situação do documento. Por segurança, dados pessoais e conteúdo completo do documento não são exibidos nesta página pública.') }}
                </p>

                @include('reports.partials.verification-navigation', ['showNewLookup' => true])
            </div>
        </div>
@endsection
