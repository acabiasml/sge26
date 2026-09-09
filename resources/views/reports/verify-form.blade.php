@extends(auth()->check() ? 'layouts.app' : 'layouts.verification')

@section('title', __('Verificar autenticidade'))
@section('page-title', __('Verificar autenticidade'))

@section('content')
        <div class="card shadow mx-auto sge-narrow-card">
            <div class="card-body p-3 p-sm-5">
                <div class="text-center mb-4">
                    <img class="sge-verification-logo" src="{{ asset('brand/logo.png') }}" alt="Beabá">
                    <h1 class="h4 text-gray-900 mt-3">{{ __('Verificar documento') }}</h1>
                    <p class="text-gray-700 mb-0">{{ __('Informe o código de verificação impresso no documento.') }}</p>
                </div>

                <form method="POST" action="{{ route('documents.verify.lookup') }}">
                    @csrf
                    <div class="form-group">
                        <label for="code">{{ __('Código de verificação') }}</label>
                        <input id="code" name="code" class="form-control form-control-lg text-uppercase @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="BEABA-XXXX-XXXX-XXXX" required autofocus>
                        @error('code') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search fa-sm"></i> {{ __('Verificar autenticidade') }}
                    </button>
                </form>

                @include('reports.partials.verification-navigation', ['showNewLookup' => false])
            </div>
        </div>
@endsection
