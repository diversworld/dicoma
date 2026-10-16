/* DiCoMa step 73: presentation only. Server-side validation remains authoritative. */
(() => {
    'use strict';
    function init(form) {
        if (form.dataset.sportCheckInitialized === '1') return;
        const checkbox = form.querySelector('[name$="[assignMissingSport]"]');
        const member = form.querySelector('[name$="[member]"]');
        const team = form.querySelector('[name$="[team]"]');
        if (!checkbox || !member || !team) return;
        const url = checkbox.dataset.sportCheckUrl;
        if (!url) return;
        form.dataset.sportCheckInitialized = '1';
        const wrapper = checkbox.closest('.field-boolean, .form-group, .field-group') || checkbox.parentElement;
        const message = document.createElement('div');
        message.className = 'small mt-2';
        message.setAttribute('role', 'status');
        message.setAttribute('aria-live', 'polite');
        wrapper.appendChild(message);
        let generation = 0;
        let controller = null;
        const display = (visible, text, enabled = visible) => {
            wrapper.hidden = !visible;
            wrapper.style.display = visible ? '' : 'none';
            checkbox.disabled = !enabled;
            if (!enabled) checkbox.checked = false;
            message.textContent = text || '';
        };
        async function update() {
            const current = ++generation;
            if (controller) controller.abort();
            controller = new AbortController();
            if (!member.value || !team.value) {
                display(false, '');
                return;
            }
            display(false, '');
            const query = new URLSearchParams({member: member.value, team: team.value});
            try {
                const response = await fetch(url + (url.includes('?') ? '&' : '?') + query.toString(), {
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'},
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Pruefung fehlgeschlagen');
                const data = await response.json();
                if (current !== generation) return;
                if (data.state === 'missing') {
                    display(true, 'Die Sportart "' + data.sport + '" fehlt. Nur mit Zustimmung wird sie beim Speichern ergaenzt.');
                } else if (data.state === 'present') {
                    display(false, '');
                } else if (data.state === 'club_mismatch') {
                    display(false, '');
                } else {
                    display(false, '');
                }
            } catch (error) {
                if (current !== generation || error.name === 'AbortError') return;
                // Fail closed: do not accept consent without a verified missing-sport result.
                display(true, 'Sportart konnte nicht geprueft werden. Bitte Seite neu laden oder Administrator kontaktieren.', false);
            }
        }
        member.addEventListener('change', update);
        team.addEventListener('change', update);
        update();
    }
    function scan() {
        document.querySelectorAll('form').forEach(init);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
    document.addEventListener('turbo:load', scan);
})();
