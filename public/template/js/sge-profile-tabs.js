(() => {
    'use strict';
    const form = document.querySelector('[data-profile-form]');
    if (!form) return;

    form.addEventListener('submit', () => {
        const tab = form.querySelector('[role="tab"][aria-selected="true"]');
        if (tab) form.elements.profile_tab.value = tab.dataset.academicTab;
    });
})();
