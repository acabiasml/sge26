@extends('layouts.app')

@section('title', __('Documentos oficiais'))
@section('page-title', __('Documentos oficiais'))

@section('content')
    <div class="row">
        @if (auth()->user()->canManagePeople())
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
                            <label for="visibility">{{ __('Visibilidade do documento') }}</label>
                            <select id="visibility" name="visibility" class="form-control @error('visibility') is-invalid @enderror" required>
                                <option value="private" @selected(old('visibility', \App\Models\OfficialDocument::VISIBILITY_PRIVATE) === 'private')>{{ __('Privado — visível apenas para você e administradores') }}</option>
                                <option value="public" @selected(old('visibility', \App\Models\OfficialDocument::VISIBILITY_PRIVATE) === 'public')>{{ __('Público — visível aos vinculados à escola') }}</option>
                            </select>
                            @error('visibility') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
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
                            {{ __('Visualizar PDF oficial') }}<span class="sr-only"> {{ __('(abre em nova aba)') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <div class="{{ auth()->user()->canManagePeople() ? 'col-xl-4' : 'col-12' }} mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Emitidos') }}</h2>
                </div>
                <div class="card-body">
                    <nav class="nav nav-pills sge-official-tabs mb-3" aria-label="{{ __('Filtrar emitidos') }}">
                        <a class="nav-link {{ $tab === 'public' ? 'active' : '' }}" href="{{ route('official-documents.create', ['visibility' => 'public']) }}" @if ($tab === 'public') aria-current="page" @endif>{{ __('Públicos') }}</a>
                        <a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}" href="{{ route('official-documents.create', ['visibility' => 'mine']) }}" @if ($tab === 'mine') aria-current="page" @endif>{{ __('Meus') }}</a>
                        @if (auth()->user()->isAdministrator())
                            <a class="nav-link {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('official-documents.create', ['visibility' => 'all']) }}" @if ($tab === 'all') aria-current="page" @endif>{{ __('Todos') }}</a>
                        @endif
                    </nav>
                    <p class="small text-muted">{{ __('Reeditar cria uma nova emissão; reemitir abre o PDF original.') }}</p>
                    <ul class="list-unstyled mb-2">
                    @forelse ($recentDocuments as $document)
                        <li class="border-bottom pb-3 mb-3">
                            <h3 class="h6 font-weight-bold mb-1">{{ $document->title }}</h3>
                            @if ($tab !== 'public')
                                <span class="badge badge-{{ $document->visibility === 'public' ? 'primary' : 'secondary' }}">{{ $document->visibility === 'public' ? __('Público') : __('Privado') }}</span>
                            @endif
                            <span class="d-block small text-muted">{{ $document->school?->name }}</span>
                            @if (auth()->user()->isAdministrator() && $document->visibility === 'private')
                                <span class="d-block small text-muted">{{ __('Proprietário:') }} {{ $document->privateOwner?->person?->full_name ?: $document->privateOwner?->name ?: __('Usuário não disponível') }}</span>
                            @endif
                            @if ($document->issuedDocument)
                                @php($issuer = $document->issuedDocument->issuedBy ?? $document->createdBy)
                                <p class="sge-official-metadata small mb-0">
                                    <span><strong>{{ __('Código:') }}</strong> {{ $document->issuedDocument->verification_code }}</span>
                                    <span><strong>{{ __('Emitido em:') }}</strong> <time datetime="{{ $document->issuedDocument->issued_at->toIso8601String() }}">{{ $document->issuedDocument->issued_at->copy()->timezone('America/Cuiaba')->format('d/m/Y H:i') }}</time> {{ __('(MT)') }}</span>
                                    <span><strong>{{ __('Emitido por:') }}</strong> {{ $issuer?->person?->full_name ?: $issuer?->name ?: $issuer?->email ?: __('Usuário não disponível') }}</span>
                                </p>
                            @endif
                            <div class="sge-action-buttons justify-content-start sge-official-actions" role="group" aria-label="{{ __('Ações para :title', ['title' => $document->title]) }}">
                                @if ($document->issuedDocument)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('official-documents.reissue', $document) }}" target="_blank" rel="noopener" aria-label="{{ __('Reemitir PDF de :title em nova aba', ['title' => $document->title]) }}" title="{{ __('Reemitir PDF') }}">
                                        <i class="fas fa-print" aria-hidden="true"></i> {{ __('Reemitir') }}
                                    </a>
                                @endif
                                @if (auth()->user()->canManageSchool($document->school_id))
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('official-documents.edit', $document) }}" aria-label="{{ __('Reeditar :title', ['title' => $document->title]) }}" title="{{ __('Reeditar') }}">
                                        <i class="fas fa-edit" aria-hidden="true"></i> {{ __('Reeditar') }}
                                    </a>
                                @endif
                                @if (auth()->user()->isAdministrator())
                                    @if ($document->visibility === 'public')
                                        <a href="{{ route('official-documents.private-owner', $document) }}" class="btn btn-sm btn-outline-warning" aria-label="{{ __('Definir proprietário e tornar privado: :title', ['title' => $document->title]) }}" title="{{ __('Tornar privado') }}">
                                            <i class="fas fa-lock" aria-hidden="true"></i> {{ __('Tornar privado') }}
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('official-documents.visibility', $document) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="visibility" value="public">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" aria-label="{{ __('Tornar público: :title', ['title' => $document->title]) }}" title="{{ __('Tornar público') }}">
                                                <i class="fas fa-globe-americas" aria-hidden="true"></i> {{ __('Tornar público') }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                @if (auth()->user()->isAdministrator() || ($document->visibility === 'private' && $document->private_owner_user_id === auth()->id()))
                                    <form method="POST" action="{{ route('official-documents.destroy', $document) }}" data-confirm-message="{{ __('Deseja excluir este documento? Esta ação é permanente.') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Excluir :title', ['title' => $document->title]) }}" title="{{ __('Excluir') }}">
                                            <i class="fas fa-trash-alt" aria-hidden="true"></i> {{ __('Excluir') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('Nenhum item encontrado.') }}</li>
                    @endforelse
                    </ul>
                    <p class="small text-muted">{{ __('Página :page de :pages · :total documentos', ['page' => $recentDocuments->currentPage(), 'pages' => $recentDocuments->lastPage(), 'total' => $recentDocuments->total()]) }}</p>
                    {{ $recentDocuments->links('pagination::simple-bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>

    @if (auth()->user()->canManagePeople())
        <div class="modal fade" id="imageDescriptionModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="imageDescriptionTitle" aria-describedby="imageDescriptionHelp">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form id="imageDescriptionForm" data-no-loading="true">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="imageDescriptionTitle">{{ __('Descrição da imagem') }}</h2>
                            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <label for="imageDescriptionInput">{{ __('Descrição da imagem') }}</label>
                            <input id="imageDescriptionInput" class="form-control" maxlength="500" aria-describedby="imageDescriptionHelp">
                            <p class="small text-muted mt-2 mb-0" id="imageDescriptionHelp">{{ __('Descreva a imagem para quem usa leitor de tela. Deixe vazio se ela for decorativa.') }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('Cancelar') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Salvar descrição') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @if (auth()->user()->canManagePeople())
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
    @endif
@endpush
