'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const template = fs.readFileSync(
  path.join(__dirname, '../../templates/admin/course/schedule_planning.html.twig'),
  'utf8'
);
const script = template.match(/function translate\(key, parameters = \{\}\) \{[\s\S]*?(?=\n    \}\)\(\);)/);
assert.ok(script, 'The schedule-planning script must be present in the template');

class Element {
  constructor(tagName, value = '') {
    this.tagName = tagName;
    this.value = value;
    this.checked = false;
    this.disabled = false;
    this.hidden = false;
    this.textContent = '';
    this.children = [];
    this.listeners = new Map();
  }

  addEventListener(name, callback) {
    const listeners = this.listeners.get(name) || [];
    listeners.push(callback);
    this.listeners.set(name, listeners);
  }

  dispatchEvent(event) {
    for (const listener of this.listeners.get(event.type) || []) listener(event);
  }

  appendChild(child) { this.children.push(child); }
  replaceChildren() { this.children = []; }
  add(option) { this.appendChild(option); }
  querySelector(selector) { return selector === 'tbody' ? this.children[0] : null; }
}

function scenario() {
  const elements = new Map();
  const inputs = {series: [], weekend: []};
  function field(id, tag, value = '') {
    const element = new Element(tag, value);
    elements.set(id, element);
    const group = id.split('-')[0];
    if (inputs[group] && ['input', 'select', 'textarea'].includes(tag)) inputs[group].push(element);
    return element;
  }

  for (const group of ['series', 'weekend']) {
    const root = field(`${group}-planner`, 'section');
    root.querySelectorAll = selector => {
      const tags = selector.split(',').map(tag => tag.trim());
      return inputs[group].filter(element => tags.includes(element.tagName));
    };
    field(`${group}-date`, 'input', '2027-01-08');
    field(`${group}-location`, 'input');
    field(`${group}-instructor`, 'select');
    field(`${group}-overwrite`, 'input');
    field(`${group}-exclusions`, 'textarea');
    field(`${group}-preview-button`, 'button');
    field(`${group}-apply-button`, 'button').disabled = true;
    field(`${group}-feedback`, 'div');
    const table = field(`${group}-preview-table`, 'table');
    table.hidden = true;
    table.appendChild(new Element('tbody'));
  }
  field('series-type', 'select', 'Freiwasser');
  field('series-time', 'input', '19:00');
  field('series-hours', 'input', '2');
  field('series-gap', 'select', '7');
  field('series-count', 'input', '1');
  for (let number = 1; number <= 5; number++) {
    field(`weekend-offset-${number}`, 'input', String(number < 3 ? 0 : number < 5 ? 1 : 2));
    field(`weekend-time-${number}`, 'input', '09:00');
    field(`weekend-hours-${number}`, 'input', '1.5');
  }

  const rows = Array.from({length: 5}, (_, index) => {
    const date = new Element('input');
    const start = new Element('input');
    const duration = new Element('input');
    const location = new Element('input');
    const instructor = new Element('select');
    instructor.options = [];
    const values = {
      'input[name$="[startDate]"]': date,
      '[data-time-start]': start,
      '[data-time-duration]': duration,
      'input[name$="[location]"]': location,
      'select[name$="[instructor]"]': instructor
    };
    return {
      date,
      cells: [{textContent: `Freiwasser ${index + 1}`}],
      classList: {add() {}, remove() {}},
      querySelector(selector) { return values[selector] || null; },
      querySelectorAll() { return Object.values(values); }
    };
  });
  const document = {
    readyState: 'complete',
    getElementById(id) { return elements.get(id) || null; },
    querySelectorAll(selector) { return selector === '[data-schedule-row]' ? rows : []; },
    createElement(tag) { return new Element(tag); }
  };
  const i18n = new Proxy({}, {get: (_, key) => String(key)});
  vm.runInNewContext(script[0], {
    document,
    i18n,
    Event: class Event { constructor(type) { this.type = type; } },
    Option: class Option { constructor(text, value) { this.textContent = text; this.value = value; } }
  });
  return {elements, rows};
}

for (const group of ['series', 'weekend']) {
  for (const event of ['input', 'change']) {
    test(`${group} exclusions ${event} invalidates the preview until recalculated`, () => {
      const {elements, rows} = scenario();
      const get = suffix => elements.get(`${group}-${suffix}`);
      const click = element => element.dispatchEvent({type: 'click'});
      const body = get('preview-table').querySelector('tbody');

      click(get('preview-button'));
      assert.equal(get('apply-button').disabled, false);
      assert.equal(get('preview-table').hidden, false);
      assert.equal(body.children[0].children[1].textContent, '08.01.2027');
      assert.equal(rows[0].date.value, '');

      get('exclusions').value = '2027-01-08';
      get('exclusions').dispatchEvent({type: event});
      assert.equal(get('apply-button').disabled, true);
      assert.equal(get('preview-table').hidden, true);
      assert.equal(body.children.length, 0);
      click(get('apply-button'));
      assert.equal(rows[0].date.value, '');

      click(get('preview-button'));
      assert.equal(get('apply-button').disabled, false);
      assert.equal(body.children[0].children[1].textContent, group === 'series' ? '15.01.2027' : '09.01.2027');
      click(get('apply-button'));
      assert.equal(rows[0].date.value, group === 'series' ? '2027-01-15' : '2027-01-09');
    });
  }
}
