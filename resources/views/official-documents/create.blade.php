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
                            <label for="official-editor">{{ __('Conteúdo') }}</label>
                            <div class="sge-editor-toolbar" role="toolbar" aria-label="{{ __('Formatação do conteúdo') }}">
                                <label class="sge-editor-control" for="editor_font_family">
                                    <span>{{ __('Fonte') }}</span>
                                    <select id="editor_font_family" class="form-control form-control-sm" data-editor-font-family>
                                        <option value="">{{ __('Padrão') }}</option>
                                        <option value="Atkinson Hyperlegible Next">Atkinson Hyperlegible</option>
                                        <option value="DejaVu Serif">{{ __('Serifada') }}</option>
                                        <option value="DejaVu Sans Mono">{{ __('Monoespaçada') }}</option>
                                    </select>
                                </label>
                                <label class="sge-editor-control" for="editor_font_size">
                                    <span>{{ __('Tamanho') }}</span>
                                    <select id="editor_font_size" class="form-control form-control-sm" data-editor-font-size>
                                        <option value="">{{ __('Padrão') }}</option>
                                        <option value="10pt">10</option>
                                        <option value="11pt">11</option>
                                        <option value="12pt">12</option>
                                        <option value="14pt">14</option>
                                        <option value="16pt">16</option>
                                        <option value="18pt">18</option>
                                    </select>
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="bold" aria-label="{{ __('Negrito') }}" title="{{ __('Negrito') }}">
                                    <i class="fas fa-bold" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="italic" aria-label="{{ __('Itálico') }}" title="{{ __('Itálico') }}">
                                    <i class="fas fa-italic" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="underline" aria-label="{{ __('Sublinhado') }}" title="{{ __('Sublinhado') }}">
                                    <i class="fas fa-underline" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="insertUnorderedList" aria-label="{{ __('Lista com marcadores') }}" title="{{ __('Lista com marcadores') }}">
                                    <i class="fas fa-list-ul" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="insertOrderedList" aria-label="{{ __('Lista numerada') }}" title="{{ __('Lista numerada') }}">
                                    <i class="fas fa-list-ol" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-format="h2" aria-label="{{ __('Título') }}" title="{{ __('Título') }}">
                                    <i class="fas fa-heading" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-format="p" aria-label="{{ __('Parágrafo') }}" title="{{ __('Parágrafo') }}">
                                    <i class="fas fa-paragraph" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyLeft" aria-label="{{ __('Alinhar à esquerda') }}" title="{{ __('Alinhar à esquerda') }}">
                                    <i class="fas fa-align-left" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyCenter" aria-label="{{ __('Centralizar') }}" title="{{ __('Centralizar') }}">
                                    <i class="fas fa-align-center" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyRight" aria-label="{{ __('Alinhar à direita') }}" title="{{ __('Alinhar à direita') }}">
                                    <i class="fas fa-align-right" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyFull" aria-label="{{ __('Justificar') }}" title="{{ __('Justificar') }}">
                                    <i class="fas fa-align-justify" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="official-editor" class="form-control sge-rich-editor @error('content_html') is-invalid @enderror" contenteditable="true" role="textbox" aria-multiline="true">{!! $editorContent !!}</div>
                            <textarea id="content_html" name="content_html" class="d-none" required>{{ $editorContent }}</textarea>
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
    <script>
        const officialEditor = document.getElementById('official-editor');
        const officialEditorInput = document.getElementById('content_html');
        const officialDocumentForm = document.getElementById('official-document-form');
        const fontFamilySelect = document.querySelector('[data-editor-font-family]');
        const fontSizeSelect = document.querySelector('[data-editor-font-size]');

        const syncOfficialEditor = () => {
            officialEditorInput.value = officialEditor.innerHTML.trim();
        };

        const applyEditorStyle = (styles) => {
            officialEditor.focus();

            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
                return;
            }

            const range = selection.getRangeAt(0);
            const wrapper = document.createElement('span');

            Object.entries(styles).forEach(([property, value]) => {
                if (value) {
                    wrapper.style[property] = value;
                }
            });

            wrapper.appendChild(range.extractContents());
            range.insertNode(wrapper);

            selection.removeAllRanges();
            const newRange = document.createRange();
            newRange.selectNodeContents(wrapper);
            selection.addRange(newRange);

            syncOfficialEditor();
        };

        // Keep the selected paragraph when the toolbar receives a mouse click.
        document.querySelectorAll('.sge-editor-toolbar button').forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
        });

        document.querySelectorAll('[data-editor-command]').forEach((button) => {
            button.addEventListener('click', () => {
                officialEditor.focus();
                document.execCommand('styleWithCSS', false, button.dataset.editorCommand.startsWith('justify'));
                document.execCommand(button.dataset.editorCommand, false, null);
                document.execCommand('styleWithCSS', false, false);
                syncOfficialEditor();
            });
        });

        document.querySelectorAll('[data-editor-format]').forEach((button) => {
            button.addEventListener('click', () => {
                officialEditor.focus();
                document.execCommand('formatBlock', false, button.dataset.editorFormat);
                syncOfficialEditor();
            });
        });

        fontFamilySelect?.addEventListener('change', () => {
            applyEditorStyle({ fontFamily: fontFamilySelect.value });
        });

        fontSizeSelect?.addEventListener('change', () => {
            applyEditorStyle({ fontSize: fontSizeSelect.value });
        });

        officialEditor?.addEventListener('input', syncOfficialEditor);
        officialEditor?.addEventListener('paste', () => setTimeout(syncOfficialEditor, 0));
        officialDocumentForm?.addEventListener('submit', syncOfficialEditor);
    </script>
@endpush
