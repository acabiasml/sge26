(() => {
    let opener = null;
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-diary-alert-manager]');
        if (!button || !window.Livewire) return;
        opener = button;
        event.preventDefault();
        Livewire.dispatch('manage-diary-alerts', {
            schoolClassId: Number(button.dataset.schoolClass),
            componentId: Number(button.dataset.component),
            periodId: Number(button.dataset.period),
        });
    });
    const focusEditor = () => window.requestAnimationFrame(() => {
        const modal = document.getElementById('diaryAlertManager');
        modal?.querySelector('.modal-body')?.scrollTo({ top: 0 });
        modal?.querySelector('textarea')?.focus();
    });
    document.addEventListener('livewire:init', () => {
        Livewire.on('diary-alert-manager-open', () => {
            window.requestAnimationFrame(() => window.jQuery('#diaryAlertManager').modal('show'));
        });
        Livewire.on('diary-alert-manager-focus', focusEditor);
        window.jQuery('#diaryAlertManager').on('shown.bs.modal', focusEditor);
        window.jQuery('#diaryAlertManager').on('hidden.bs.modal', () => {
            const target = opener?.isConnected ? opener : [...document.querySelectorAll('[data-diary-alert-manager]')].find(button =>
                button.dataset.schoolClass === opener?.dataset.schoolClass &&
                button.dataset.component === opener?.dataset.component &&
                button.dataset.period === opener?.dataset.period
            );
            target?.focus({ preventScroll: true });
        });
    });
})();
