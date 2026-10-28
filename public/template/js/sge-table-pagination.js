(function () {
    'use strict';

    const script = document.currentScript;
    const labels = {
        previous: script?.dataset.previous || 'Anterior',
        next: script?.dataset.next || 'Próxima',
        goToPage: script?.dataset.goToPage || 'Ir para página',
        page: script?.dataset.page || 'Página :current de :total',
        range: script?.dataset.range || 'Registros :first–:last de :total',
        navigation: script?.dataset.navigation || 'Paginação da tabela',
    };

    const format = (pattern, values) => Object.entries(values).reduce(
        (value, [key, replacement]) => value.replaceAll(`:${key}`, String(replacement)),
        pattern,
    );

    document.querySelectorAll('table[data-sge-client-pagination]').forEach((table, tableIndex) => {
        const body = table.tBodies[0];
        const pageSize = Math.max(1, Number.parseInt(table.dataset.sgeClientPagination, 10) || 20);

        if (!body) {
            return;
        }

        table.id ||= `sge-paginated-table-${tableIndex + 1}`;
        const tableLabel = table.getAttribute('aria-label')
            || table.querySelector('caption')?.textContent
            || table.closest('.card')?.querySelector('h2, h3')?.textContent;
        const navigation = document.createElement('nav');
        navigation.className = 'flex-wrap align-items-center justify-content-between mt-3';
        navigation.setAttribute('aria-label', tableLabel
            ? `${labels.navigation}: ${tableLabel.trim()}`
            : `${labels.navigation} ${tableIndex + 1}`);

        const status = document.createElement('span');
        status.className = 'small text-muted mr-3 mb-2';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        const controls = document.createElement('div');
        controls.className = 'd-flex align-items-center mb-2';

        const button = (text) => {
            const element = document.createElement('button');
            element.type = 'button';
            element.className = 'btn btn-sm btn-outline-primary';
            element.textContent = text;
            element.setAttribute('aria-controls', table.id);
            return element;
        };

        const previous = button(labels.previous);
        const next = button(labels.next);
        const pageSelect = document.createElement('select');
        pageSelect.className = 'form-control form-control-sm mx-2';
        pageSelect.style.width = 'auto';
        pageSelect.setAttribute('aria-label', labels.goToPage);
        pageSelect.setAttribute('aria-controls', table.id);

        controls.append(previous, pageSelect, next);
        navigation.append(status, controls);
        (table.closest('.table-responsive') || table).after(navigation);

        let currentPage = 1;
        let previousPageCount = 0;
        const render = (showLastPage = false) => {
            const rows = Array.from(body.rows);
            const pageCount = Math.ceil(rows.length / pageSize);
            const hasPages = pageCount > 1;
            navigation.classList.toggle('d-flex', hasPages);
            navigation.hidden = !hasPages;

            if (!hasPages) {
                currentPage = 1;
                rows.forEach((row) => { row.hidden = false; });
                previousPageCount = pageCount;
                return;
            }

            if (pageCount !== previousPageCount) {
                pageSelect.replaceChildren();
                for (let number = 1; number <= pageCount; number++) {
                    const option = document.createElement('option');
                    option.value = String(number);
                    option.textContent = format(labels.page, { current: number, total: pageCount });
                    pageSelect.appendChild(option);
                }
                previousPageCount = pageCount;
            }

            currentPage = showLastPage ? pageCount : Math.min(currentPage, pageCount);
            const first = (currentPage - 1) * pageSize;
            const last = Math.min(first + pageSize, rows.length);
            rows.forEach((row, index) => { row.hidden = index < first || index >= last; });
            previous.disabled = currentPage === 1;
            next.disabled = currentPage === pageCount;
            pageSelect.value = String(currentPage);
            status.textContent = format(labels.range, { first: first + 1, last, total: rows.length });
        };

        previous.addEventListener('click', () => {
            currentPage--;
            render();
            pageSelect.focus();
        });
        next.addEventListener('click', () => {
            currentPage++;
            render();
            pageSelect.focus();
        });
        pageSelect.addEventListener('change', () => {
            currentPage = Number(pageSelect.value);
            render();
        });

        render();
        new MutationObserver((changes) => {
            const rowAdded = changes.some((change) => Array.from(change.addedNodes)
                .some((node) => node.nodeName === 'TR'));
            render(rowAdded);
        }).observe(body, { childList: true });
    });
}());
