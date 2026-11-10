(() => {
    'use strict';
    const form = document.querySelector('[data-profile-form]');
    if (!form) return;

    // A validação nativa precisa revelar o primeiro campo inválido antes de focá-lo.
    form.addEventListener('invalid', (event) => {
        const first = form.querySelector('input:invalid, select:invalid, textarea:invalid');
        if (event.target !== first) return;
        const panel = first.closest('[data-academic-panel]');
        if (panel) form.querySelector(`[data-academic-tab="${CSS.escape(panel.dataset.academicPanel)}"]`)?.click();
        for (let parent = first.parentElement; parent && parent !== form; parent = parent.parentElement) {
            if (parent.matches('details')) parent.open = true;
        }
    }, true);

    form.addEventListener('submit', () => {
        const tab = form.querySelector('[role="tab"][aria-selected="true"]');
        if (tab) form.elements.profile_tab.value = tab.dataset.academicTab;
    });
})();
