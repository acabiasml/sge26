(() => {
    const root = document.documentElement;
    const selectedTheme = root.dataset.theme;
    if (!selectedTheme) {
        return;
    }

    const family = selectedTheme.endsWith('-light') ? selectedTheme.slice(0, -6) : selectedTheme;
    const standaloneTheme = family === 'beaba' || family === 'govbr';
    const systemTheme = window.matchMedia?.('(prefers-color-scheme: dark)');

    const applyTheme = () => {
        root.dataset.themeFamily = family;
        root.dataset.theme = systemTheme?.matches
            ? (standaloneTheme ? 'aurora-dark' : `${family}-dark`)
            : (standaloneTheme ? family : `${family}-light`);
    };

    applyTheme();
    if (systemTheme?.addEventListener) {
        systemTheme.addEventListener('change', applyTheme);
    } else {
        systemTheme?.addListener?.(applyTheme);
    }
})();
