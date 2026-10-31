(() => {
    'use strict';
    const root = document.body;
    if (!root) return;
    let nextId = 0;
    const identify = (element) => {
        if (!element.id || document.getElementById(element.id) !== element) {
            do { element.id = `sge-accessible-field-${++nextId}`; }
            while (document.getElementById(element.id) !== element);
        }
        return element.id;
    };
    const editableFor = (field) => field.matches('textarea')
        ? field.nextElementSibling?.querySelector('.note-editable') || field
        : field;
    const fields = () => [...root.querySelectorAll('input:not([type="hidden"]), select, textarea')];
    const resolve = (name, controls) => controls.find((field) =>
        field.name === name || field.name === name.replace(/\.([^.]+)/g, '[$1]'));
    const sync = () => {
        const controls = fields();
        const summaries = new Map();
        root.querySelectorAll('[data-validation-field]').forEach((entry) => {
            const field = resolve(entry.dataset.validationField, controls);
            if (!field) return;
            const target = editableFor(field);
            const id = identify(target);
            if (entry.tagName !== 'A') {
                const link = document.createElement('a');
                link.dataset.validationField = entry.dataset.validationField;
                link.textContent = entry.textContent;
                entry.replaceWith(link);
                entry = link;
            }
            entry.setAttribute('href', `#${id}`);
            identify(entry);
            summaries.set(field, entry);
        });
        controls.forEach((field) => {
            const target = editableFor(field);
            const invalid = field.classList.contains('is-invalid') || summaries.has(field);
            const messages = field.closest('.form-group')?.querySelectorAll('.invalid-feedback');
            const message = messages?.length === 1 ? messages[0] : summaries.get(field);
            [field, target].filter((item, index, all) => all.indexOf(item) === index).forEach((control) => {
                const previous = control.dataset.sgeErrorDescription;
                const descriptions = (control.getAttribute('aria-describedby') || '').split(/\s+/)
                    .filter((id) => id && id !== previous);
                if (invalid) {
                    control.setAttribute('aria-invalid', 'true');
                    control.dataset.sgeInvalid = 'true';
                    if (message) {
                        const id = identify(message);
                        descriptions.push(id);
                        control.dataset.sgeErrorDescription = id;
                    }
                } else if (control.dataset.sgeInvalid) {
                    control.removeAttribute('aria-invalid');
                    delete control.dataset.sgeInvalid;
                    delete control.dataset.sgeErrorDescription;
                }
                if (descriptions.length) control.setAttribute('aria-describedby', [...new Set(descriptions)].join(' '));
                else control.removeAttribute('aria-describedby');
            });
        });
    };
    root.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-validation-field]');
        if (!link) return;
        const field = resolve(link.dataset.validationField, fields());
        if (!field) return;
        event.preventDefault();
        const target = editableFor(field);
        for (let parent = target.parentElement; parent; parent = parent.parentElement) {
            if (parent.matches('details')) parent.open = true;
            if (parent.hidden && parent.dataset.academicPanel) {
                root.querySelector(`[data-academic-tab="${CSS.escape(parent.dataset.academicPanel)}"]`)?.click();
            }
        }
        target.focus();
        target.scrollIntoView({block: 'center'});
    });
    let scheduled = false;
    new MutationObserver(() => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => { scheduled = false; sync(); });
    }).observe(root, {childList: true, subtree: true, attributes: true, attributeFilter: ['class']});
    sync();
})();
