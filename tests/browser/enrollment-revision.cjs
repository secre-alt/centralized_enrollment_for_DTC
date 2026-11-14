// Tests the actual revision script in Edge; no database or application writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const directory = fs.mkdtempSync(path.join(root, 'storage/framework/testing/revision-browser-'));
assert.equal(path.dirname(directory), path.join(root, 'storage/framework/testing'));
const edge = process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const script = pathToFileURL(path.join(root, 'public/js/enrollment-revision.js')).href;
const fixture = path.join(directory, 'fixture.html');
fs.writeFileSync(fixture, `<!doctype html><meta charset="utf-8">
<form id="enrollment-revision-form" data-retained-irregular="0">
<select data-revision-choice data-original="GE101 / Section A">
<option value="">Choose</option><option value="1">GE101 / Section B / host BSIS / 3 units</option>
<option value="2" data-requires-irregular="1">&lt;img src=x onerror=alert(1)&gt; / Section C</option></select>
<input id="confirm-revision" type="checkbox"><div id="enrollment-revision-review" aria-live="polite"></div>
</form><script src="${script}"></script><script>
try {
    const review = document.querySelector('#enrollment-revision-review');
    const select = document.querySelector('select');
    const confirmation = document.querySelector('#confirm-revision');
    function check(condition, message) { if (!condition) throw Error(message); }
    check(review.textContent.includes('GE101 / Section A'), 'Original selection missing');
    check(review.textContent.includes('Choose a section'), 'Initial guidance missing');
    select.value = '1'; select.dispatchEvent(new Event('change'));
    check(review.textContent.includes('Section B / host BSIS'), 'Replacement review missing');
    check(review.textContent.includes('Resulting load: Regular'), 'Regular load preview missing');
    confirmation.checked = true;
    select.value = '2'; select.dispatchEvent(new Event('change'));
    check(!confirmation.checked, 'Confirmation did not reset');
    check(!review.querySelector('img'), 'Untrusted markup was interpreted');
    check(review.textContent.includes('<img src=x'), 'Literal text missing');
    check(review.textContent.includes('Resulting load: Irregular'), 'Irregular load preview missing');
    const form = document.querySelector('form');
    form.dataset.retainedIrregular = '1';
    select.value = '1'; select.dispatchEvent(new Event('change'));
    check(review.textContent.includes('Resulting load: Irregular'), 'Retained Irregular policy missing');
    document.body.dataset.testResult = 'passed';
} catch (error) { document.body.dataset.testResult = error.message; }
</script>`);
try {
    const result = spawnSync(edge, ['--headless=new', '--no-first-run', '--disable-background-networking',
        '--disable-extensions', '--disable-gpu', '--in-process-gpu', '--user-data-dir=' + path.join(directory, 'profile'),
        '--dump-dom', pathToFileURL(fixture).href],
    { windowsHide: true, timeout: 45000, encoding: 'utf8', maxBuffer: 1024 * 1024 });
    assert.ifError(result.error);
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /data-test-result="passed"/, result.stdout.slice(-3000));
    console.log('PASS: original/new review, confirmation reset, and safe text rendering in Edge.');
} finally {
    fs.rmSync(directory, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
}
