document.querySelectorAll('[data-function-row]').forEach((row) => {
    const choice = row.querySelector('input[type="checkbox"]');
    const validity = row.querySelector('[data-function-validity]');
    const fields = validity.querySelectorAll('input');
    const emptyFields = Array.from(fields, (field) => {
        const empty = document.createElement('input');
        empty.type = 'hidden';
        empty.name = field.name;
        empty.value = '';
        field.after(empty);
        return empty;
    });
    const update = () => {
        validity.hidden = !choice.checked;
        fields.forEach((field) => { field.disabled = !choice.checked; });
        emptyFields.forEach((field) => { field.disabled = choice.checked; });
    };
    choice.addEventListener('change', update);
    update();
});
