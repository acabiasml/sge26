(() => {
    'use strict';
    const script = document.currentScript;
    const storageKey = 'sge.navigation.v1:' + script.dataset.navigationScope;
    const appRoot = new URL(script.dataset.appUrl, location.origin);
    const inApp = url => url.origin === location.origin
        && (url.pathname === appRoot.pathname.replace(/\/$/, '') || url.pathname.startsWith(appRoot.pathname.replace(/\/$/, '') + '/'));
    const parse = value => {
        if (typeof value !== 'string') return null;
        try { const url = new URL(value, location.origin); return inApp(url) ? url : null; } catch (_) { return null; }
    };
    let pages = new Map(), sections = new Map();
    const reload = () => {
        try {
            const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
            pages = new Map(saved.pages || []);
            sections = new Map(saved.sections || []);
        } catch (_) {}
    };
    reload();
    const put = (map, key, value) => {
        map.delete(key);
        map.set(key, value);
        if (map.size > 100) map.delete(map.keys().next().value);
    };
    const persist = () => {
        try { sessionStorage.setItem(storageKey, JSON.stringify({ pages: [...pages], sections: [...sections] })); } catch (_) {}
    };
    const pageKey = () => {
        const url = new URL(location.href);
        url.searchParams.sort();
        return url.pathname + url.search;
    };
    const remember = () => {
        put(pages, location.pathname, location.pathname + location.search + location.hash);
        persist();
    };
    const restoreLink = link => {
        link.dataset.navigationFallback ??= link.getAttribute('href');
        const fallback = parse(link.dataset.navigationFallback);
        if (!fallback || fallback.hash || fallback.pathname === location.pathname) return;
        link.href = fallback.href;
        const saved = parse(pages.get(fallback.pathname));
        if (!saved || saved.pathname !== fallback.pathname) return;
        // Um período ou contexto explicitamente indicado no botão tem precedência.
        const values = (url, key) => url.searchParams.getAll(url.searchParams.has(key) ? key : `f[${key}]`);
        if ([...fallback.searchParams.keys()].some(key => JSON.stringify(values(fallback, key)) !== JSON.stringify(values(saved, key)))) return;
        link.href = saved.href;
    };
    const refreshLinks = () => document.querySelectorAll('a[data-navigation-return][href]').forEach(restoreLink);
    window.sgeNavigation = {
        section: () => sections.get(pageKey()),
        rememberSection: name => {
            put(sections, pageKey(), name);
            remember();
        },
    };
    // Eventos delegados também alcançam os botões recriados pelo Livewire.
    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href]');
        if (link?.matches('[data-navigation-return]')) restoreLink(link);
        remember();
    }, true);
    document.addEventListener('contextmenu', event => {
        const link = event.target.closest?.('a[data-navigation-return][href]');
        if (link) restoreLink(link);
        remember();
    }, true);
    window.addEventListener('pagehide', remember);
    window.addEventListener('hashchange', remember);
    window.addEventListener('pageshow', () => { reload(); refreshLinks(); remember(); });
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morphed', refreshLinks);
    });
    refreshLinks();
    remember();
})();
