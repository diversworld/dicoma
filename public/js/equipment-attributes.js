/* DiCoMa 78B: category-driven attributes, no external dependencies. */
(() => {
  'use strict';
  function init(textarea) {
    if (textarea.dataset.equipmentInitialized) return;
    const form = textarea.closest('form');
    if (!form) return;
    const category = form.querySelector('select[name$="[category]"]');
    const club = form.querySelector('select[name$="[club]"]');
    if (!category) return;
    textarea.dataset.equipmentInitialized = '1';
    const wrapper = document.createElement('div');
    wrapper.className = 'row g-3 mt-1';
    const status = document.createElement('p');
    status.className = 'text-muted small';
    textarea.insertAdjacentElement('afterend', wrapper);
    wrapper.insertAdjacentElement('beforebegin', status);
    let lastCategory = category.value;
    let controller = null;
    let schema = [];
    let currentValues = {};

    function parseValues() {
      try {
        const parsed = JSON.parse(textarea.value || '{}');
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
      } catch (_) { return null; }
    }
    function showFallback(message) {
      textarea.style.display = '';
      wrapper.replaceChildren();
      status.textContent = message;
      status.className = 'text-danger small';
    }
    function serialize() {
      const values = {};
      for (const def of schema) {
        const input = wrapper.querySelector('[data-equipment-code="' + CSS.escape(def.code) + '"]');
        if (!input) continue;
        if (def.type === 'boolean') {
          values[def.code] = input.checked;
        } else if (input.value !== '') {
          values[def.code] = def.type === 'number' ? Number(input.value) : input.value;
        }
      }
      currentValues = values;
      textarea.value = JSON.stringify(values);
    }
    function render(definitions, values) {
      schema = definitions;
      wrapper.replaceChildren();
      textarea.style.display = 'none';
      status.className = 'text-muted small';
      status.textContent = definitions.length ? 'Eigenschaften der gewählten Kategorie' : 'Für diese Kategorie sind noch keine Merkmale definiert.';
      for (const def of definitions) {
        const col = document.createElement('div');
        col.className = 'col-12 col-md-6';
        const label = document.createElement('label');
        label.className = 'form-label';
        const id = textarea.id + '_' + def.code.replace(/[^a-zA-Z0-9_-]/g, '_');
        label.htmlFor = id;
        label.textContent = def.label + (def.required ? ' *' : '');
        const input = document.createElement('input');
        input.id = id;
        input.dataset.equipmentCode = def.code;
        input.type = ({text:'text',number:'number',boolean:'checkbox',date:'date'})[def.type] || 'text';
        if (def.type === 'number') input.step = 'any';
        if (def.type === 'boolean') input.className = 'form-check-input';
        else input.className = 'form-control';
        if (def.required && def.type !== 'boolean') input.required = true;
        const v = values[def.code];
        if (def.type === 'boolean') input.checked = v === true;
        else if (v !== undefined && v !== null) input.value = String(v);
        input.addEventListener('input', serialize);
        input.addEventListener('change', serialize);
        col.append(label, input);
        wrapper.append(col);
      }
      serialize();
    }
    async function load(reset) {
      if (controller) controller.abort();
      controller = new AbortController();
      if (!category.value) {
        showFallback('Bitte zuerst eine Kategorie auswählen.');
        return;
      }
      const values = reset ? {} : parseValues();
      if (values === null) {
        showFallback('Ungültiges JSON. Bitte zuerst die bestehenden Merkmalswerte korrigieren.');
        return;
      }
      status.textContent = 'Merkmale werden geladen ...';
      const url = new URL(textarea.dataset.equipmentSchemaUrl, window.location.origin);
      url.searchParams.set('categoryId', category.value);
      try {
        const response = await fetch(url, {credentials:'same-origin', signal:controller.signal, headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        if (String(data.categoryId) !== category.value) return;
        if (club && club.value && String(data.clubId) !== club.value) {
          showFallback('Kategorie und Verein stimmen nicht überein. Bitte Auswahl korrigieren.');
          return;
        }
        render(data.attributes, values);
      } catch (error) {
        if (error.name !== 'AbortError') showFallback('Merkmale konnten nicht geladen werden. JSON-Eingabe bleibt verfügbar.');
      }
    }
    category.addEventListener('change', () => {
      const previous = parseValues();
      if (previous && Object.keys(previous).length && !window.confirm('Kategorie wechseln? Bisherige Merkmalswerte werden entfernt.')) {
        category.value = lastCategory;
        return;
      }
      lastCategory = category.value;
      textarea.value = '{}';
      load(true);
    });
    if (club) club.addEventListener('change', () => load(false));
    form.addEventListener('submit', () => {
      if (textarea.style.display === 'none') serialize();
    });
    load(false);
  }
  function scan() {
    document.querySelectorAll('textarea[data-equipment-schema-url]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
  else scan();
  document.addEventListener('turbo:load', scan);
})();
