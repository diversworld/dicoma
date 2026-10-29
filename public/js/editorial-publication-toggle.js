document.querySelectorAll('[data-editorial-publication-toggle]').forEach((form) => {
    const checkbox = form.querySelector('input[name="published"]');
    const button = form.querySelector('[data-editorial-toggle-submit]');
    if (!checkbox || !button || typeof form.requestSubmit !== 'function') return;

    checkbox.addEventListener('change', () => form.requestSubmit());
    button.hidden = true;
});
