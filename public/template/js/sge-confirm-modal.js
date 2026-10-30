(() => {
    'use strict';

    const modal = document.getElementById('confirmActionModal');
    if (!modal || !window.jQuery?.fn.modal) return;

    const dialog = window.jQuery(modal);
    const message = modal.querySelector('#confirmActionMessage');
    const accept = modal.querySelector('[data-confirm-accept]');
    const confirmedForms = new WeakSet();
    let pendingForm = null;
    let pendingSubmitter = null;
    let pendingAction = null;
    let returnFocus = null;

    window.sgeConfirmAction = (confirmation, action, trigger) => {
        pendingForm = null;
        pendingSubmitter = null;
        pendingAction = action;
        returnFocus = trigger || document.activeElement;
        message.textContent = confirmation;
        accept.classList.add('btn-danger');
        accept.classList.remove('btn-primary');
        dialog.modal('show');
    };

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (confirmedForms.has(form)) {
            confirmedForms.delete(form);
            return;
        }

        const submitter = event.submitter;
        const confirmation = submitter?.dataset.confirmMessage || form.dataset.confirmMessage;
        if (!confirmation) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        pendingForm = form;
        pendingSubmitter = submitter;
        pendingAction = null;
        returnFocus = submitter || document.activeElement;
        message.textContent = confirmation;

        const method = form.querySelector('input[name="_method"]')?.value.toUpperCase();
        const destructive = method === 'DELETE' || submitter?.matches('.btn-danger, .btn-outline-danger, .text-danger');
        accept.classList.toggle('btn-danger', destructive);
        accept.classList.toggle('btn-primary', !destructive);
        dialog.modal('show');
    }, true);

    accept.addEventListener('click', () => {
        const action = pendingAction;
        const form = pendingForm;
        const submitter = pendingSubmitter?.isConnected ? pendingSubmitter : undefined;
        pendingForm = null;
        pendingSubmitter = null;
        pendingAction = null;
        dialog.modal('hide');

        if (action) {
            action();
            return;
        }
        if (!form?.isConnected) return;
        confirmedForms.add(form);
        form.requestSubmit(submitter);
        confirmedForms.delete(form);
    });

    dialog.on('hidden.bs.modal', () => {
        pendingForm = null;
        pendingSubmitter = null;
        pendingAction = null;
        if (returnFocus?.isConnected) returnFocus.focus();
        returnFocus = null;
    });
})();
