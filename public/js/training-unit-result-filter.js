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
                return;
            }
            schedule.disabled = true;
            try {
                const response = await fetch(endpoint + '?participant=' + encodeURIComponent(participantId), {
                    headers: {'Accept': 'application/json'}, credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const data = await response.json();
                if (ticket !== requestNumber) return;
                rebuild(Array.isArray(data.options) ? data.options : [], currentValue);
            } catch (error) {
                if (ticket !== requestNumber) return;
                console.error('Ausbildungstermine konnten nicht geladen werden:', error);
                // Keine ungefilterte Liste freigeben, wenn die Abfrage fehlschlaegt.
                rebuild([], '');
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
