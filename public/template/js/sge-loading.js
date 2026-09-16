(() => {
    const label = document.currentScript.dataset.loadingLabel;
    const operations = new Set();
    let bar;
    window.sgeLoading = {
        start() {
            const operation = {};
            operations.add(operation);
            if (!bar?.isConnected) {
                bar = document.createElement('div');
                bar.className = 'sge-submit-loading-bar';
                bar.setAttribute('role', 'progressbar');
                bar.setAttribute('aria-label', label);
                document.body.appendChild(bar);
            }
            return () => {
                operations.delete(operation);
                if (!operations.size) {
                    bar?.remove();
                    bar = null;
                }
            };
        },
        reset() {
            operations.clear();
            bar?.remove();
            bar = null;
        }
    };

    let registered = false;
    const pending = new WeakMap();
    const register = () => {
        if (registered) return;
        registered = true;
        window.Livewire.hook('commit', ({component, succeed, fail}) => {
            const finish = window.sgeLoading.start();
            const element = component.el;
            let state = pending.get(element);
            if (!state) {
                state = {count: 0, previous: element.getAttribute('aria-busy')};
                pending.set(element, state);
            }
            state.count++;
            element.setAttribute('aria-busy', 'true');
            let finished = false;
            const done = () => {
                if (finished) return;
                finished = true;
                finish();
                if (--state.count === 0) {
                    if (state.previous === null) element.removeAttribute('aria-busy');
                    else element.setAttribute('aria-busy', state.previous);
                    pending.delete(element);
                }
            };
            succeed(done);
            fail(done);
        });
    };
    if (window.Livewire) register();
    else document.addEventListener('livewire:init', register, {once: true});

    document.addEventListener('click', (event) => {
        const pagination = event.target.closest('.pagination');
        const component = pagination?.closest('[wire\\:id]');
        if (component && pending.has(component)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    window.addEventListener('pageshow', () => window.sgeLoading.reset());
})();
