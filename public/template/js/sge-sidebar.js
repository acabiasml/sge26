(() => {
    const root = document.documentElement;
    root.setAttribute('data-sidebar-collapsed', '');

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('sidebarToggleControl');
        const brand = document.getElementById('sidebarBrandControl');
        const sidebar = document.getElementById('accordionSidebar');
        const menuItems = sidebar?.querySelector('.sge-sidebar-items');
        const content = document.getElementById('content-wrapper');
        const backdrop = document.querySelector('.sge-sidebar-backdrop');
        if (!toggle || !brand || !menuItems) return;

        const desktop = window.matchMedia('(min-width: 992px)');
        let mobileOpen = false;
        let pointerInside = false;
        let keyboardInside = false;
        let touchOpen = false;
        let hoverTimer;
        const cancelHover = () => {
            window.clearTimeout(hoverTimer);
            hoverTimer = undefined;
        };
        const submenus = Array.from(sidebar.querySelectorAll('[data-toggle="collapse"][data-target]'))
            .map((control) => ({ control, panel: document.querySelector(control.dataset.target) }))
            .filter(({ panel }) => panel);
        sidebar.querySelectorAll('a[href]').forEach((link) => {
            if (!link.hasAttribute('aria-label')) {
                link.setAttribute('aria-label', link.textContent.replace(/\s+/g, ' ').trim());
            }
        });
        const render = ({ focusMenu = false, restoreFocus = false } = {}) => {
            const open = desktop.matches ? pointerInside || keyboardInside || touchOpen : mobileOpen;
            const hadMenuFocus = sidebar.contains(document.activeElement);
            content?.removeAttribute('inert');
            if (restoreFocus || (!desktop.matches && !open && hadMenuFocus)) {
                const focusTarget = desktop.matches ? brand : toggle;
                focusTarget.focus({ preventScroll: true });
            }
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
            brand.setAttribute('aria-expanded', String(open));
            const brandLabel = desktop.matches
                ? (open ? brand.dataset.collapseLabel : brand.dataset.expandLabel)
                : brand.dataset.closeLabel;
            brand.setAttribute('aria-label', brandLabel);
            brand.title = brandLabel;
            if (backdrop) backdrop.hidden = desktop.matches || !open;
            if (!desktop.matches && open) {
                if (focusMenu) brand.focus();
                content?.setAttribute('inert', '');
            }
        };
        const closeMenu = ({ restoreFocus = true } = {}) => {
            cancelHover();
            if (desktop.matches) {
                pointerInside = false;
                keyboardInside = false;
                touchOpen = false;
            } else {
                mobileOpen = false;
            }
            render({ restoreFocus });
        };
        toggle.addEventListener('click', () => {
            if (desktop.matches) return;
            mobileOpen = true;
            render({ focusMenu: true });
        });
        brand.addEventListener('click', (event) => {
            if (!desktop.matches || root.hasAttribute('data-sidebar-expanded')) {
                closeMenu();
                return;
            }
            cancelHover();
            keyboardInside = event.detail === 0;
            touchOpen = event.pointerType === 'touch';
            pointerInside = !keyboardInside && !touchOpen;
            render();
        });
        backdrop?.addEventListener('click', closeMenu);
        document.addEventListener('pointerdown', (event) => {
            if (touchOpen && !sidebar.contains(event.target)) closeMenu({ restoreFocus: false });
        });
        sidebar.addEventListener('pointerenter', (event) => {
            if (desktop.matches && pointerInside && event.pointerType !== 'touch') cancelHover();
        });
        menuItems.addEventListener('pointerenter', (event) => {
            if (!desktop.matches || event.pointerType === 'touch') return;
            cancelHover();
            const reveal = () => {
                pointerInside = true;
                render();
            };
            if (root.hasAttribute('data-sidebar-expanded')) reveal();
            else hoverTimer = window.setTimeout(reveal, 180);
        });
        sidebar.addEventListener('pointerleave', (event) => {
            if (!desktop.matches || event.pointerType === 'touch') return;
            cancelHover();
            hoverTimer = window.setTimeout(() => {
                pointerInside = false;
                render();
            }, 120);
        });
        sidebar.addEventListener('focusin', (event) => {
            if (!desktop.matches || event.target === brand) return;
            keyboardInside = event.target.matches(':focus-visible');
            render();
        });
        sidebar.addEventListener('focusout', (event) => {
            if (sidebar.contains(event.relatedTarget)) return;
            keyboardInside = false;
            render();
        });
        sidebar.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => {
                if (!desktop.matches && !link.getAttribute('href').startsWith('#')) closeMenu();
            });
        });
        const viewportChanged = () => {
            cancelHover();
            mobileOpen = false;
            pointerInside = false;
            keyboardInside = desktop.matches && menuItems.contains(document.activeElement) && document.activeElement.matches(':focus-visible');
            touchOpen = false;
            sidebar.classList.remove('toggled');
            document.body.classList.remove('sidebar-toggled');
            render();
        };
        if (desktop.addEventListener) desktop.addEventListener('change', viewportChanged);
        else desktop.addListener(viewportChanged);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') cancelHover();
            if (event.key === 'Escape' && (desktop.matches
                ? root.hasAttribute('data-sidebar-expanded')
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
