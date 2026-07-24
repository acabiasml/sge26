@extends('layouts.app')

@section('title', 'Documentos oficiais')
@section('page-title', 'Documentos oficiais')

@section('content')
    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Novo documento em papel timbrado</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('official-documents.store') }}" id="official-document-form" data-download-form="true">
                        @csrf
                        <input type="hidden" name="type" value="{{ \App\Models\OfficialDocument::TYPE_OTHER }}">

                        <div class="row">
                            <div class="col-md-5 form-group">
                                <label for="school_id">Escola</label>
                                <select id="school_id" name="school_id" class="form-control @error('school_id') is-invalid @enderror" required>
                                    <option value="">Selecione</option>
                                    @foreach ($schools as $school)
                                        <option value="{{ $school->id }}" @selected((int) old('school_id') === $school->id)>{{ $school->name }}</option>
                                    @endforeach
                                </select>
                                @error('school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="orientation">Orientação</label>
                                <select id="orientation" name="orientation" class="form-control @error('orientation') is-invalid @enderror" required>
                                    <option value="portrait" @selected(old('orientation', 'portrait') === 'portrait')>Retrato</option>
                                    <option value="landscape" @selected(old('orientation') === 'landscape')>Paisagem</option>
                                </select>
                                @error('orientation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="line_spacing">Espaçamento entre linhas</label>
                                <select id="line_spacing" name="line_spacing" class="form-control @error('line_spacing') is-invalid @enderror" required>
                                    <option value="1" @selected(old('line_spacing') === '1')>Simples</option>
                                    <option value="1.15" @selected(old('line_spacing') === '1.15')>1,15</option>
                                    <option value="1.5" @selected(old('line_spacing', '1.5') === '1.5')>1,5</option>
                                    <option value="2" @selected(old('line_spacing') === '2')>Duplo</option>
                                </select>
                                @error('line_spacing') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="title">Título do documento</label>
                            <input id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="255" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="official-editor">Conteúdo</label>
                            <div class="sge-editor-toolbar" role="toolbar" aria-label="Formatação do conteúdo">
                                <label class="sge-editor-control" for="editor_font_family">
                                    <span>Fonte</span>
                                    <select id="editor_font_family" class="form-control form-control-sm" data-editor-font-family>
                                        <option value="">Padrão</option>
                                        <option value="Atkinson Hyperlegible Next">Atkinson Hyperlegible</option>
                                        <option value="DejaVu Serif">Serifada</option>
                                        <option value="DejaVu Sans Mono">Monoespaçada</option>
                                    </select>
                                </label>
                                <label class="sge-editor-control" for="editor_font_size">
                                    <span>Tamanho</span>
                                    <select id="editor_font_size" class="form-control form-control-sm" data-editor-font-size>
                                        <option value="">Padrão</option>
                                        <option value="10pt">10</option>
                                        <option value="11pt">11</option>
                                        <option value="12pt">12</option>
                                        <option value="14pt">14</option>
                                        <option value="16pt">16</option>
                                        <option value="18pt">18</option>
                                    </select>
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="bold" aria-label="Negrito" title="Negrito">
                                    <i class="fas fa-bold" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="italic" aria-label="Itálico" title="Itálico">
                                    <i class="fas fa-italic" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="underline" aria-label="Sublinhado" title="Sublinhado">
                                    <i class="fas fa-underline" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="insertUnorderedList" aria-label="Lista com marcadores" title="Lista com marcadores">
                                    <i class="fas fa-list-ul" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="insertOrderedList" aria-label="Lista numerada" title="Lista numerada">
                                    <i class="fas fa-list-ol" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-format="h2" aria-label="Título" title="Título">
                                    <i class="fas fa-heading" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-format="p" aria-label="Parágrafo" title="Parágrafo">
                                    <i class="fas fa-paragraph" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyLeft" aria-label="Alinhar à esquerda" title="Alinhar à esquerda">
                                    <i class="fas fa-align-left" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyCenter" aria-label="Centralizar" title="Centralizar">
                                    <i class="fas fa-align-center" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyRight" aria-label="Alinhar à direita" title="Alinhar à direita">
                                    <i class="fas fa-align-right" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-editor-command="justifyFull" aria-label="Justificar" title="Justificar">
                                    <i class="fas fa-align-justify" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="official-editor" class="form-control sge-rich-editor @error('content_html') is-invalid @enderror" contenteditable="true" role="textbox" aria-multiline="true">{{ old('content_html') }}</div>
                            <textarea id="content_html" name="content_html" class="d-none" required>{{ old('content_html') }}</textarea>
                            @error('content_html') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <button class="btn btn-primary" type="submit" @disabled($schools->isEmpty())>
                            <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>
                            Gerar PDF oficial
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Emissões recentes</h2>
                </div>
                <div class="card-body">
                    @forelse ($recentDocuments as $document)
                        <div class="border-bottom pb-2 mb-2">
                            <strong class="d-block">{{ $document->title }}</strong>
                            <span class="small text-muted">{{ $document->school?->name }}</span>
                            @if ($document->issuedDocument)
                                <span class="d-block small">Código: {{ $document->issuedDocument->verification_code }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">Nenhum documento emitido ainda.</p>
                    @endforelse
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

        document.querySelectorAll('[data-editor-command]').forEach((button) => {
            button.addEventListener('click', () => {
                officialEditor.focus();
                document.execCommand(button.dataset.editorCommand, false, null);
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
