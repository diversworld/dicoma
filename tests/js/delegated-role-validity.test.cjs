'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/delegated-role-validity.js'), 'utf8');

function role(checked) {
  const handlers = {};
  const choice = {checked, addEventListener(name, callback) { handlers[name] = callback; }};
  const fields = ['startsAt', 'endsAt'].map(name => ({
    name: `form[validity][ROLE_TEST][${name}]`, value: '2027-01-01', disabled: false,
    after(input) { this.empty = input; },
  }));
  const validity = {hidden: false, querySelectorAll() { return fields; }};
  const row = {querySelector(selector) {
    return selector === 'input[type="checkbox"]' ? choice : validity;
  }};
  return {row, choice, fields, validity, handlers};
}

test('only selected functions submit their date inputs and toggling preserves values', () => {
  const selected = role(true);
  const unselected = role(false);
  const document = {
    querySelectorAll() { return [selected.row, unselected.row]; },
    createElement() { return {}; },
  };
  vm.runInNewContext(source, {document});
  assert.equal(selected.validity.hidden, false);
  assert.equal(unselected.validity.hidden, true);
  for (const field of selected.fields) {
    assert.equal(field.disabled, false);
    assert.equal(field.empty.disabled, true);
    assert.equal(field.empty.name, field.name);
    assert.equal(field.empty.value, '');
  }
  for (const field of unselected.fields) {
    assert.equal(field.disabled, true);
    assert.equal(field.empty.disabled, false);
  }
  selected.choice.checked = false;
  selected.handlers.change();
  assert.equal(selected.validity.hidden, true);
  assert.equal(selected.fields[0].disabled, true);
  assert.equal(selected.fields[0].empty.disabled, false);
  selected.choice.checked = true;
  selected.handlers.change();
  assert.equal(selected.validity.hidden, false);
  assert.equal(selected.fields[0].disabled, false);
  assert.equal(selected.fields[0].empty.disabled, true);
  assert.equal(selected.fields[0].value, '2027-01-01');
});
