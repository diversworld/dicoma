/* DiCoMa: one owner for dependent inventory selects (native select + TomSelect). */
(() => {
  function init() {
    const category = document.querySelector('select[data-inventory-options-url]');
    if (!category || category.dataset.dicomaDependentReady === '1') return;
    const form = category.closest('form');
    if (!form) return;
    const find = key => form.querySelector(`select[name$="[${key}]"]`);
    const club = find('club'), manufacturer = find('manufacturer');
    const subType = find('subType'), model = find('modelReference'), size = find('size');
    if (![club, manufacturer, subType, model, size].every(Boolean)) return;
    category.dataset.dicomaDependentReady = '1';
    const fields = {subTypes: subType, models: model, sizes: size};
    let generation = 0, controller = null;
    let firstLoad = true;

    function value(select) { return String(select.tomselect ? select.tomselect.getValue() : select.value || ''); }
    function replace(select, rows, previous = '', enabled = true) {
      const entries = Array.isArray(rows) ? rows.filter(row => row && /^\d+$/.test(String(row.id)) && row.label != null) : [];
      const valid = entries.some(row => String(row.id) === previous) ? previous : '';
      const ts = select.tomselect;
      if (ts) {
        ts.clear(true);
        ts.clearOptions();
        ts.addOption({value: '', text: 'Bitte auswählen'});
        entries.forEach(row => ts.addOption({value: String(row.id), text: String(row.label)}));
        ts.refreshOptions(false);
        if (valid) ts.setValue(valid, true);
        if (enabled) ts.enable(); else ts.disable();
      } else {
        select.replaceChildren(new Option('Bitte auswählen', ''));
        entries.forEach(row => select.add(new Option(String(row.label), String(row.id))));
        select.value = valid;
        select.disabled = !enabled;
      }
    }
    async function refresh(event) {
      const current = ++generation;
      if (controller) controller.abort();
      controller = new AbortController();
      const preserve = firstLoad && !event;
      firstLoad = false;
      const previous = {};
      Object.entries(fields).forEach(([key, select]) => {
        previous[key] = preserve ? value(select) : '';
        replace(select, [], '', false);
      });
      if (!value(club) || !value(category)) return;
      try {
        const url = new URL(category.dataset.inventoryOptionsUrl, window.location.origin);
        url.searchParams.set('club', value(club));
        url.searchParams.set('category', value(category));
        url.searchParams.set('manufacturer', value(manufacturer));
        const response = await fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: controller.signal});
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        if (current !== generation) return;
        replace(subType, data.subTypes, previous.subTypes);
        replace(model, data.models, previous.models, !!value(manufacturer));
        replace(size, data.sizes, previous.sizes, !!value(manufacturer));
      } catch (error) {
        if (current !== generation || error.name === 'AbortError') return;
        console.warn('DiCoMa: Inventaroptionen konnten nicht geladen werden.', error);
      }
    }
    [club, category, manufacturer].forEach(select => select.addEventListener('change', refresh));
    refresh();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  document.addEventListener('turbo:load', init);
})();
