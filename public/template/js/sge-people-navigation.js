(() => {
    const script = document.currentScript;
    const list = new URL(script.dataset.peopleListUrl, location.origin);
    const key = 'sge.people.return';
    const onList = location.pathname.replace(/\/$/, '') === list.pathname.replace(/\/$/, '');
    if (onList) {
        const remember = () => {
            try { sessionStorage.setItem(key, location.pathname + location.search); } catch (_) {}
        };
        remember();
        document.addEventListener('click', remember, true);
        document.addEventListener('contextmenu', remember, true);
        window.addEventListener('pagehide', remember);
    } else {
        try {
            const saved = new URL(sessionStorage.getItem(key) || list.href, location.origin);
            if (saved.origin === list.origin && saved.pathname === list.pathname) {
                document.querySelectorAll('[data-people-return]').forEach(link => { link.href = saved.href; });
            }
        } catch (_) {}
    }
})();
