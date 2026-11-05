(() => {
    'use strict';
    const initialize = () => {
        const wrapper = document.getElementById('history-years');
        const settings = document.getElementById('history-editor-config');
        const template = document.getElementById('history-component-template');
        const form = wrapper?.closest('form');
        if (!wrapper || !settings || !template || !form) return;
        const config = JSON.parse(settings.textContent);
        const cards = () => Array.from(wrapper.querySelectorAll('[data-history-year]'));
        let nextTableId = cards().length;
        const stageOf = card => config.fixedStage || Object.entries(config.stageLabels).find(([, label]) => label === card.querySelector('[data-history-stage]')?.value)?.[0] || 'fundamental';
        const catalogOf = card => config.catalogs[stageOf(card)]?.formations?.formacao_geral_basica?.areas || [];

        function labelFields(scope) {
            scope.querySelectorAll('.form-group').forEach(group => {
                const label = group.querySelector('label');
                const field = group.querySelector('input:not([type="hidden"]),select,textarea');
                if (!label || !field?.name) return;
                field.id = 'history-' + field.name.replace(/[^a-zA-Z0-9]+/g, '-');
                label.htmlFor = field.id;
            });
        }
        function refreshRow(row, reset = false, configure = false) {
            const card = row.closest('[data-history-year]');
            const stage = stageOf(card);
            const areas = catalogOf(card);
            const formation = row.querySelector('[data-history-formation]');
            let area = row.querySelector('[data-history-area]');
            const select = row.querySelector('[data-history-component-select]');
            const input = row.querySelector('[data-history-component-input]');
            if (configure) {
                const currentFormation = formation.value;
                const formations = stage === 'tecnico' ? [config.flexibleFormations[stage]] : ['Formação Geral Básica', config.flexibleFormations[stage] || 'Parte Diversificada'];
                formation.replaceChildren(...formations.map(value => new Option(config.formationLabels[value] || value, value)));
                formation.value = formations.includes(currentFormation) ? currentFormation : formations[0];
                const currentArea = area.value;
                const replacement = document.createElement(stage === 'tecnico' ? 'input' : 'select');
                replacement.name = area.name;
                replacement.className = area.className;
                replacement.dataset.historyArea = '';
                replacement.setAttribute('aria-label', config.area);
                replacement.required = true;
                if (stage === 'tecnico') replacement.value = currentArea;
                else {
                    replacement.add(new Option(config.select, ''));
                    areas.forEach(item => replacement.add(new Option(item.name, item.name)));
                    replacement.value = areas.some(item => item.name === currentArea) ? currentArea : '';
                }
                area.replaceWith(replacement);
                area = replacement;
            }
            const basic = formation.value === 'Formação Geral Básica';
            const current = reset ? '' : input.value;
            select.replaceChildren(new Option(config.select, ''));
            (areas.find(item => item.name === area.value)?.components || []).forEach(name => select.add(new Option(name, name)));
            if (current && !Array.from(select.options).some(option => option.value === current)) select.add(new Option(current, current));
            select.value = current;
            select.disabled = !basic;
            select.required = basic;
            select.hidden = !basic;
            input.type = basic ? 'hidden' : 'text';
            if (reset) input.value = '';
            else if (basic) input.value = select.value;
        }
        function refreshCard(card) {
            const label = card.querySelector('[name$="[grade_phase]"]')?.value.trim() || config.newYear;
            card.querySelector('.sge-history-year-summary strong').textContent = label;
            card.querySelector('[data-history-year-preview]').textContent = ['year', 'school_name', 'workload_hours'].map(name => {
                const value = card.querySelector(`[name^="years["][name$="[${name}]"]`)?.value.trim();
                return value ? value + (name === 'workload_hours' ? ' h' : '') : '';
            }).filter(Boolean).join(' · ') || config.preview;
            card.querySelector('table[data-sge-client-pagination]').setAttribute('aria-label', config.components + ' — ' + label);
            const mode = card.querySelector('[name$="[transcript_mode]"]')?.value || 'detailed';
            card.querySelector('[data-history-global-hint]').hidden = mode === 'detailed';
            const grade = card.querySelector('[data-history-grade]');
            if (grade) {
                const stage = stageOf(card);
                grade.required = ['fundamental', 'medio'].includes(stage);
                Array.from(grade.options).forEach(option => { if (option.value) option.disabled = !grade.required || Number(option.value) > (stage === 'medio' ? 3 : 9); });
                if (grade.selectedOptions[0]?.disabled) grade.value = '';
            }
        }
        function refreshEmpty(card) {
            const body = card.querySelector('table tbody');
            body.querySelector('[data-history-empty]')?.remove();
            if (!body.querySelector('[data-history-component]')) {
                const row = body.insertRow();
                row.dataset.historyEmpty = '';
                const cell = row.insertCell();
                cell.colSpan = 5;
                cell.className = 'text-muted';
                cell.textContent = config.empty;
            }
        }
        function renumber() {
            cards().forEach((card, yearIndex) => {
                card.querySelectorAll('[name^="years["]').forEach(field => { field.name = field.name.replace(/^years\[\d+\]/, `years[${yearIndex}]`); });
                card.querySelectorAll('[data-history-component]').forEach((row, rowIndex) => {
                    row.querySelectorAll('[name^="year_components["]').forEach(field => { field.name = field.name.replace(/^year_components\[[^\]]+\]\[[^\]]+\]/, `year_components[${yearIndex}][${rowIndex}]`); });
                });
                card.querySelector('[data-remove-history-year]').disabled = cards().length <= 1;
                refreshCard(card);
            });
            labelFields(wrapper);
        }
        form.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            const card = button.closest('[data-history-year]');
            if (button.matches('[data-add-history-component]') && card) {
                const yearIndex = cards().indexOf(card);
                const rowIndex = card.querySelectorAll('[data-history-component]').length;
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('[data-history-component]');
                row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace('__YEAR__', yearIndex).replace('__ROW__', rowIndex); });
                card.querySelector('[data-history-empty]')?.remove();
                card.querySelector('tbody').appendChild(fragment);
                refreshRow(row, true, true);
                renumber();
                requestAnimationFrame(() => row.querySelector('[data-history-area]').focus());
            } else if (button.matches('[data-remove-history-row]') && card) {
                button.closest('[data-history-component]').remove();
                refreshEmpty(card);
                renumber();
            } else if (button.matches('[data-add-history-year]')) {
                const clone = cards().at(-1).cloneNode(true);
                clone.querySelectorAll('input,textarea').forEach(field => { field.value = ''; });
                clone.querySelectorAll('select').forEach(field => { field.value = ''; });
                clone.querySelectorAll('details').forEach(details => { details.open = false; });
                clone.querySelectorAll('[data-sge-client-pagination-nav]').forEach(nav => nav.remove());
                const table = clone.querySelector('table');
                table.id = `history-components-${nextTableId++}`;
                delete table.dataset.sgePaginationReady;
                table.querySelector('tbody').replaceChildren();
                clone.querySelector('[name$="[source]"]').value = 'manual';
                clone.querySelector('[name$="[country]"]').value = 'Brasil';
                clone.querySelector('[name$="[transcript_mode]"]').value = 'detailed';
                wrapper.appendChild(clone);
                refreshEmpty(clone);
                renumber();
                window.sgeInitializeTablePagination?.(clone);
                clone.open = true;
                clone.querySelector('[name$="[year]"]').focus();
            } else if (button.matches('[data-remove-history-year]') && card && cards().length > 1) {
                const savedId = card.querySelector('[name$="[id]"]').value;
                const remove = () => {
                    if (!card.isConnected) return;
                    if (savedId) {
                        const field = document.createElement('input');
                        field.type = 'hidden'; field.name = 'removed_year_ids[]'; field.value = savedId;
                        form.appendChild(field);
                    }
                    card.remove(); renumber();
                };
                if (savedId) window.sgeConfirmAction?.(config.confirmRemove, remove, button);
                else remove();
            }
        });
        wrapper.addEventListener('input', event => {
            const card = event.target.closest('[data-history-year]');
            if (card) refreshCard(card);
        });
        wrapper.addEventListener('change', event => {
            const card = event.target.closest('[data-history-year]');
            if (!card) return;
            if (event.target.matches('[data-history-stage]')) card.querySelectorAll('[data-history-component]').forEach(row => refreshRow(row, false, true));
            const row = event.target.closest('[data-history-component]');
            if (row && event.target.matches('[data-history-formation],[data-history-area]')) refreshRow(row, true);
            if (row && event.target.matches('[data-history-component-select]')) row.querySelector('[data-history-component-input]').value = event.target.value;
            refreshCard(card);
        });
        let pendingInvalid = false, presentingInvalid = false;
        form.addEventListener('invalid', event => {
            if (presentingInvalid) return;
            event.preventDefault();
            if (pendingInvalid) return;
            pendingInvalid = true;
            requestAnimationFrame(() => {
                pendingInvalid = false;
                let field = event.target;
                const row = field.closest('[data-history-component]');
                if (field.type === 'hidden' && row) field = row.querySelector('[data-history-component-select]');
                if (row?.hidden) {
                    const table = row.closest('table');
                    const page = form.querySelector(`select[aria-controls="${CSS.escape(table.id)}"]`);
                    if (page) {
                        page.value = String(Math.floor(Array.from(row.parentElement.children).indexOf(row) / 10) + 1);
                        page.dispatchEvent(new Event('change'));
                    }
                }
                for (let parent = field.parentElement; parent; parent = parent.parentElement) if (parent.tagName === 'DETAILS') parent.open = true;
                field.focus();
                presentingInvalid = true; field.reportValidity(); presentingInvalid = false;
            });
        }, true);
        wrapper.querySelectorAll('[data-history-component]').forEach(row => refreshRow(row));
        renumber();
        if (config.errors[0]) {
            const name = config.errors[0].replace(/\.([^.]*)/g, '[$1]');
            form.elements.namedItem(name)?.dispatchEvent(new Event('invalid', { cancelable: true }));
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
