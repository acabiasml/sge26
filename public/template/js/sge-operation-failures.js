(() => {
    'use strict';
    const script = document.currentScript;
    if (!script?.dataset.reportUrl || window.beabaFailureMonitor) return;
    const endpoint = new URL(script.dataset.reportUrl, location.origin);
    const base = new URL(script.dataset.appUrl, location.origin).pathname.replace(/\/$/, '');
    const nativeFetch = window.fetch.bind(window);
    const pending = [];
    const seen = new Map();
    const observed = new WeakMap();
    let flushing = false;
    let serverHandledUntil = 0;
    let lastOperation = '';
    const selector = '[role="alert"],.alert-danger,[data-listing-error],[data-seen-error],#target-status,#participant-results,#import-status';
    const failed = text => /não\s+(?:foi|é|pode|consegui)|bloquead|falh|impossív|unable|could not|cannot|not allowed|non\s+(?:è|e|può|puoi|consentito)|error|errore|impossibile/i.test(text);
    const text = value => String(value ?? '').replace(/\s+/g, ' ').trim();
    const pathname = value => {
        try {
            const url = new URL(value, location.href);
            if (url.origin !== location.origin || (base && !url.pathname.startsWith(base + '/') && url.pathname !== base)) return null;
            return url.pathname.replace(/(\/(?:reset-password|password\/reset)\/)[^/]+/gi, '$1[omitido]');
        } catch { return null; }
    };
    const uuid = () => window.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const n = Math.floor(Math.random() * 16); return (c === 'x' ? n : (n & 3) | 8).toString(16);
    });
    const operation = () => [lastOperation, text(document.querySelector('h1')?.textContent) || text(document.title)].filter(Boolean).join(' · ');
    const flush = async () => {
        if (flushing || !pending.length || !navigator.onLine) return;
        flushing = true;
        try {
            while (pending.length) {
                const response = await nativeFetch(endpoint.href, {
                    method: 'POST', credentials: 'same-origin', keepalive: true,
                    headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body: JSON.stringify(pending[0]),
                });
                if (response.status === 401 || response.status === 419 || response.status === 429 || response.status >= 500) break;
                pending.shift();
            }
        } catch { /* Tenta novamente quando a conexão voltar. */ }
        finally { flushing = false; }
    };
    const report = data => {
        const message = text(data.message).slice(0, 8000);
        const path = pathname(data.path || location.href);
        if (!message || !path || path === endpoint.pathname) return;
        const signature = [data.category, path, message].join('|');
        if (Date.now() - (seen.get(signature) || 0) < 1500) return;
        seen.set(signature, Date.now());
        if (seen.size > 100) seen.delete(seen.keys().next().value);
        if (pending.length >= 20) pending.shift();
        pending.push({event_uuid: uuid(), category: data.category || 'browser', operation: text(data.operation || operation()).slice(0, 255),
            path, method: String(data.method || 'GET').toUpperCase(), message, reason: text(data.reason).slice(0, 4000), status_code: data.status_code ?? null});
        void flush();
    };
    window.beabaFailureMonitor = {report};
    document.addEventListener('click', event => {
        const button = event.target.closest('button,input[type="submit"],a.btn');
        if (!button) return;
        lastOperation = text(button.textContent || button.getAttribute('aria-label') || button.getAttribute('title')).slice(0, 220);
    }, true);
    document.addEventListener('submit', event => {
        lastOperation = text(event.submitter?.textContent || lastOperation || document.querySelector('h1')?.textContent).slice(0, 220);
    }, true);
    window.fetch = async (input, init) => {
        const url = typeof input === 'string' || input instanceof URL ? String(input) : input?.url;
        const path = pathname(url);
        const method = String(init?.method || input?.method || 'GET').toUpperCase();
        try {
            const response = await nativeFetch(input, init);
            if (path && path !== endpoint.pathname) {
                if (response.headers.get('X-Beaba-Failure-ID')) serverHandledUntil = Date.now() + 800;
                else if (!response.ok) {
                    report({category: 'network', message: 'Não foi possível concluir a solicitação ao servidor.',
                        reason: 'Resposta HTTP ' + response.status + '.', path, method, status_code: response.status});
                }
            }
            return response;
        } catch (error) {
            if (error?.name !== 'AbortError' && path && path !== endpoint.pathname) {
                report({category: 'network', message: 'Não foi possível comunicar com o servidor.', reason: String(error?.name || 'Falha de rede'), path, method, status_code: 0});
            }
            throw error;
        }
    };
    const inspect = element => {
        if (!(element instanceof Element) || element.closest('[data-no-failure-monitor]') || element.dataset.operationFailureRecorded) return;
        const style = getComputedStyle(element);
        if (element.hidden || !element.getClientRects().length || style.display === 'none' || style.visibility === 'hidden') return;
        const message = text(element.textContent);
        if (!message || (!element.matches('.alert-danger,[data-listing-error],[data-seen-error]') && !failed(message))) return;
        if (observed.get(element) === message) return;
        observed.set(element, message);
        if (Date.now() < serverHandledUntil) return;
        report({category: 'browser', message, reason: 'Mensagem exibida pela interface durante a operação.'});
    };
    const scan = root => {
        if (!(root instanceof Element)) return;
        const owner = root.closest(selector);
        if (owner) inspect(owner);
        root.querySelectorAll(selector).forEach(inspect);
    };
    const start = () => {
        scan(document.body);
        new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === 'characterData') scan(mutation.target.parentElement);
                else {
                    scan(mutation.target);
                    mutation.addedNodes.forEach(node => {if (node instanceof Element) scan(node);});
                }
            });
        }).observe(document.body, {subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['hidden', 'class', 'style']});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true}); else start();
    const invalidForms = new Map();
    document.addEventListener('invalid', event => {
        const field = event.target;
        const form = field.form;
        if (!form || form.closest('[data-no-failure-monitor]')) return;
        const label = text(form.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.textContent || field.getAttribute('aria-label') || field.name || 'Campo obrigatório');
        const message = /password|senha|token|secret/i.test(field.name || '') || field.type === 'password' ? 'Confira o campo protegido.' : field.validationMessage;
        if (!invalidForms.has(form)) {
            invalidForms.set(form, []);
            setTimeout(() => {
                const messages = invalidForms.get(form) || [];
                invalidForms.delete(form);
                report({category: 'validation', operation: operation(), path: form.action || location.href,
                    method: form.querySelector('input[name="_method"]')?.value || form.method || 'POST',
                    message: messages.join(' '), reason: 'Validação dos campos pelo navegador; solicitação não enviada.'});
            }, 100);
        }
        invalidForms.get(form).push(label + ': ' + message);
    }, true);
    const nativeAlert = window.alert.bind(window);
    window.alert = message => {if (failed(String(message))) report({category: 'browser', message}); return nativeAlert(message);};
    window.addEventListener('beaba:operation-failed', event => report(event.detail || {}));
    window.addEventListener('online', flush);
    window.addEventListener('pageshow', flush);
    window.setInterval(flush, 30000);
})();
