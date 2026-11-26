(() => {
    'use strict';
    const initialize = () => {
        const wrapper = document.getElementById('history-years');
        const settings = document.getElementById('history-editor-config');
        const template = document.getElementById('history-component-template');
        const form = wrapper?.closest('form');
        if (!wrapper || !settings || !template || !form) return;
        const config = JSON.parse(settings.textContent);
        const alphabet = new Intl.Collator(document.documentElement.lang || 'pt-BR', { sensitivity: 'base', numeric: true });
        const sorted = values => Array.from(new Set(values)).sort(alphabet.compare);
        const cards = () => Array.from(wrapper.querySelectorAll('[data-history-year]'));
        let nextTableId = cards().length;
        const stageOf = card => config.fixedStage || Object.entries(config.stageLabels).find(([, label]) => label === card.querySelector('[data-history-stage]')?.value)?.[0] || 'fundamental';
        const catalogOf = card => config.catalogs[stageOf(card)] || [];

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
            const current = reset ? '' : input.value;
            const currentArea = area.value;
            if (configure) {
                const currentFormation = formation.value;
                const formations = Array.from(new Set([config.formations[stage]?.[0] || 'Formação Geral Básica', ...areas.map(item => item.formation)]));
                if (!reset && currentFormation && !formations.includes(currentFormation)) formations.push(currentFormation);
                formations.sort((a, b) => alphabet.compare(config.formationLabels[a] || a, config.formationLabels[b] || b));
                formation.replaceChildren(...formations.map(value => new Option(config.formationLabels[value] || value, value)));
                formation.value = formations.includes(currentFormation) ? currentFormation : formations[0];
            }
            const availableAreas = Array.from(new Set(areas.filter(item => item.formation === formation.value).map(item => item.area)));
            if (!reset && currentArea) availableAreas.push(currentArea);
            area.replaceChildren(new Option(config.select, ''), ...sorted(availableAreas).map(name => {
                const subtitle = areas.find(item => item.area === name)?.area_subtitle;
                return new Option(subtitle ? name + ' — ' + subtitle : name, name);
            }));
            area.value = Array.from(area.options).some(option => option.value === currentArea) ? currentArea : '';
            const names = areas.filter(item => item.formation === formation.value && item.area === area.value).map(item => item.name);
            if (current) names.push(current);
            select.replaceChildren(new Option(config.select, ''), ...sorted(names).map(name => new Option(name, name)));
            select.value = current;
            select.disabled = false;
            select.required = true;
            select.hidden = false;
            input.type = 'hidden';
            input.value = select.value;
        }

        function refreshCard(card) {
            const label = card.querySelector('[name$="[grade_phase]"]')?.value.trim() || config.newYear;
            card.querySelector('.sge-history-year-summary strong').textContent = label;
            const mode = card.querySelector('[name$="[transcript_mode]"]')?.value || 'detailed';
            const components = card.querySelector('[data-history-components]');
            components.hidden = mode === 'no_transcription';
            components.disabled = mode === 'no_transcription';
            const summary = mode === 'summary';
            card.dataset.historyMode = mode;
            const title = summary ? config.globalComponents : config.components;
            card.querySelector('[data-history-components-title]').textContent = title;
            components.querySelector('[role="region"]').setAttribute('aria-label', title);
            components.querySelector('table').setAttribute('aria-label', title + ' — ' + label);
            components.querySelectorAll('[data-history-detailed-only],[data-history-record-fields]').forEach(element => { element.hidden = summary; });
            components.querySelector('[data-history-empty] td')?.setAttribute('colspan', summary ? '4' : '5');
            const totals = card.querySelector('[data-history-formation-totals]');
            totals.hidden = mode !== 'summary';
            totals.disabled = mode !== 'summary';
            let total = 0, complete = true;
            const enabledFormations = [];
            totals.querySelectorAll('[data-history-formation-total]').forEach(group => {
                const field = group.querySelector('[data-formation-hours]'), toggle = group.querySelector('[data-formation-toggle]');
                const enabled = !toggle || toggle.checked;
                field.disabled = !enabled;
                const formation = group.dataset.historyFormationTotal;
                if (enabled) enabledFormations.push(formation);
                const hasCatalog = catalogOf(card).some(item => item.formation === formation);
                group.querySelector('[data-add-history-component]').disabled = !enabled || !hasCatalog;
                group.querySelector('[data-history-no-catalog]').hidden = !enabled || hasCatalog;
                if (!enabled) return;
                const value = Number(field.value.trim().replace(',', '.'));
                if (field.value.trim() === '' || !Number.isFinite(value) || value < 0) complete = false;
                else total += value;
            });
            components.querySelectorAll('[data-history-component]').forEach(row => {
                const formation = row.querySelector('[data-history-formation]');
                const inactive = summary && !enabledFormations.includes(formation.value);
                row.querySelector('[data-history-formation-inactive]').hidden = !inactive;
                row.querySelectorAll('input,select,textarea').forEach(field => {
                    field.disabled = inactive || (summary && field.name.includes('[record]'));
                });
                Array.from(formation.options).forEach(option => { option.disabled = summary && !enabledFormations.includes(option.value); });
            });
            const annualTotal = card.querySelector('[data-history-total-hours]');
            const calculated = summary && complete;
            if (calculated) {
                if (!annualTotal.readOnly) annualTotal.dataset.manualValue = annualTotal.value;
                annualTotal.value = total.toFixed(2).replace('.', ',');
            } else if (annualTotal.readOnly) {
                annualTotal.value = annualTotal.dataset.manualValue || '';
            }
            annualTotal.readOnly = calculated;
            card.querySelector('[data-history-year-preview]').textContent = ['year', 'school_name', 'workload_hours'].map(name => {
                const value = card.querySelector(`[name^="years["][name$="[${name}]"]`)?.value.trim();
                return value ? value + (name === 'workload_hours' ? ' h' : '') : '';
            }).filter(Boolean).join(' · ') || config.preview;

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
                if (button.dataset.formation) {
                    row.querySelector('[data-history-formation]').value = button.dataset.formation;
                    refreshRow(row, true);
                }
                renumber();
                queueDraft();
                requestAnimationFrame(() => row.querySelector('[data-history-area]').focus());
            } else if (button.matches('[data-remove-history-row]') && card) {
                button.closest('[data-history-component]').remove();
                refreshEmpty(card);
                renumber();
                queueDraft();
            } else if (button.matches('[data-add-history-year]')) {
                const clone = cards().at(-1).cloneNode(true);
                clone.removeAttribute('id');
                clone.querySelectorAll('input,textarea').forEach(field => { if (field.type === 'checkbox') field.checked = false; else field.value = ''; });
                clone.querySelectorAll('select').forEach(field => { field.value = ''; });
                clone.querySelectorAll('details').forEach(details => { details.open = false; });
                clone.querySelectorAll('[data-sge-table-navigation]').forEach(nav => nav.remove());
                const table = clone.querySelector('table');
                table.id = `history-components-${nextTableId++}`;
                table.querySelector('tbody').replaceChildren();
                clone.querySelector('[name$="[source]"]').value = 'manual';
                clone.querySelector('[name$="[country]"]').value = 'Brasil';
                clone.querySelector('[name$="[transcript_mode]"]').value = 'detailed';
                wrapper.appendChild(clone);
                refreshEmpty(clone);
                renumber();
                queueDraft();
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
                    queueDraft();
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
        const saveAlert = form.querySelector('[data-history-save-alert]');
        const saveMessage = saveAlert.querySelector('[data-history-save-message]');
        const loginLink = saveAlert.querySelector('[data-history-login]');
        const saveButton = form.querySelector('button[type="submit"]');
        const saveLabel = saveButton.innerHTML;
        const indicator = form.querySelector('[data-history-draft-indicator]');
        const draftStatus = indicator.querySelector('[data-history-draft-status]');
        const draftIcon = indicator.querySelector('[data-history-draft-icon]');
        const draftLogin = indicator.querySelector('[data-history-draft-login]');
        const draftRetry = indicator.querySelector('[data-history-draft-retry]');
        const draftDiscard = form.querySelector('[data-history-draft-discard]');
        const draftRevision = form.elements.namedItem('history_draft_revision');
        const requestOptions = { headers: { Accept: 'application/json' }, credentials: 'same-origin' };
        const dateFormat = new Intl.DateTimeFormat(document.documentElement.lang || 'pt-BR', {
            dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Cuiaba',
        });
        let refreshSession = false;
        let manualSaving = false;
        let navigating = false;
        let draftConflict = false;
        let draftTimer;
        let draftRequest;
        let changes = 0;
        let savedChanges = 0;

        function showSaveError(messages, showLogin = false) {
            saveMessage.replaceChildren(...messages.map(message => {
                const line = document.createElement('div');
                line.textContent = message;
                return line;
            }));
            loginLink.hidden = !showLogin;
            saveAlert.hidden = false;
        }

        function showDraftState(state, message, { login = false, retry = false } = {}) {
            const icons = { idle: 'fa-cloud', pending: 'fa-clock', saving: 'fa-cloud-upload-alt',
                saved: 'fa-check-circle', error: 'fa-exclamation-circle' };
            indicator.hidden = false;
            indicator.dataset.state = state;
            draftIcon.className = 'fas ' + icons[state];
            if (draftStatus.textContent !== message) draftStatus.textContent = message;
            draftLogin.hidden = !login;
            draftRetry.hidden = !retry;
        }

        function draftTime(template, date) {
            return template.replace(':data', dateFormat.format(new Date(date)));
        }

        function dirty() {
            return changes !== savedChanges;
        }

        function scheduleDraft() {
            clearTimeout(draftTimer);
            if (!manualSaving && !navigating && !draftConflict && dirty()) {
                draftTimer = setTimeout(persistDraft, 900);
            }
        }

        function queueDraft() {
            changes++;
            if (!draftConflict && !refreshSession) showDraftState('pending', config.draftPending);
            scheduleDraft();
        }

        function isDraftField(name) {
            return name.startsWith('years[') || name.startsWith('year_components[')
                || ['school_id', 'technical_course_id', 'removed_year_ids[]'].includes(name);
        }

        async function renewEditorSession() {
            if (!refreshSession) return null;

            const session = await fetch(config.sessionUrl, { ...requestOptions, cache: 'no-store' });
            if (!session.ok || session.redirected) return 'session';

            const current = await session.json();
            if (current.user_id !== config.userId) return 'account';

            form.elements.namedItem('_token').value = current.csrf_token;
            refreshSession = false;
            return null;
        }

        async function sendDraft(method, data) {
            const sessionError = await renewEditorSession();
            if (sessionError) {
                showDraftState('error', sessionError === 'account' ? config.differentUser : config.draftSession, { login: true, retry: true });
                return null;
            }

            const result = await fetch(config.draftUrl, {
                ...requestOptions,
                headers: { ...requestOptions.headers, 'Content-Type': 'application/json' },
                method,
                body: JSON.stringify({
                    _token: form.elements.namedItem('_token').value,
                    _history_editor_user: config.userId,
                    revision: draftRevision.value || null,
                    ...data,
                }),
            });
            if (result.status === 401 || result.status === 419 || result.status === 409) {
                refreshSession = true;
                showDraftState('error', result.status === 409 ? config.differentUser : config.draftSession, { login: true, retry: true });
            } else if (result.status === 422 && (await result.json()).errors?.history_draft_revision) {
                draftConflict = true;
                showDraftState('error', config.draftConflict);
            } else if (result.ok && !result.redirected) {
                return result;
            } else {
                showDraftState('error', config.draftError, { retry: true });
            }
            return null;
        }

        async function saveDraft() {
            const currentChanges = changes;
            const snapshot = JSON.stringify(Array.from(new FormData(form)).filter(([name]) => isDraftField(name)));
            showDraftState('saving', config.draftSaving);

            try {
                const result = await sendDraft('PUT', {
                    history_version: form.elements.namedItem('history_version')?.value || null,
                    snapshot,
                });
                if (!result) return;

                const data = await result.json();
                draftRevision.value = data.revision;
                draftDiscard.hidden = false;
                savedChanges = currentChanges;
                if (dirty()) {
                    showDraftState('pending', config.draftPending);
                    scheduleDraft();
                } else {
                    showDraftState('saved', draftTime(config.draftSaved, data.saved_at));
                }
            } catch {
                showDraftState('error', config.draftError, { retry: true });
            }
        }

        function persistDraft() {
            if (manualSaving || navigating || draftConflict || draftRequest || !dirty()) return;
            draftRequest = saveDraft().finally(() => { draftRequest = null; });
        }

        for (const name of ['input', 'change']) {
            form.addEventListener(name, event => {
                if (isDraftField(event.target.name || '')) queueDraft();
            });
        }
        draftRetry.addEventListener('click', persistDraft);
        window.addEventListener('online', persistDraft);
        window.addEventListener('focus', persistDraft);
        window.addEventListener('beforeunload', event => {
            if (!navigating && dirty()) event.preventDefault();
        });
        draftDiscard.addEventListener('click', () => {
            window.sgeConfirmAction?.(config.draftDiscard, async () => {
                if (manualSaving || navigating) return;
                manualSaving = true;
                clearTimeout(draftTimer);
                try {
                    await draftRequest;
                    if (await sendDraft('DELETE', {})) {
                        navigating = true;
                        location.reload();
                    }
                } catch {
                    showDraftState('error', config.draftError, { retry: true });
                } finally {
                    manualSaving = false;
                }
            }, draftDiscard);
        });

        if (config.draftSavedAt) {
            showDraftState('saved', draftTime(config.draftRestored, config.draftSavedAt));
        } else {
            showDraftState('idle', config.draftIdle);
        }

        form.addEventListener('submit', async event => {
            if (event.defaultPrevented) return;
            event.preventDefault();
            if (manualSaving || navigating) return;

            manualSaving = true;
            clearTimeout(draftTimer);
            saveButton.disabled = true;
            saveButton.setAttribute('aria-busy', 'true');
            saveButton.textContent = config.saving;
            saveAlert.hidden = true;
            try {
                await draftRequest;
                const sessionError = await renewEditorSession();
                if (sessionError) {
                    showSaveError([sessionError === 'account' ? config.differentUser : config.sessionExpired], true);
                    return;
                }

                const fields = Array.from(new FormData(form));
                const metadata = Object.fromEntries(fields.filter(([name]) => !isDraftField(name) && name !== '_method'));
                const result = await fetch(form.action, {
                    ...requestOptions,
                    headers: { ...requestOptions.headers, 'Content-Type': 'application/json' },
                    method: form.elements.namedItem('_method')?.value || form.method.toUpperCase(),
                    body: JSON.stringify({ ...metadata, editor_snapshot: JSON.stringify(fields.filter(([name]) => isDraftField(name))) }),
                });
                if (result.status === 401 || result.status === 419) {
                    refreshSession = true;
                    showSaveError([config.sessionExpired], true);
                } else if (result.status === 409) {
                    refreshSession = true;
                    showSaveError([config.differentUser], true);
                } else if (result.status === 422) {
                    const data = await result.json();
                    const messages = Object.values(data.errors || {}).flat();
                    showSaveError(messages.length ? messages : [data.message || config.saveError]);
                } else if (result.ok && !result.redirected) {
                    const data = await result.json();
                    const destination = new URL(data.redirect);
                    if (destination.origin !== location.origin) throw new Error('Invalid history redirect');
                    navigating = true;
                    location.assign(destination);
                } else {
                    showSaveError([config.saveError]);
                }
            } catch {
                showSaveError([config.saveError]);
            } finally {
                manualSaving = false;
                saveButton.disabled = false;
                saveButton.removeAttribute('aria-busy');
                saveButton.innerHTML = saveLabel;
                if (!saveAlert.hidden) saveAlert.focus();
                if (!refreshSession) scheduleDraft();
            }
        });
        wrapper.querySelectorAll('[data-history-component]').forEach(row => refreshRow(row));
        renumber();
        try {
            const target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
            if (target?.matches('[data-history-year]')) {
                target.open = true;
                requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
            }
        } catch (_) {}
        if (config.errors[0]) {
            const name = config.errors[0].replace(/\.([^.]*)/g, '[$1]');
            form.elements.namedItem(name)?.dispatchEvent(new Event('invalid', { cancelable: true }));
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
