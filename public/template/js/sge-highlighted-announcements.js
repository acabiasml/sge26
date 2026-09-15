(() => {
    const queue = Array.from(document.querySelectorAll('.sge-highlight-modal'));
    function next() {
        const modal = queue.shift();
        if (!modal) return;
        const dialog = window.jQuery(modal);
        dialog.one('hidden.bs.modal', next);
        const button = modal.querySelector('[data-mark-seen]');
        button.addEventListener('click', async () => {
            button.disabled = true;
            modal.querySelector('[data-seen-error]').classList.add('d-none');
            try {
                const response = await fetch(button.dataset.markSeen, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'}
                });
                if (!response.ok || response.redirected) throw new Error('save failed');
                dialog.modal('hide');
            } catch (_) {
                modal.querySelector('[data-seen-error]').classList.remove('d-none');
                button.disabled = false;
            }
        });
        dialog.modal('show');
    }
    window.jQuery(next);
})();
