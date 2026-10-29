(() => {
    const root = document.documentElement;
    const preferenceKey = 'sge.sidebar.collapsed';
    let desktopCollapsed = true;
    try {
        desktopCollapsed = window.localStorage.getItem(preferenceKey) !== '0';
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
        let pointerInside = false;
        let focusInside = false;
        let previewDismissed = false;
        const submenus = Array.from(sidebar.querySelectorAll('[data-toggle="collapse"][data-target]'))
            .map((control) => ({ control, panel: document.querySelector(control.dataset.target) }))
            .filter(({ panel }) => panel);
        sidebar.querySelectorAll('a[href]').forEach((link) => {
            if (!link.hasAttribute('aria-label')) {
                link.setAttribute('aria-label', link.textContent.replace(/\s+/g, ' ').trim());
            }
        });
        const savePreference = () => {
            try {
                window.localStorage.setItem(preferenceKey, desktopCollapsed ? '1' : '0');
            } catch (_) {}
        };
        const render = ({ focusMenu = false, restoreFocus = false } = {}) => {
            const preview = !previewDismissed && (pointerInside || focusInside);
            const open = desktop.matches ? !desktopCollapsed || preview : mobileOpen;
            const hadMenuFocus = sidebar.contains(document.activeElement);
            content?.removeAttribute('inert');
            if (restoreFocus || (!desktop.matches && !open && hadMenuFocus) || (desktop.matches && !open && document.activeElement === close)) {
                const focusTarget = desktop.matches ? document.getElementById('main-content') : toggle;
                focusTarget?.focus({ preventScroll: true });
            }
            root.toggleAttribute('data-sidebar-collapsed', desktopCollapsed);
            root.toggleAttribute('data-sidebar-expanded', desktop.matches && open);
            document.body.classList.toggle('sge-mobile-menu-open', !desktop.matches && open);
            sidebar.toggleAttribute('inert', !desktop.matches && !open);
            sidebar.setAttribute('aria-hidden', String(!desktop.matches && !open));
            submenus.forEach(({ control, panel }) => {
                const compact = desktop.matches && !open;
                panel.toggleAttribute('inert', compact);
                if (compact) panel.setAttribute('aria-hidden', 'true');
                else panel.removeAttribute('aria-hidden');
                const expanded = panel.classList.contains('show')
                    || (panel.classList.contains('collapsing') && control.getAttribute('aria-expanded') === 'true');
                control.setAttribute('aria-expanded', String(!compact && expanded));
            });
            toggle.setAttribute('aria-expanded', String(!desktop.matches && open));
            const label = !desktop.matches && open ? toggle.dataset.closeLabel : toggle.dataset.openLabel;
            toggle.setAttribute('aria-label', label);
            toggle.title = label;
            if (close) {
                close.setAttribute('aria-label', desktop.matches ? toggle.dataset.collapseLabel : toggle.dataset.closeLabel);
                close.title = close.getAttribute('aria-label');
            }
            if (backdrop) backdrop.hidden = desktop.matches || !open;
            if (!desktop.matches && open) {
                if (focusMenu) close?.focus();
                content?.setAttribute('inert', '');
            }
        };
        const closeMenu = ({ restoreFocus = true } = {}) => {
            if (desktop.matches) {
                desktopCollapsed = true;
                previewDismissed = true;
                pointerInside = false;
                focusInside = false;
                savePreference();
            } else {
                mobileOpen = false;
            }
            render({ restoreFocus });
        };
        toggle.addEventListener('click', () => {
            if (desktop.matches) return;
            mobileOpen = !mobileOpen;
            render({ focusMenu: mobileOpen });
        });
        close?.addEventListener('click', closeMenu);
        backdrop?.addEventListener('click', closeMenu);
        sidebar.addEventListener('pointerenter', (event) => {
            if (!desktop.matches || event.pointerType === 'touch') return;
            pointerInside = true;
            previewDismissed = false;
            render();
        });
        sidebar.addEventListener('pointerleave', () => {
            pointerInside = false;
            if (!focusInside) previewDismissed = false;
            render();
        });
        sidebar.addEventListener('focusin', () => {
            if (!desktop.matches) return;
            focusInside = true;
            previewDismissed = false;
            render();
        });
        sidebar.addEventListener('focusout', (event) => {
            if (sidebar.contains(event.relatedTarget)) return;
            focusInside = false;
            if (!pointerInside) previewDismissed = false;
            render();
        });
        sidebar.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => {
                if (!desktop.matches && !link.getAttribute('href').startsWith('#')) closeMenu();
            });
        });
        const viewportChanged = () => {
            mobileOpen = false;
            pointerInside = false;
            focusInside = desktop.matches && sidebar.contains(document.activeElement);
            previewDismissed = false;
            sidebar.classList.remove('toggled');
            document.body.classList.remove('sidebar-toggled');
            render();
        };
        if (desktop.addEventListener) desktop.addEventListener('change', viewportChanged);
        else desktop.addListener(viewportChanged);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && (desktop.matches
                ? root.hasAttribute('data-sidebar-expanded') && (desktopCollapsed || sidebar.contains(document.activeElement))
                : mobileOpen)) {
                event.preventDefault();
                closeMenu({ restoreFocus: !desktop.matches || sidebar.contains(document.activeElement) });
            } else if (!desktop.matches && mobileOpen && event.key === 'Tab') {
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
