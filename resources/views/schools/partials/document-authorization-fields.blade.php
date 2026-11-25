@php
    use App\Models\School;
    use App\Support\OfficialDocumentCompliance;

    $today = now('America/Cuiaba')->toDateString();
    $matoGrosso = old('state', $school->state) === 'MT';
    $groups = ['current' => [], 'inactive' => [], 'pending' => [], 'available' => []];
    foreach (School::documentAuthorizationLabels() as $kind => $label) {
        $act = $school->document_authorizations[$kind] ?? [];
        $registered = collect($act)->contains(fn ($value) => filled($value));
        // As chaves genéricas permanecem editáveis quando já cadastradas.
        if ($kind !== 'accreditation' && isset(School::DOCUMENT_AUTHORIZATIONS[$kind])
            && ! $registered && old("document_authorizations.$kind") === null) {
            continue;
        }
        $group = match (true) {
            OfficialDocumentCompliance::isCurrentAct($act, $today) => 'current',
            $registered => 'inactive',
            old("document_authorizations.$kind") !== null, $errors->has("document_authorizations.$kind.*") => 'pending',
            default => 'available',
        };
        $groups[$group][$kind] = $label;
    }
    $headings = ['current' => __('Atos vigentes'), 'inactive' => __('Atos não vigentes')];
@endphp

@foreach ($headings as $group => $heading)
    @continue($group === 'inactive' && $groups[$group] === [])
    <details class="border rounded p-3 mb-3" @if($group === 'current' || collect(array_keys($groups[$group]))->contains(fn ($kind) => $errors->has("document_authorizations.$kind.*"))) open @endif>
        <summary class="font-weight-bold">{{ $heading }}</summary>
        <div class="mt-3">
            @forelse ($groups[$group] as $kind => $label)
                @php
                    $act = $school->document_authorizations[$kind];
                    $status = match (true) {
                        filled($act['ends_on'] ?? null) && $act['ends_on'] < $today => __('Vigência encerrada'),
                        ($act['starts_on'] ?? '') > $today || ($act['published_on'] ?? '') > $today => __('Vigência futura'),
                        default => __('Cadastro incompleto'),
                    };
                @endphp
                <details class="mb-3" @if($errors->has("document_authorizations.$kind.*")) open @endif>
                    <summary>
                        <strong>{{ __($label) }}</strong>
                        @if ($group === 'inactive')
                            <small class="text-muted">· {{ $status }}</small>
                            @if (filled($act['renewal_protocol'] ?? null))
                                <small class="text-muted">· {{ __('Renovação em andamento (SIPE nº :protocol)', ['protocol' => $act['renewal_protocol']]) }}</small>
                            @endif
                        @elseif ($kind === 'accreditation' && $matoGrosso && blank($act['ends_on'] ?? null))
                            <small class="text-muted">· {{ __('Prazo indeterminado') }}</small>
                        @endif
                        @if (filled($act['act'] ?? null))
                            <span class="d-block ml-3 small">{{ $act['act'] }}</span>
                        @endif
                    </summary>
                    @include('schools.partials.document-authorization-fieldset', ['supportsRenewal' => School::supportsDocumentAuthorizationRenewal($kind)])
                </details>
            @empty
                <p class="text-muted mb-0">{{ __('Nenhum ato vigente cadastrado.') }}</p>
            @endforelse
        </div>
    </details>
@endforeach

@if ($groups['available'] !== [] || $groups['pending'] !== [])
    <details class="border rounded p-3 mb-3" @if($groups['pending'] !== []) open @endif>
        <summary class="font-weight-bold">{{ __('Incluir ato') }}</summary>
        <div class="mt-3">
            <p class="small text-muted">{{ __('Deixe o fim da vigência em branco somente quando o ato tiver prazo indeterminado.') }}</p>
            <div id="new-school-authorizations">
                @foreach ($groups['pending'] as $kind => $label)
                    <div class="border rounded p-3 mb-3">
                        <h3 class="h6">{{ __($label) }}</h3>
                        @include('schools.partials.document-authorization-fieldset', ['act' => [], 'supportsRenewal' => School::supportsDocumentAuthorizationRenewal($kind)])
                    </div>
                @endforeach
            </div>

            @if ($groups['available'] !== [])
                <label for="new-school-authorization-kind">{{ __('Etapa ou modalidade do ato') }}</label>
                <div class="input-group">
                    <select id="new-school-authorization-kind" class="custom-select">
                        <option value="">{{ __('Selecione uma opção') }}</option>
                        @foreach ($groups['available'] as $kind => $label)
                            <option value="{{ $kind }}" @if(School::supportsDocumentAuthorizationRenewal($kind)) data-authorization-renewal @endif>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <div class="input-group-append">
                        <button type="button" id="add-school-authorization" class="btn btn-outline-primary">{{ __('Adicionar') }}</button>
                    </div>
                </div>

                <template id="school-authorization-template">
                    <div class="border rounded p-3 mb-3">
                        <h3 class="h6"></h3>
                        @include('schools.partials.document-authorization-fieldset', ['kind' => '__kind__', 'label' => '', 'act' => [], 'supportsRenewal' => true])
                    </div>
                </template>

            @endif
        </div>
    </details>
@endif

@push('scripts')
    <script>
        (() => {
            const state = document.getElementById('state');
            const updateAccreditationEndDates = (root) => {
                const permanent = state ? state.value === 'MT' : @js($matoGrosso);
                root.querySelectorAll('[data-accreditation-end-date]').forEach(field => {
                    field.hidden = permanent;
                    field.querySelector('input').disabled = permanent;
                });
            };
            state?.addEventListener('change', () => updateAccreditationEndDates(document));
            updateAccreditationEndDates(document);

            document.getElementById('add-school-authorization')?.addEventListener('click', () => {
                const select = document.getElementById('new-school-authorization-kind');
                const option = select.selectedOptions[0];
                if (!option?.value) {
                    select.focus();
                    return;
                }

                const fields = document.getElementById('school-authorization-template').content.cloneNode(true);
                fields.querySelector('h3').textContent = option.textContent;
                fields.querySelector('legend').textContent = option.textContent;
                if (!option.hasAttribute('data-authorization-renewal')) {
                    fields.querySelectorAll('[data-authorization-renewal]').forEach(field => field.remove());
                }
                if (option.value === 'accreditation') {
                    fields.querySelector('[data-authorization-end-date]').setAttribute('data-accreditation-end-date', '');
                    updateAccreditationEndDates(fields);
                }
                fields.querySelectorAll('[id], [name], [for]').forEach(field => {
                    for (const attribute of ['id', 'name', 'for']) {
                        if (field.hasAttribute(attribute)) {
                            field.setAttribute(attribute, field.getAttribute(attribute).replace('__kind__', option.value));
                        }
                    }
                });

                const firstInput = fields.querySelector('input');
                document.getElementById('new-school-authorizations').append(fields);
                option.remove();
                select.value = '';
                firstInput.focus();
            });
        })();
    </script>
@endpush
