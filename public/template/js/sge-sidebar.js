(() => {
    const root = document.documentElement;
    const preferenceKey = 'sge.sidebar.collapsed';
    let desktopCollapsed = false;
    try {
        desktopCollapsed = window.localStorage.getItem(preferenceKey) === '1';
    } catch (_) {
        // A navegação continua funcionando quando o armazenamento está bloqueado.
    }
    root.toggleAttribute('data-sidebar-collapsed', desktopCollapsed);

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('sidebarToggleControl');
        const close = document.getElementById('sidebarCloseControl');
        const sidebar = document.getElementById('accordionSidebar');
        const content = document.getElementById('content-wrapper');
        const backdrop = document.querySelector('.sge-sidebar-backdrop');
        if (!toggle || !sidebar) return;

        const desktop = window.matchMedia('(min-width: 992px)');
        let mobileOpen = false;
        const savePreference = () => {
            try {
                window.localStorage.setItem(preferenceKey, desktopCollapsed ? '1' : '0');
            } catch (_) {}
        };
        const render = ({ focusMenu = false, restoreFocus = false } = {}) => {
            const open = desktop.matches ? !desktopCollapsed : mobileOpen;
            const hadMenuFocus = sidebar.contains(document.activeElement);
            content?.removeAttribute('inert');
            if (restoreFocus || (!open && hadMenuFocus) || (desktop.matches && document.activeElement === close)) {
                toggle.focus();
            }
            root.toggleAttribute('data-sidebar-collapsed', desktopCollapsed);
            document.body.classList.toggle('sge-mobile-menu-open', !desktop.matches && open);
            sidebar.toggleAttribute('inert', !open);
            sidebar.setAttribute('aria-hidden', String(!open));
            toggle.setAttribute('aria-expanded', String(open));
            const label = open ? toggle.dataset.closeLabel : toggle.dataset.openLabel;
            toggle.setAttribute('aria-label', label);
            toggle.title = label;
            if (backdrop) backdrop.hidden = desktop.matches || !open;
            if (!desktop.matches && open) {
                if (focusMenu) close?.focus();
                content?.setAttribute('inert', '');
            }
        };
        const closeMenu = () => {
            if (desktop.matches) {
                desktopCollapsed = true;
                savePreference();
            } else {
                mobileOpen = false;
            }
            render({ restoreFocus: true });
        };
        toggle.addEventListener('click', () => {
            if (desktop.matches) {
                desktopCollapsed = !desktopCollapsed;
                savePreference();
            } else {
                mobileOpen = !mobileOpen;
            }
            render({ focusMenu: !desktop.matches && mobileOpen });
        });
        close?.addEventListener('click', closeMenu);
        backdrop?.addEventListener('click', closeMenu);
        sidebar.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => {
                if (!desktop.matches && !link.getAttribute('href').startsWith('#')) closeMenu();
            });
        });
        const viewportChanged = () => {
            mobileOpen = false;
            sidebar.classList.remove('toggled');
            document.body.classList.remove('sidebar-toggled');
            render();
        };
        if (desktop.addEventListener) desktop.addEventListener('change', viewportChanged);
        else desktop.addListener(viewportChanged);

        document.addEventListener('keydown', (event) => {
            if (desktop.matches || !mobileOpen) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeMenu();
            } else if (event.key === 'Tab') {
                const items = Array.from(sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]'))
                    .filter((item) => item.getClientRects().length > 0);
                const first = items[0];
                const last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last?.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first?.focus();
                }
            }
        });
        render();
    });
})();
