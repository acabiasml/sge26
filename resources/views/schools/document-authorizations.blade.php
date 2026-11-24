@extends('layouts.app')

@section('title', __('Atos autorizativos'))
@section('page-title', __('Atos autorizativos'))

@section('content')
    <div class="card sge-panel-card shadow mb-4">
        <div class="card-body">
            <h2 class="h5">{{ $school->name }}</h2>
            <form method="POST" action="{{ route('schools.document-authorizations.update', $school) }}">
                @csrf
                @method('PUT')
                <p class="small text-muted">{{ __('Preencha os atos das etapas ofertadas, com a referência da publicação e as datas de vigência. Deixe o término em branco somente quando o ato tiver prazo indeterminado.') }}</p>
                @foreach(\App\Models\School::DOCUMENT_AUTHORIZATIONS as $kind => $label)
                    <fieldset class="mb-3">
                        <legend class="h6">{{ __($label) }}</legend>
                        <div class="form-row">
                            @foreach(['act' => __('Ato e referência do Diário Oficial'), 'published_on' => __('Publicação'), 'starts_on' => __('Início da vigência'), 'ends_on' => __('Fim da vigência')] as $field => $caption)
                                @php($fieldKey = "document_authorizations.$kind.$field")
                                <div class="form-group {{ $field === 'act' ? 'col-12 col-lg-6' : 'col-12 col-md-4 col-lg-2' }}">
                                    <label for="authorization-{{ $kind }}-{{ $field }}">{{ $caption }}</label>
                                    <input id="authorization-{{ $kind }}-{{ $field }}" name="document_authorizations[{{ $kind }}][{{ $field }}]" type="{{ $field === 'act' ? 'text' : 'date' }}" maxlength="350" class="form-control @error($fieldKey) is-invalid @enderror" value="{{ old($fieldKey, $school->document_authorizations[$kind][$field] ?? '') }}">
                                    @error($fieldKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                <button type="submit" class="btn btn-primary">{{ __('screens.save_changes') }}</button>
                <a href="{{ route('schools.edit', $school) }}#section-documentos" class="btn btn-outline-secondary">{{ __('Voltar ao cadastro da escola') }}</a>
            </form>
        </div>
    </div>
@endsection
