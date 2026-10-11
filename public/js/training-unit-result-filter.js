(function () {
    'use strict';

    function setup() {
        const participant = document.querySelector('[data-training-participant]') ||
            document.querySelector('select[name$="[courseParticipant]"]');
        const schedule = document.querySelector('[data-training-schedule]') ||
            document.querySelector('select[name$="[schedule]"]');
        if (!participant || !schedule || schedule.dataset.trainingFilterReady === '1') return;
        schedule.dataset.trainingFilterReady = '1';

        const endpoint = '/admin/training-unit-result/schedules';
        const original = Array.from(schedule.options).map(option => ({
            value: option.value, text: option.textContent
        }));
        const placeholder = original.find(option => !option.value)?.text || '';
        let requestNumber = 0;
        const editMatch = new URLSearchParams(window.location.search).get('entityId');
        const editId = editMatch && /^\d+$/.test(editMatch) ? editMatch : '';
        const existingBox = document.createElement('div');
        existingBox.className = 'mt-2';
        existingBox.setAttribute('aria-live', 'polite');
        schedule.closest('.form-group, .field-association, .form-widget')?.appendChild(existingBox)
            || schedule.parentElement?.appendChild(existingBox);

        function showExisting(items) {
            existingBox.replaceChildren();
            if (!items.length) return;
            const title = document.createElement('div');
            title.className = 'small fw-semibold mb-1';
            title.textContent = 'Bereits erfasste, noch nicht bestandene Nachweise bearbeiten:';
            existingBox.appendChild(title);
            items.forEach(item => {
                const link = document.createElement('a');
                link.href = item.url;
                link.className = 'd-block small';
                link.textContent = item.text + ' – ' + item.status + ' (bearbeiten)';
                existingBox.appendChild(link);
            });
        }

        function selectedValue(select) {
            return select.tomselect ? select.tomselect.getValue() : select.value;
        }

        function rebuild(options, keepValue) {
            const choices = [{value: '', text: placeholder}, ...options];
            const allowed = new Set(options.map(item => String(item.value)));
            const value = allowed.has(String(keepValue)) ? String(keepValue) : '';

            // EasyAdmin verwendet je nach Konfiguration TomSelect.
            // Bei vorhandener Instanz wird diese ueber ihre API aktualisiert.
            if (schedule.tomselect) {
                const ts = schedule.tomselect;
                ts.clear(true);
                ts.clearOptions();
                choices.forEach(item => ts.addOption(item));
                ts.refreshOptions(false);
                if (value) ts.setValue(value, true);
                else ts.clear(true);
            } else {
                schedule.replaceChildren();
                choices.forEach(item => schedule.add(new Option(item.text, item.value)));
                schedule.value = value;
            }
            schedule.disabled = options.length === 0;
        }

        async function refresh(preserve) {
            const ticket = ++requestNumber;
            const participantId = selectedValue(participant);
            const currentValue = preserve ? selectedValue(schedule) : '';
            if (!participantId) {
                rebuild([], '');
                showExisting([]);
                return;
            }
            schedule.disabled = true;
            try {
                const response = await fetch(endpoint + '?participant=' + encodeURIComponent(participantId) + (editId ? '&edit=' + encodeURIComponent(editId) : ''), {
                    headers: {'Accept': 'application/json'}, credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const data = await response.json();
                if (ticket !== requestNumber) return;
                rebuild(Array.isArray(data.options) ? data.options : [], currentValue);
                showExisting(Array.isArray(data.existing) ? data.existing : []);
            } catch (error) {
                if (ticket !== requestNumber) return;
                console.error('Ausbildungstermine konnten nicht geladen werden:', error);
                // Keine ungefilterte Liste freigeben, wenn die Abfrage fehlschlaegt.
                rebuild([], '');
                showExisting([]);
            }
        }

        participant.addEventListener('change', () => refresh(false));
        // Initialen Wert beim Bearbeiten erhalten.
        refresh(true);
    }

    document.addEventListener('DOMContentLoaded', setup);
    document.addEventListener('turbo:load', setup);
    if (document.readyState !== 'loading') setup();
})();
