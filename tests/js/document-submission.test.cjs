const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('selected documents alone submit quantities and their own GCash fields', () => {
    const events = {};
    const summary = { replaceChildren(...children) { this.children = children; } };
    const selectionError = { hidden: true };
    const button = { disabled: false };
    const mobileTotal = {};
    const navigation = () => ({ addEventListener(event, callback) { this[event] = callback; } });
    const next = navigation();
    const back = navigation();
    const steps = Array.from({ length: 3 }, () => ({ hidden: false, querySelector: () => null, contains: () => false }));
    const paymentContainer = { appendChild() {} };
    const rows = [true, false].map(checked => {
        const toggle = { checked, focus() { this.focused = true; } };
        const fields = [{ disabled: true, value: 'diploma' }, { disabled: true, value: '2' }];
        const payment = { value: 'walk_in', disabled: true };
        const proof = [{ disabled: true }, { disabled: true }];
        const panel = { hidden: true };
        const controls = { hidden: true };
        const hint = {};
        const copyButtons = [-1, 1].map(step => ({ dataset: { documentCopyStep: String(step) },
            addEventListener(event, callback) { this[event] = callback; } }));
        return { toggle, fields, payment, proof, panel, copyButtons,
            dataset: { documentName: 'Diploma', documentCents: '10025' }, controls, hint,
            querySelector: s => ({ '[data-document-toggle]': toggle, '[data-document-payment]:checked': payment,
                '[data-document-controls]': controls, '[data-document-payment-hint]': hint,
                '[data-document-copies]': fields[1], '[data-document-gcash]': panel })[s],
            querySelectorAll: s => s === '[data-document-field]' ? [...fields, payment] : s === '[data-document-copy-step]' ? copyButtons : proof };
    });
    rows.forEach(row => {
        const originalQuery = row.querySelector;
        const section = { prepend() {}, querySelector: originalQuery, querySelectorAll: row.querySelectorAll };
        row.querySelector = selector => selector === '[data-document-payment-section]' ? section : originalQuery(selector);
    });
    const form = {
        querySelectorAll: selector => selector === '[data-request-step]' ? steps : selector === '[data-request-step-indicator]' ? [] : rows,
        querySelector: s => s === '[data-document-summary]' ? summary
            : s === '[data-mobile-request-total]' ? mobileTotal
            : s === '[data-request-payments]' ? paymentContainer : s === '[data-request-next]' ? next : s === '[data-request-back]' ? back
            : s === '[data-document-selection-error]' ? selectionError : s === '[data-document-submit]' ? button
            : s.includes(':checked') ? rows.find(r => r.toggle.checked)?.toggle : rows[0].toggle,
        addEventListener: (event, handler) => { events[event] = handler; },
    };
    vm.runInNewContext(fs.readFileSync('public/js/document-submission.js', 'utf8'), { document: {
        querySelectorAll: () => [form], createElement: () => ({}) } });
    assert.deepEqual(steps.map(step => step.hidden), [false, true, true]);
    assert.equal(button.hidden, true);
    next.click();
    assert.deepEqual(steps.map(step => step.hidden), [true, false, true]);
    next.click();
    assert.deepEqual(steps.map(step => step.hidden), [true, true, false]);
    assert.equal(button.hidden, false);
    back.click();
    assert.deepEqual(steps.map(step => step.hidden), [true, false, true]);
    next.click();
    assert.equal(rows[0].fields[1].disabled, false);
    assert.equal(rows[1].fields[1].disabled, true);
    assert.equal(rows[0].proof[0].disabled, true);
    assert.equal(rows[0].controls.hidden, false);
    assert.equal(rows[1].controls.hidden, true);
    assert.match(summary.children[0].textContent, /2 copies.*200.50.*Cash at Cashier/);
    assert.match(mobileTotal.textContent, /1 document.*200.50/);
    rows[0].copyButtons[1].click();
    assert.equal(Number(rows[0].fields[1].value), 3);
    rows[0].fields[1].value = '10'; events.input();
    assert.equal(rows[0].copyButtons[1].disabled, true);
    rows[0].copyButtons[1].click();
    assert.equal(Number(rows[0].fields[1].value), 10);
    rows[0].fields[1].value = '1'; events.input();
    assert.equal(rows[0].copyButtons[0].disabled, true);
    rows[0].copyButtons[0].click();
    assert.equal(Number(rows[0].fields[1].value), 1);
    assert.ok(rows[1].copyButtons.every(control => control.disabled));
    rows[0].fields[1].value = '3'; events.input();
    assert.match(summary.children.at(-1).textContent, /300.75/);
    rows[0].payment.value = 'gcash_qr'; events.change();
    assert.equal(rows[0].panel.hidden, false);
    assert.ok(rows[0].proof.every(p => !p.disabled && p.required));
    assert.ok(rows[1].proof.every(p => p.disabled && !p.required));
    assert.match(summary.children[0].textContent, /GCash/);
    assert.match(rows[0].hint.textContent, /Cashier verification/);
    rows[0].payment.value = 'walk_in'; events.change();
    assert.equal(rows[0].panel.hidden, true);
    assert.ok(rows[0].proof.every(p => p.disabled && !p.required));
    rows[0].payment.value = 'gcash_qr'; events.change();
    rows[1].toggle.checked = true;
    rows[1].dataset.documentCents = '0'; events.change();
    assert.match(summary.children[1].textContent, /No payment required/);
    assert.match(summary.children.at(-1).textContent, /300.75/);
    rows[0].fields[1].value = '11'; events.input();
    assert.match(summary.children.at(-1).textContent, /Complete copy quantities/);
    assert.match(mobileTotal.textContent, /2 documents.*Check copy quantities/);
    rows[1].toggle.checked = false;
    rows[0].toggle.checked = false; events.change();
    assert.equal(rows[0].panel.hidden, true);
    assert.ok(rows[0].proof.every(p => p.disabled));
    let prevented = false;
    events.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(rows[0].toggle.focused, true);
    assert.equal(selectionError.hidden, false);
    assert.match(summary.children[0].textContent, /Select documents/);
    assert.equal(mobileTotal.textContent, 'Select your documents');
    rows[0].toggle.checked = true; events.change();
    assert.equal(selectionError.hidden, true);
    events.submit({ preventDefault() { assert.fail('Valid selection was blocked'); } });
    assert.equal(button.disabled, true);
    assert.match(button.textContent, /Submitting/);
});
