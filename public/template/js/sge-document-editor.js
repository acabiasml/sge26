(() => {
    'use strict';
    const input = document.getElementById('content_html');
    if (!input || !window.jQuery?.fn.summernote) return;
    const $editor = window.jQuery(input);
    const form = document.getElementById('official-document-form');
    const feedback = document.getElementById('editor-feedback');
    const labels = window.sgeDocumentEditor;
    const descriptionModal = document.getElementById('imageDescriptionModal');
    const descriptionForm = document.getElementById('imageDescriptionForm');
    const descriptionInput = document.getElementById('imageDescriptionInput');
    let pending = 0;
    let selectedImage = null;
    let imageForDescription = null;
    let descriptionContext = null;
    let descriptionTrigger = null;
    const error = message => { feedback.textContent = message; feedback.hidden = !message; };
    const imageBytes = () => {
        const doc = new DOMParser().parseFromString($editor.summernote('code'), 'text/html');
        return Array.from(doc.images).reduce((sum, img) => sum + (img.src.split(',')[1]?.length || 0) * 0.75, 0);
    };
    const insertImages = async files => {
        pending++;
        try {
            for (const file of files) {
                if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 1048576 || imageBytes() + file.size > 4194304) throw new Error(labels.imageError);
                const src = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(new Error(labels.imageError));
                    reader.readAsDataURL(file);
                });
                const image = new Image();
                image.src = src;
                await image.decode();
                if (image.naturalWidth > 4096 || image.naturalHeight > 4096 || image.naturalWidth * image.naturalHeight > 8388608) throw new Error(labels.imageError);
                image.alt = file.name.replace(/\.[^.]+$/, '');
                image.style.width = Math.min(image.naturalWidth, 600) + 'px';
                $editor.summernote('insertNode', image);
            }
            error('');
        } catch (e) { error(e.message === labels.imageError ? e.message : labels.imageError); }
        finally { pending--; }
    };
    window.jQuery.summernote.lang[labels.language].font.sizeunit = labels.fontUnit;
    window.jQuery.summernote.lang[labels.language].image.resizeNone = labels.originalSize;
    window.jQuery(descriptionModal).on('shown.bs.modal', () => descriptionInput.focus());
    window.jQuery(descriptionModal).on('hidden.bs.modal', () => {
        if (descriptionTrigger?.isConnected) descriptionTrigger.focus();
        descriptionTrigger = null;
        imageForDescription = null;
        descriptionContext = null;
    });
    descriptionForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!imageForDescription?.isConnected || !descriptionContext) return;
        descriptionContext.invoke('editor.beforeCommand');
        imageForDescription.alt = descriptionInput.value;
        descriptionContext.invoke('editor.afterCommand');
        window.jQuery(descriptionModal).modal('hide');
    });
    $editor.summernote({
        lang: labels.language,
        minHeight: 420,
        dialogsInBody: true,
        acceptImageFileTypes: 'image/png,image/jpeg',
        disableDragAndDrop: true,
        styleTags: ['p', 'h2', 'h3', 'h4', 'blockquote'],
        fontNames: ['Atkinson Hyperlegible Next', 'DejaVu Serif', 'DejaVu Sans Mono'],
        fontNamesIgnoreCheck: ['Atkinson Hyperlegible Next', 'DejaVu Serif', 'DejaVu Sans Mono'],
        fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32', '36'],
        fontSizeUnits: ['pt'],
        toolbar: [
            ['history', ['undo', 'redo']], ['style', ['style']],
            ['font', ['fontname', 'fontsize', 'fontsizeunit', 'bold', 'italic', 'underline', 'clear']],
            ['effects', ['strikethrough', 'superscript', 'subscript']],
            ['color', ['color']],
            ['paragraph', ['ul', 'ol', 'paragraph', 'height']], ['insert', ['table', 'picture', 'link', 'hr']],
            ['view', ['fullscreen', 'help']]
        ],
        popover: {
            image: [['size', ['resizeFull', 'resizeHalf', 'resizeQuarter', 'resizeNone']], ['float', ['floatLeft', 'floatRight', 'floatNone']], ['description', ['imageDescription']], ['remove', ['removeMedia']]],
            table: [['add', ['addRowUp', 'addRowDown', 'addColLeft', 'addColRight']], ['delete', ['deleteRow', 'deleteCol', 'deleteTable']]],
            link: [['link', ['linkDialogShow', 'unlink']]], air: []
        },
        buttons: {
            imageDescription: context => window.jQuery.summernote.ui.button({
                contents: '<i class="fas fa-comment-alt" aria-hidden="true"></i>',
                tooltip: labels.imageDescription,
                click: event => {
                    if (!selectedImage?.isConnected) return;
                    imageForDescription = selectedImage;
                    descriptionContext = context;
                    descriptionTrigger = event.currentTarget;
                    descriptionInput.value = selectedImage.alt;
                    window.jQuery(descriptionModal).modal('show');
                }
            }).render()
        },
        callbacks: {
            onImageUpload: insertImages,
            onImageLinkInsert: () => error(labels.localImage),
            onChange: html => { input.value = html; },
            onInit: () => {
                const editor = $editor.next('.note-editor');
                const editable = editor.find('.note-editable')[0];
                editable.setAttribute('aria-label', input.getAttribute('aria-label'));
                editable.setAttribute('aria-describedby', 'editor-help editor-feedback');
                editable.addEventListener('mousedown', e => { if (e.target.tagName === 'IMG') selectedImage = e.target; }, true);

                const resizebar = editor.find('.note-resizebar')[0];
                if (resizebar) {
                    const minHeight = 420;
                    const maxHeight = 1000;
                    resizebar.setAttribute('role', 'separator');
                    resizebar.setAttribute('tabindex', '0');
                    resizebar.setAttribute('aria-label', labels.resizeEditor);
                    resizebar.setAttribute('aria-orientation', 'horizontal');
                    resizebar.setAttribute('aria-valuemin', minHeight);
                    resizebar.setAttribute('aria-valuemax', maxHeight);
                    const syncHeight = () => {
                        const height = Math.round(editable.getBoundingClientRect().height);
                        resizebar.setAttribute('aria-valuemax', Math.max(maxHeight, height));
                        resizebar.setAttribute('aria-valuenow', height);
                    };
                    syncHeight();
                    resizebar.addEventListener('keydown', event => {
                        if (!['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
                        event.preventDefault();
                        const current = Math.round(editable.getBoundingClientRect().height);
                        const next = event.key === 'Home' ? minHeight : event.key === 'End' ? maxHeight
                            : current + (event.key === 'ArrowDown' ? 40 : -40);
                        editable.style.height = `${Math.max(minHeight, Math.min(maxHeight, next))}px`;
                        syncHeight();
                    });
                    document.addEventListener('mouseup', syncHeight);
                }
            }
        }
    });
    form.addEventListener('submit', event => {
        input.value = $editor.summernote('code');
        if (pending || $editor.summernote('isEmpty')) {
            event.preventDefault();
            error(pending ? labels.busy : labels.empty);
            $editor.summernote('focus');
        }
    });
})();
