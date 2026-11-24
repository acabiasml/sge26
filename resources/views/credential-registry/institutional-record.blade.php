@extends('layouts.app')
@section('title', __('Registrar ato escolar'))
@section('page-title', __('Registrar ato escolar'))
@section('page-actions')
    <a href="{{ route('credential-registry.index') }}" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" data-navigation-return aria-label="{{ __('Voltar aos livros') }}" title="{{ __('Voltar aos livros') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection
@section('content')
    @if($schools->isEmpty())
        <p class="text-muted">{{ __('Nenhuma escola disponível para registro.') }}</p>
    @else
        <form method="GET" action="{{ route('institutional-records.index') }}" class="card sge-panel-card mb-3">
            <div class="card-body">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-4 mb-md-0">
                        <label for="record-school">{{ __('Escola') }}</label>
                        <select class="form-control" name="school" id="record-school">
                            @foreach($schools as $option)
                                <option value="{{ $option->id }}" @selected($school->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-5 mb-md-0">
                        <label for="record-kind">{{ __('Tipo de livro') }}</label>
                        <select class="form-control" name="kind" id="record-kind">
                            @foreach($definitions as $code => $option)
                                <option value="{{ $code }}" @selected($kind === $code)>{{ __(\App\Models\CredentialBook::KINDS[$code]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <button type="submit" class="btn btn-outline-primary">{{ __('Carregar formulário') }}</button>
                    </div>
                </div>
            </div>
        </form>

        @if($kind === 'delivery' && !$event)
            <livewire:screen-listing screen="pending-deliveries" :context="['school' => $school->id]" />
        @endif
        <section class="card sge-panel-card">
            <div class="card-header"><h2 class="h6 mb-0">{{ __(\App\Models\CredentialBook::KINDS[$kind]) }} · {{ $school->name }}</h2></div>
            <div class="card-body">
                <p>{{ __($definition['notice']) }}</p>
                <p class="small text-muted">
                    <a href="https://www.iomat.mt.gov.br/portal/visualizacoes/html/15256/#e:15256/#m:1021117" target="_blank" rel="noopener">{{ __('Portaria SEDUC-MT nº 486/2018, art. 5º, VI') }}</a>
                </p>
                <form method="POST" enctype="multipart/form-data" action="{{ route('institutional-records.store', [$school, $kind]) }}">
                    @csrf
                    @include('credential-registry.book-choice', ['requiresBook' => true, 'citeDocumentRegulation' => false])
                    @if($event)
                        <input type="hidden" name="delivery_event" value="{{ $event->id }}">
                        <p>{{ __('Os dados e comprovantes abaixo vieram da entrega já arquivada.') }}
                            <a href="{{ route('credential-registry.entry', $event->registration) }}">{{ __('Abrir registro de origem') }}</a>
                        </p>
                        <dl>
                            <dt>{{ __('Data do ato') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($defaults['recorded_on'])->format('d/m/Y') }}</dd>
                            @foreach(\App\Support\InstitutionalSchoolRecord::displayFields($definition['fields'], $defaults) as $label => $value)
                                <dt>{{ __($label) }}</dt><dd>{{ __($value) }}</dd>
                            @endforeach
                        </dl>
                    @else
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="recorded-on">{{ __('Data do ato') }}</label>
                                <input id="recorded-on" class="form-control" name="recorded_on" type="date" value="{{ old('recorded_on') }}" max="{{ now('America/Cuiaba')->toDateString() }}" required>
                            </div>
                            @foreach($definition['fields'] as $key => [$label, $input])
                                <div class="form-group {{ $input === 'textarea' ? 'col-12' : 'col-md-6' }}">
                                    <label for="institutional-{{ $key }}">{{ __($label) }}</label>
                                    @if(is_array($input))
                                        <select id="institutional-{{ $key }}" class="form-control" name="{{ $key }}" required>
                                            <option value="">{{ __('Selecione') }}</option>
                                            @foreach($input as $value => $option)
                                                <option value="{{ $value }}" @selected(old($key) === $value)>{{ __($option) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($input === 'textarea')
                                        <textarea id="institutional-{{ $key }}" class="form-control" name="{{ $key }}" rows="3" maxlength="6000" required>{{ old($key) }}</textarea>
                                    @else
                                        <input id="institutional-{{ $key }}" class="form-control" name="{{ $key }}" value="{{ old($key) }}" maxlength="250" required>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="form-group">
                            <label for="institutional-original">{{ __($definition['attachment']) }} (PDF)</label>
                            <input id="institutional-original" type="file" class="form-control-file" name="original_pdf" accept="application/pdf" @required($definition['required'])>
                        </div>
                        @if($kind === 'delivery')
                            <div class="form-group">
                                <label for="institutional-authority">{{ __('Comprovação de representação, quando o recebedor for terceiro (PDF)') }}</label>
                                <input id="institutional-authority" type="file" class="form-control-file" name="authority_pdf" accept="application/pdf">
                            </div>
                        @endif
                    @endif
                    <div class="form-group">
                        <label><input type="checkbox" name="reviewed" value="1" required @checked(old('reviewed'))> {{ __('Conferi a exatidão dos dados, a autorização e as assinaturas dos comprovantes, quando exigidas.') }}</label>
                    </div>
                    <button class="btn btn-primary" @disabled(!$school->usesPhysicalRegistry() && $books->isEmpty())>{{ __('Registrar no livro') }}</button>
                </form>
            </div>
        </section>
    @endif
@endsection
