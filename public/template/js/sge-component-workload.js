(() => {
    const initialize = () => document.querySelectorAll('[data-workload-choice]').forEach((choice) => {
        const form = choice.closest('form');
        const start = form.querySelector('[name="starts_period_id"]');
        const end = form.querySelector('[name="ends_period_id"]');
        const positions = new Map([...choice.querySelectorAll('[data-period-weekly]')].map((input) => [input.dataset.periodId, Number(input.dataset.periodPosition)]));
        const sync = () => {
            const mode = choice.querySelector('[name="workload_mode"]:checked')?.value;
            const lower = positions.get(start?.value) ?? -Infinity;
            const upper = positions.get(end?.value) ?? Infinity;
            choice.querySelectorAll('[data-workload-field]').forEach((field) => {
                field.hidden = field.dataset.workloadField !== mode;
                field.querySelectorAll('input').forEach((input) => {
                    const outside = input.dataset.periodPosition && (Number(input.dataset.periodPosition) < lower || Number(input.dataset.periodPosition) > upper);
                    input.disabled = field.hidden || Boolean(outside);
                    input.required = !input.disabled && !input.hasAttribute('data-period-hours');
                });
            });
            let total = 0;
            choice.querySelectorAll('[data-period-weekly]:not(:disabled)').forEach((input) => {
                const hours = input.closest('tr').querySelector('[data-period-hours]');
                total += hours.value !== '' ? Number(hours.value) : Number(input.value || 0) * Number(choice.dataset.periodWeeks) * Number(choice.dataset.lessonMinutes) / 60;
            });
            const output = choice.querySelector('[data-period-total]');
            if (output) output.textContent = total.toLocaleString(document.documentElement.lang || 'pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        };
        choice.addEventListener('input', sync);
        choice.addEventListener('change', sync);
        start?.addEventListener('change', sync);
        end?.addEventListener('change', sync);
        sync();
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
