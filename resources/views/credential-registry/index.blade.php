@extends('layouts.app')
@section('title', __('Livro Digital'))
@section('page-title', __('Livro Digital'))
@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('institutional-records.index') }}" aria-label="{{ __('Registrar ato escolar') }}" title="{{ __('Registrar ato escolar') }}">
        <i class="fas fa-plus" aria-hidden="true"></i>
    </a>
@endsection
@section('content')
<p>{{ __('Selecione o livro próprio de cada ato escolar. Nos livros digitais, o Beabá atribui número e folha. Nos físicos, a secretaria transcreve a referência durante a emissão.') }} <x-school-document-regulation :articles="__('art. 5º, II')" /></p>
<livewire:screen-listing screen="credential-books" />
<details class="card sge-panel-card"><summary class="card-header">{{ __('Abrir um livro') }}</summary><div class="card-body"><p>{{ __('Uma folha será atribuída a cada registro. Confira a continuidade da numeração e formalize a adoção do livro digital. Os livros físicos anteriores devem ser conservados.') }}</p>
<form method="POST" action="{{ route('credential-registry.store') }}">@csrf<div class="form-row"><div class="form-group col-md-6"><label for="book-school">{{ __('Escola') }}</label><select class="form-control" name="school_id" id="book-school" required>@foreach($schools->reject(fn ($school) => $school->usesPhysicalRegistry()) as $school)<option value="{{ $school->id }}" @selected(old('school_id')==$school->id)>{{ $school->name }}</option>@endforeach</select></div><div class="form-group col-md-3"><label for="book-kind">{{ __('Tipo') }}</label><select class="form-control" name="kind" id="book-kind" required>@foreach(\App\Models\CredentialBook::KINDS as $value=>$label)<option value="{{ $value }}" @selected(old('kind')===$value)>{{ __($label) }}</option>@endforeach</select></div>
<div class="form-group col-md-3" id="book-modality">
    <label for="book-scope">{{ __('Modalidade do Ensino Médio') }}</label>
    <select class="form-control" id="book-scope" name="scope">
        <option value="">{{ __('Selecione') }}</option>
        @foreach(\App\Models\AcademicCourse::MODALITY_LABELS as $code => $label)
            <option value="{{ $code }}" @selected(old('scope') === $code)>{{ __($label) }}</option>
        @endforeach
    </select>
</div>
@foreach(['identifier'=>['Identificação do livro','text',old('identifier','001/'.now()->year)],'reference_year'=>['Ano civil','number',old('reference_year',now()->year)],'first_number'=>['Primeiro número de registro','number',old('first_number',1)],'first_folio'=>['Primeira folha','number',old('first_folio',1)],'opened_on'=>['Data de abertura','date',old('opened_on',now()->toDateString())]] as $field=>$definition)<div class="form-group col-md-3"><label for="book-{{ $field }}">{{ __($definition[0]) }}</label><input class="form-control" id="book-{{ $field }}" name="{{ $field }}" type="{{ $definition[1] }}" value="{{ $definition[2] }}" required @if($definition[1]==='number') min="1" max="999999999" @endif></div>@endforeach
<div class="form-group col-md-6"><label for="previous-book">{{ __('Livro anterior / último registro, se houver') }}</label><input id="previous-book" class="form-control" name="previous_book" maxlength="255" value="{{ old('previous_book') }}"></div><div class="form-group col-12"><label for="opening-term">{{ __('Termo de abertura e referência ao ato de adoção') }}</label><textarea id="opening-term" class="form-control" name="opening_term" maxlength="10000" required rows="4">{{ old('opening_term') }}</textarea></div></div><button class="btn btn-primary">{{ __('Abrir livro e registrar termo') }}</button></form></div></details>
@endsection

@push('scripts')
<script>
    const bookKind = document.getElementById('book-kind');
    const bookScope = document.getElementById('book-scope');
    const syncBookScope = () => {
        const applies = bookKind.value === 'high-school';
        bookScope.disabled = !applies;
        bookScope.required = applies;
        document.getElementById('book-modality').hidden = !applies;
    };
    bookKind.addEventListener('change', syncBookScope);
    syncBookScope();
</script>
@endpush
