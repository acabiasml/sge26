<input type="hidden" name="issuance_token" value="{{ old('issuance_token',(string)\Illuminate\Support\Str::uuid()) }}">
@if(isset($school) && $school->usesPhysicalRegistry())
    <fieldset class="mb-3">
        <legend class="h6">{{ __('Referência do livro físico') }}</legend>
        <p class="text-muted small">{{ __('Transcreva o livro, o número e a folha que a escola atribuiu em papel. O Beabá arquiva a referência e os comprovantes.') }}</p>
        <div class="form-row">
            @foreach(['registry_book' => __('Livro'), 'registry_number' => __('Número do registro'), 'registry_folio' => __('Folha'), 'registered_by' => __('Responsável pelo registro')] as $field => $label)
                <div class="form-group col-md-6">
                    <label for="paper-{{ $field }}">{{ $label }}</label>
                    <input id="paper-{{ $field }}" class="form-control" name="{{ $field }}" value="{{ old($field) }}" maxlength="{{ $field === 'registered_by' ? 150 : 50 }}" required>
                </div>
            @endforeach
            @unless($hasRegistrationDate ?? false)
                <div class="form-group col-md-6">
                    <label for="paper-registration-date">{{ __('Data do registro em papel') }}</label>
                    <input id="paper-registration-date" class="form-control" name="registration_date" type="date" value="{{ old('registration_date') }}" max="{{ now('America/Cuiaba')->toDateString() }}" required>
                </div>
            @endunless
        </div>
    </fieldset>
@else
<div class="form-group"><label for="registry-book-choice">{{ __('Livro do registro') }}</label>
<select class="form-control" name="registry_book_id" id="registry-book-choice" aria-describedby="registry-book-help" data-registry-book-choice @required($requiresBook ?? false)>
<option value="">{{ ($requiresBook ?? false) ? __('Selecione um livro digital aberto') : __('Livro físico com numeração informada pela escola') }}</option>
@foreach($books as $book)<option value="{{ $book->id }}" @selected((string)old('registry_book_id', $preferredBookId ?? $books->first()?->id)===(string)$book->id)>{{ $book->identifier }} · {{ __('próximo registro') }} {{ $book->next_number }} · {{ __('folha') }} {{ $book->first_folio + $book->next_number - $book->first_number }}</option>@endforeach
</select>
<p id="registry-book-help" class="small text-muted mt-1">
    @if(($requiresBook ?? false) && $books->isEmpty())
        {{ __('Abra um novo livro da escola para continuar a emissão.') }}
    @else
        {{ __('No Livro Digital, o Beabá preenche número, folha e responsável no registro oficial.') }}
        @unless($requiresBook ?? false)
            {{ __('Se a escola usa livro físico, informe os dados do registro nesse livro.') }}
        @endunless
    @endif
    @if($citeDocumentRegulation ?? true)
        <x-school-document-regulation :articles="__('art. 5º, II')" /> ·
    @endif
    <a href="{{ route('credential-registry.index') }}">{{ __('Gerenciar livros') }}</a>
</p></div>
@once
@push('scripts')
<script>
    document.querySelectorAll('[data-registry-book-choice]').forEach(select => {
        const form = select.closest('form');
        const sync = () => {
            const digital = Boolean(select.value);
            form.querySelectorAll('[data-manual-registry], [data-digital-registry]').forEach(section => {
                const visible = section.hasAttribute('data-digital-registry') === digital;
                section.hidden = !visible;
                section.querySelectorAll('input').forEach(input => {
                    input.disabled = !visible;
                    input.required = visible;
                });
            });
            form.querySelectorAll('[data-registry-evidence]').forEach(section => {
                section.hidden = !digital;
                section.querySelectorAll('input, select').forEach(input => { input.disabled = !digital; });
            });
        };
        select.addEventListener('change', sync);
        sync();
    });
</script>
@endpush
@endonce
@endif
