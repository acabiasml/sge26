(() => {
  const tabs = [...document.querySelectorAll('[role="tab"]')];
  function selectTab(key, focus = false) {
    const selected = tabs.find(tab => tab.dataset.tab === key);
    if (!selected) return;
    tabs.forEach(tab => {
      const active = tab === selected;
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
      document.getElementById(tab.getAttribute('aria-controls')).hidden = !active;
    });
    if (focus) selected.focus();
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => selectTab(tab.dataset.tab));
    tab.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      if (next !== undefined) { event.preventDefault(); selectTab(tabs[next].dataset.tab, true); }
    });
  });
  document.querySelectorAll('[data-open-tab]').forEach(link => link.addEventListener('click', () => selectTab(link.dataset.openTab)));
  const fromHash = () => { if (location.hash.startsWith('#painel-')) selectTab(location.hash.slice(8)); };
  window.addEventListener('hashchange', fromHash);
  fromHash();
  const form = document.querySelector('[data-verify-form]');
  const input = form.elements.code;
  const error = document.getElementById('verification-error');
  input.addEventListener('input', () => { error.textContent = ''; input.removeAttribute('aria-invalid'); });
  form.addEventListener('submit', event => {
    event.preventDefault();
    const code = input.value.trim().toUpperCase().replace(/\s+/g, '');
    if (!code) { error.textContent = 'Informe o código impresso no documento.'; input.setAttribute('aria-invalid','true'); input.focus(); return; }
    location.href = '/sge/documentos/verificar/' + encodeURIComponent(code);
  });
})();
