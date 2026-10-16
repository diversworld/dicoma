/* Filter manufacturers by club. Symfony entity validation is authoritative. */
(() => {
  const init = () => {
    const manufacturer = document.querySelector('select[data-manufacturer-options-url]');
    if (!manufacturer || manufacturer.dataset.manufacturerBound === '1') return;
    const form = manufacturer.closest('form');
    const club = form?.querySelector('select[name$="[club]"]');
    if (!club) return;
    manufacturer.dataset.manufacturerBound = '1';
    const original = manufacturer.value;
    let generation = 0;
    const refresh = async (preserve = false) => {
      const token = ++generation;
      const previous = preserve ? original : manufacturer.value;
      if (!club.value) {
        manufacturer.replaceChildren(new Option('Bitte auswählen', ''));
        manufacturer.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }
      try {
        const url = new URL(manufacturer.dataset.manufacturerOptionsUrl, window.location.origin);
        url.searchParams.set('club', club.value);
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const rows = await response.json();
        if (token !== generation) return;
        manufacturer.replaceChildren(new Option('Bitte auswählen', ''));
        for (const row of rows) manufacturer.add(new Option(row.label, String(row.id)));
        manufacturer.value = rows.some(row => String(row.id) === previous) ? previous : '';
        manufacturer.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (error) {
        console.warn('Hersteller konnten nicht geladen werden.', error);
      }
    };
    club.addEventListener('change', () => refresh(false));
    refresh(true);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  document.addEventListener('turbo:load', init);
})();
