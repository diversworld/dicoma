'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/team-member-sport-check.js'), 'utf8');

async function scenario(payload, status = 200) {
  const handlers = {};
  const message = {textContent: '', className: '', setAttribute() {}};
  const wrapper = {hidden: false, style: {}, appendChild() {}};
  const checkbox = {dataset: {sportCheckUrl: '/admin/team-member/sport-check'}, checked: false, disabled: false,
    closest() {return wrapper;}};
  const member = {value: '1', addEventListener(name, cb) {handlers['member:'+name] = cb;}};
  const team = {value: '2', addEventListener(name, cb) {handlers['team:'+name] = cb;}};
  const form = {dataset: {}, querySelector(selector) {
    if (selector.includes('assignMissingSport')) return checkbox;
    if (selector.includes('[member]')) return member;
    if (selector.includes('[team]')) return team;
    return null;
  }};
  const document = {readyState: 'complete', querySelectorAll() {return [form];},
    createElement() {return message;}, addEventListener() {}};
  const ctx = {document, URLSearchParams, AbortController, fetch: async () => ({ok: status === 200, json: async () => payload})};
  vm.runInNewContext(source, ctx);
  await new Promise(resolve => setImmediate(resolve));
  return {checkbox, wrapper, message, handlers};
}

test('missing sport enables explicit consent, unchecked by default', async () => {
  const r = await scenario({state:'missing', sport:'Flossenschwimmen'});
  assert.equal(r.wrapper.hidden, false);
  assert.equal(r.checkbox.disabled, false);
  assert.equal(r.checkbox.checked, false);
  assert.match(r.message.textContent, /Flossenschwimmen/);
});
test('present sport hides consent', async () => {
  const r = await scenario({state:'present'});
  assert.equal(r.wrapper.hidden, true);
  assert.equal(r.checkbox.disabled, true);
});
test('club mismatch hides consent', async () => {
  const r = await scenario({state:'club_mismatch'});
  assert.equal(r.wrapper.hidden, true);
  assert.equal(r.checkbox.disabled, true);
});
test('endpoint failure fails closed', async () => {
  const r = await scenario({}, 503);
  assert.equal(r.wrapper.hidden, false);
  assert.equal(r.checkbox.disabled, true);
  assert.equal(r.checkbox.checked, false);
  assert.match(r.message.textContent, /nicht geprueft/);
});
