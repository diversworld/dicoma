(() => {
  const fields = {
    club: 'club', manufacturer: 'manufacturer',
    first: 'firstStageModel', primary: 'primarySecondStageModel',
    secondary: 'secondarySecondStageModel'
  };
  const locate = (name) => document.querySelector(`select[name$="[${name}]"]`);
  const bind = () => {
    const club = locate(fields.club), manufacturer = locate(fields.manufacturer);
    const first = locate(fields.first), primary = locate(fields.primary), secondary = locate(fields.secondary);
    if (!club || !manufacturer || !first || !primary || !secondary || club.dataset.dicomaModelsBound) return;
    club.dataset.dicomaModelsBound = '1';
    const initial = new Map([[first, first.value], [primary, primary.value], [secondary, secondary.value]]);
    const reset = (select, options, previous) => {
      select.replaceChildren(new Option('Bitte wählen', ''));
      for (const item of options) select.add(new Option(item.label, String(item.id)));
      if ([...select.options].some(o => o.value === previous)) select.value = previous;
      select.disabled = options.length === 0;
    };
    let generation = 0;
    const refresh = async () => {
      const token = ++generation;
      const values = new Map([[first, first.value || initial.get(first)], [primary, primary.value || initial.get(primary)], [secondary, secondary.value || initial.get(secondary)]]);
      if (!club.value || !manufacturer.value) {
        [first, primary, secondary].forEach(s => reset(s, [], ''));
        return;
      }
      const url = new URL('/admin/api/regulator-model-options', window.location.origin);
      url.searchParams.set('club', club.value);
      url.searchParams.set('manufacturer', manufacturer.value);
      try {
        const response = await fetch(url, {credentials: 'same-origin', headers: {'Accept':'application/json'}});
        if (!response.ok) throw new Error('Modelle konnten nicht geladen werden');
        const data = await response.json();
        if (token !== generation) return;
        reset(first, data.first || [], values.get(first));
        reset(primary, data.second || [], values.get(primary));
        reset(secondary, data.second || [], values.get(secondary));
        initial.clear();
      } catch (error) {
        if (token === generation) console.error(error);
      }
    };
    club.addEventListener('change', () => { initial.clear(); refresh(); });
    manufacturer.addEventListener('change', () => { initial.clear(); refresh(); });
    refresh();
  };
  document.addEventListener('DOMContentLoaded', bind);
  document.addEventListener('turbo:load', bind);
})();
