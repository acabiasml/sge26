@extends('layouts.app')

@section('title', __('Documentos oficiais'))
@section('page-title', __('Documentos oficiais'))

@section('content')
    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ $sourceDocument ? __('Reeditar documento em papel timbrado') : __('Novo documento em papel timbrado') }}</h2>
                </div>
                <div class="card-body">
                    @if ($sourceDocument)
                        <div class="alert alert-info">{{ __('Você está reeditando “') }}{{ $sourceDocument->title }}{{ __('”. A emissão criará um novo documento e um novo código de autenticidade.') }}</div>
                    @endif
                    <form method="POST" action="{{ route('official-documents.store') }}" id="official-document-form" data-download-form="true" target="_blank" rel="noopener">
                        @csrf
                        <input type="hidden" name="type" value="{{ old('type', $sourceDocument?->type ?? \App\Models\OfficialDocument::TYPE_OTHER) }}">

                        <div class="row">
                            <div class="col-md-5 form-group">
                                <label for="school_id">{{ __('Escola') }}</label>
                                <select id="school_id" name="school_id" class="form-control @error('school_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Selecione') }}</option>
                                    @foreach ($schools as $school)
                                        <option value="{{ $school->id }}" @selected((int) old('school_id', $sourceDocument?->school_id) === $school->id)>{{ $school->name }}</option>
                                    @endforeach
                                </select>
                                @error('school_id') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="orientation">{{ __('Orientação') }}</label>
                                <select id="orientation" name="orientation" class="form-control @error('orientation') is-invalid @enderror" required>
                                    <option value="portrait" @selected(old('orientation', $sourceDocument?->orientation ?? 'portrait') === 'portrait')>{{ __('Retrato') }}</option>
                                    <option value="landscape" @selected(old('orientation', $sourceDocument?->orientation) === 'landscape')>{{ __('Paisagem') }}</option>
                                </select>
                                @error('orientation') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="line_spacing">{{ __('Espaçamento entre linhas') }}</label>
                                <select id="line_spacing" name="line_spacing" class="form-control @error('line_spacing') is-invalid @enderror" required>
                                    <option value="1" @selected((string) old('line_spacing', $sourceDocument?->line_spacing ?? '1.5') === '1')>{{ __('Simples') }}</option>
                                    <option value="1.15" @selected((string) old('line_spacing', $sourceDocument?->line_spacing ?? '1.5') === '1.15')>1,15</option>
                                    <option value="1.5" @selected((string) old('line_spacing', $sourceDocument?->line_spacing ?? '1.5') === '1.5')>1,5</option>
                                    <option value="2" @selected((string) old('line_spacing', $sourceDocument?->line_spacing ?? '1.5') === '2')>{{ __('Duplo') }}</option>
                                </select>
                                @error('line_spacing') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="title">{{ __('Título do documento') }}</label>
                            <input id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $sourceDocument?->title) }}" maxlength="255" required>
                            @error('title') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="content_html">{{ __('Conteúdo') }}</label>
                            <p id="editor-help" class="small text-muted">{{ __('Insira tabelas e imagens pela barra de ferramentas. Clique numa tabela para editar linhas e colunas; clique numa imagem para redimensionar ou remover.') }}</p>
                            <textarea id="content_html" name="content_html" class="form-control" aria-describedby="editor-help" aria-label="{{ __('Formatação do conteúdo') }}">{{ $editorContent }}</textarea>
                            <p id="editor-feedback" class="small text-danger mt-2" role="alert" hidden></p>
                            @error('content_html') <div class="invalid-feedback d-block">{{ __($message) }}</div> @enderror
                        </div>

                        <button class="btn btn-primary" type="submit" @disabled($schools->isEmpty())>
                            <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>
                            {{ __('Visualizar PDF oficial') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Documentos emitidos') }}</h2>
                </div>
                <div class="card-body">
                    <p class="small text-muted">{{ __('Reeditar cria uma nova emissão. Reemitir abre o documento já emitido para visualizar ou imprimir.') }}</p>
                    @forelse ($recentDocuments as $document)
                        <div class="border-bottom pb-2 mb-2">
                            <strong class="d-block">{{ $document->title }}</strong>
                            <span class="small text-muted">{{ $document->school?->name }}</span>
                            @if ($document->issuedDocument)
                                <span class="d-block small">{{ __('Código:') }} {{ $document->issuedDocument->verification_code }}</span>
                            @endif
                            @if ($document->issuedDocument)
                                <a class="btn btn-sm btn-outline-secondary mt-2" href="{{ route('official-documents.reissue', $document) }}" target="_blank" rel="noopener">
                                    <i class="fas fa-print mr-1" aria-hidden="true"></i> {{ __('Reemitir') }}
                                </a>
                            @endif
                            <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('official-documents.edit', $document) }}">
                                <i class="fas fa-edit mr-1" aria-hidden="true"></i> {{ __('Reeditar') }}
                            </a>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Nenhum documento emitido ainda.') }}</p>
                    @endforelse
                    <p class="small text-muted">{{ __('Página :page de :pages · :total documentos', ['page' => $recentDocuments->currentPage(), 'pages' => $recentDocuments->lastPage(), 'total' => $recentDocuments->total()]) }}</p>
                    {{ $recentDocuments->links('pagination::simple-bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <link rel="stylesheet" href="{{ asset('template/vendor/summernote/summernote-bs4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('template/css/sge-document-editor.css') }}?v={{ filemtime(public_path('template/css/sge-document-editor.css')) }}">
    <script src="{{ asset('template/vendor/summernote/summernote-bs4.min.js') }}"></script>
    <script src="{{ asset('template/vendor/summernote/lang/summernote-'.(app()->getLocale() === 'it' ? 'it-IT' : 'pt-BR').'.min.js') }}"></script>
    <script>
        window.sgeDocumentEditor = {
            language: @js(app()->getLocale() === 'it' ? 'it-IT' : 'pt-BR'),
            imageError: @js(__('Use imagens PNG ou JPEG de até 1 MB e 4096 pixels por lado. O documento aceita até 4 MB de imagens.')),
            localImage: @js(__('Escolha uma imagem do seu dispositivo.')),
            empty: @js(__('Digite o conteúdo do documento antes de gerar o PDF.')),
            busy: @js(__('Aguarde a inserção das imagens.')),
            originalSize: @js(__('Tamanho original')),
            fontUnit: @js(__('Unidade do tamanho da fonte')),
            imageDescription: @js(__('Descrição da imagem')),
        };
    </script>
    <script src="{{ asset('template/js/sge-document-editor.js') }}?v={{ filemtime(public_path('template/js/sge-document-editor.js')) }}"></script>
@endpush
