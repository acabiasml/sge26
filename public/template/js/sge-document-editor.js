(() => {
    'use strict';
    const input = document.getElementById('content_html');
    if (!input || !window.jQuery?.fn.summernote) return;
    const $editor = window.jQuery(input);
    const form = document.getElementById('official-document-form');
    const feedback = document.getElementById('editor-feedback');
    const labels = window.sgeDocumentEditor;
    let pending = 0;
    let selectedImage = null;
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
                click: () => {
                    if (!selectedImage?.isConnected) return;
                    const description = window.prompt(labels.imageDescription, selectedImage.alt);
                    if (description === null) return;
                    context.invoke('editor.beforeCommand');
                    selectedImage.alt = description.slice(0, 500);
                    context.invoke('editor.afterCommand');
                }
            }).render()
        },
        callbacks: {
            onImageUpload: insertImages,
            onImageLinkInsert: () => error(labels.localImage),
            onChange: html => { input.value = html; },
            onInit: () => {
                const editable = $editor.next('.note-editor').find('.note-editable')[0];
                editable.setAttribute('aria-label', input.getAttribute('aria-label'));
                editable.setAttribute('aria-describedby', 'editor-help editor-feedback');
                editable.addEventListener('mousedown', e => { if (e.target.tagName === 'IMG') selectedImage = e.target; }, true);
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
