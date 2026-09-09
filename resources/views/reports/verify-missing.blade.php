@extends(auth()->check() ? 'layouts.app' : 'layouts.verification')

@section('title', __('Documento não localizado'))
@section('page-title', __('Verificar autenticidade'))

@section('content')
        <div class="card shadow mx-auto sge-narrow-card-lg">
            <div class="card-body p-3 p-sm-5">
                <div class="text-center mb-4">
                    <img class="sge-verification-logo-lg" src="{{ asset('brand/logo.png') }}" alt="Beabá">
                    <h1 class="h4 text-gray-900 mt-3">{{ __('Documento não localizado') }}</h1>
                    <p class="text-gray-700 mb-0">
                        {{ __('Não encontramos documento emitido pelo Beabá com este código de verificação.') }}
                    </p>
                </div>

                <div class="alert alert-warning" role="alert">
                    {{ __('Confira se o código foi digitado exatamente como aparece no rodapé do documento.') }}
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Código consultado') }}</dt>
                    <dd class="col-sm-8"><strong>{{ $code }}</strong></dd>

                    <dt class="col-sm-4">{{ __('Situação') }}</dt>
                    <dd class="col-sm-8">{{ __('Não localizado nos registros públicos de verificação.') }}</dd>
                </dl>

                <p class="small text-gray-600 mt-4 mb-0">
                    {{ __('Se o código estiver correto e o problema continuar, procure a secretaria da escola que emitiu o documento.') }}
                </p>

                @include('reports.partials.verification-navigation', ['showNewLookup' => true])
            </div>
        </div>
@endsection
