const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function fixture({ ready = '1', restored = false, programs = [], hasEditor = true } = {}) {
    const events = {};
    const checkbox = { disabled: false, checked: true };
    const button = { disabled: false };
    const notice = { hidden: true };
    const editor = { value: 'original', querySelector: () => restored, addEventListener: (name, fn) => { events[name] = fn; } };
    const publication = { dataset: { ready }, querySelector: () => checkbox,
        querySelectorAll: () => [checkbox, button], addEventListener: (name, fn) => { events['publish-' + name] = fn; } };
    const document = { getElementById: id => id === 'tt-edit-form' ? (hasEditor ? editor : null) : notice,
        querySelectorAll: () => programs, querySelector: () => publication,
        addEventListener: (name, fn) => { if (name === 'DOMContentLoaded') fn(); else events[name] = fn; } };
    vm.runInNewContext(fs.readFileSync('public/js/timetable-review.js', 'utf8'), {
        document, URLSearchParams, FormData: function (form) { return [['choice', form.value]]; },
    });
    return { events, checkbox, button, notice, editor };
}

test('unsaved edits block publication and clear review confirmation; reverting restores eligibility', () => {
    const f = fixture();
    assert.equal(f.button.disabled, false);
    f.editor.value = 'changed'; f.events.input();
    assert.equal(f.button.disabled, true);
    assert.equal(f.checkbox.checked, false);
    assert.equal(f.notice.hidden, false);
    let prevented = false;
    f.events['publish-submit']({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    f.editor.value = 'original'; f.events.change();
    assert.equal(f.button.disabled, false);
    assert.equal(f.checkbox.checked, false);
    assert.equal(f.notice.hidden, true);
});

test('unresolved saved subjects and restored validation edits stay blocked', () => {
    assert.equal(fixture({ ready: '0' }).button.disabled, true);
    const f = fixture({ restored: true });
    assert.equal(f.button.disabled, true);
    assert.equal(f.notice.hidden, false);
    f.events.change();
    assert.equal(f.button.disabled, true);
});

test('program selection prefills untouched start forms without replacing staff choices', () => {
    const changes = [];
    const programs = Array.from({length: 2}, () => ({dataset: {}, value: '1', options: [{value: '1'}],
        addEventListener: (name, fn) => changes.push(fn)}));
    const f = fixture({programs, hasEditor: false});
    f.events['dtc:program-selected']({detail: '2'});
    assert.deepEqual(programs.map(field => field.value), ['2', '2']);
    programs[0].value = '3'; changes[0]();
    f.events['dtc:program-selected']({detail: '4'});
    assert.deepEqual(programs.map(field => field.value), ['3', '4']);
    f.events['dtc:program-selected']({detail: null});
    assert.deepEqual(programs.map(field => field.value), ['3', '1']);
});
