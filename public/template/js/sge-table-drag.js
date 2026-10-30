(() => {
    let drag = null;
    let suppressClick = false;
    const interactive = 'a, button, input, select, textarea, label, [role="button"], [contenteditable]:not([contenteditable="false"])';

    const finish = () => {
        if (!drag) return;
        drag.container.classList.remove('sge-table-dragging');
        if (drag.started) {
            suppressClick = true;
            window.setTimeout(() => { suppressClick = false; }, 0);
        }
        drag = null;
    };

    // Delegation also covers tables replaced by Livewire after search or pagination.
    document.addEventListener('mousedown', (event) => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const container = event.target.closest('.table-responsive');
        if (!container || event.target.closest(interactive) || container.scrollWidth <= container.clientWidth + 2) return;
        // Leave the native scrollbar available for ordinary use.
        if (event.clientY >= container.getBoundingClientRect().top + container.clientHeight) return;
        drag = { container, x: event.clientX, y: event.clientY, left: container.scrollLeft, started: false };
    });

    document.addEventListener('mousemove', (event) => {
        if (!drag) return;
        if (!(event.buttons & 1) || !drag.container.isConnected) { finish(); return; }
        const distance = event.clientX - drag.x;
        if (!drag.started) {
            if (Math.abs(distance) < 6) return;
            if (Math.abs(event.clientY - drag.y) > Math.abs(distance)) { finish(); return; }
            drag.started = true;
            drag.container.classList.add('sge-table-dragging');
            window.getSelection()?.removeAllRanges();
        }
        event.preventDefault();
        drag.container.scrollLeft = drag.left - distance;
    });

    document.addEventListener('mouseup', finish);
    window.addEventListener('blur', finish);
    document.addEventListener('dragstart', (event) => { if (drag) event.preventDefault(); });
    document.addEventListener('click', (event) => {
        if (!suppressClick) return;
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);
})();
