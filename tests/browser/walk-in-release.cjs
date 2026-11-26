// Native form checks against rendered testing-only Blade HTML; no network or database access.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const testing = path.join(root, 'storage/framework/testing');
const source = path.join(testing, 'walk-in-release-form.html');
assert.ok(fs.existsSync(source), 'Run WalkInDocumentReleaseTest first to render the testing fixture.');
const directory = fs.mkdtempSync(path.join(testing, 'walk-in-browser-'));
assert.equal(path.dirname(path.resolve(directory)), testing);
const html = fs.readFileSync(source, 'utf8').match(/<form\b[^>]*action="[^"]*\/documents\/walk-in-release"[\s\S]*?<\/form>/i)?.[0];
assert.ok(html, 'Rendered collection form missing.');
const fixture = path.join(directory, 'fixture.html');
fs.writeFileSync(fixture, '<!doctype html><meta charset="utf-8"><body>' + html + `<script>
try {
    function check(value, message) { if (!value) throw Error(message); }
    const form = document.querySelector('form[action$="/documents/walk-in-release"]');
    check(form, 'Collection form missing');
    const eligible = form.querySelectorAll('input[name="document_ids[]"]:enabled');
    const unpaid = form.querySelectorAll('input[name="document_ids[]"]:disabled');
    check(eligible.length === 2 && unpaid.length === 1, 'Payment eligibility controls missing');
    check(!form.checkValidity(), 'Physical handover confirmation must be required');
    eligible[0].checked = true;
    check(!form.checkValidity(), 'Selection alone must not confirm physical handover');
    form.querySelector('[name="handover_confirmed"]').checked = true;
    check(form.checkValidity(), 'Confirmed selection should be valid');
    const data = new FormData(form);
    check(data.getAll('document_ids[]').length === 1 && data.getAll('document_ids[]')[0] === eligible[0].value, 'Unpaid or unselected document entered the command');
    check(data.get('operation_key') && data.get('claimant_id'), 'Command identity missing');
    document.body.dataset.testResult = 'passed';
} catch (error) { document.body.dataset.testResult = error.message; }
</script>`);
try {
    const edge = process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
    const result = spawnSync(edge, ['--headless=new', '--no-first-run', '--disable-background-networking',
        '--disable-extensions', '--disable-gpu', '--in-process-gpu', '--user-data-dir=' + path.join(directory, 'profile'),
        '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', timeout: 30000, windowsHide: true });
    assert.ifError(result.error);
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /data-test-result="passed"/, result.stdout.slice(-3000));
    console.log('Edge rendered walk-in form: payment controls, exact selection, command identity and required physical confirmation passed.');
} finally {
    assert.equal(path.dirname(path.resolve(directory)), testing);
    fs.rmSync(directory, { recursive: true, force: true });
}
