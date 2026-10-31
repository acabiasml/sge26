(() => {
    document.addEventListener('keydown', (event) => {
        const tab = event.target.closest?.('[data-diary-class-tabs] [role="tab"], [data-enrollment-tabs] [role="tab"]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]')];
        const index = tabs.indexOf(tab);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
            : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        event.preventDefault();
        tabs[next].focus();
        tabs[next].scrollIntoView({ block: 'nearest', inline: 'nearest' });
        tabs[next].click();
    });
})();
