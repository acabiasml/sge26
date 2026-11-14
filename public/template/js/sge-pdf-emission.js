(() => {
    'use strict';
    const appRoot = new URL(document.currentScript.dataset.appUrl, location.origin);
    const rootPath = appRoot.pathname.replace(/\/$/, '') + '/';
    const pdfPath = /(?:[/-]pdf|\/recibo-voluntario)\/?$/;
    const parse = value => {
        try {
            const url = new URL(value, location.href);
            return url.origin === appRoot.origin && url.pathname.startsWith(rootPath)
                && !url.searchParams.has('signature') ? url : null;
        } catch (_) { return null; }
    };
    const emission = () => typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID() : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    // Executar depois dos eventos do próprio link preserva os filtros das exportações.
    const refreshLink = event => {
        const link = event.target.closest?.('a[href]');
        if (!link || link.hasAttribute('download')) return;
        const url = parse(link.href);
        if (!url || !pdfPath.test(url.pathname)) return;
        url.searchParams.set('emissao', emission());
        link.href = url.href;
    };
    ['click', 'auxclick', 'contextmenu'].forEach(name => document.addEventListener(name, refreshLink));
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') return;
        const url = parse(form.action);
        if (!url || (!pdfPath.test(url.pathname) && !url.pathname.endsWith('/emissao-de-documentos/emitir'))) return;
        let field = form.querySelector('input[name="emissao"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'emissao';
            form.appendChild(field);
        }
        field.value = emission();
    });
})();
