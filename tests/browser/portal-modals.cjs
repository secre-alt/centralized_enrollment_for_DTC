// Representative portal markup with the real local stylesheets; no DB writes.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/portal-modals');
fs.mkdirSync(directory, { recursive: true });
const styles = ['vendor/adminlte/dist/css/adminlte.min.css', 'css/dtc-theme.css', 'css/admin-users.css',
    'css/profile.css', 'css/student-welcome.css', 'css/cashier-payment-pages.css', 'css/portal-modals.css']
    .map(file => `<link rel="stylesheet" href="${pathToFileURL(path.join(root, 'public', file)).href}">`).join('');
const controls = '<label for="reason">Reason</label><textarea id="reason" class="form-control" rows="3">The selected section is unavailable.</textarea>';
const footer = '<div class="modal-footer"><button class="btn btn-secondary">Cancel</button><button class="btn btn-primary">Save changes</button></div>';
const variants = {
    payment: `<div id="paymentModal" class="modal show" style="display:block"><div class="modal-dialog modal-lg"><div class="modal-content dtc-review-modal-content"><div class="dtc-review-header"><div class="dtc-review-identity"><span class="dtc-review-avatar">TT</span><div class="dtc-review-identity-copy"><h2 class="dtc-review-name">Trianne Alexandra T. Sano</h2><p class="dtc-review-email">long.student.email@example.edu.ph</p></div></div><div class="dtc-review-header-right"><span class="dtc-status-badge is-warning">GCash pending</span><button class="dtc-review-close" aria-label="Close">×</button></div></div><div class="dtc-review-body"><div class="dtc-review-info-grid"><div><span class="dtc-review-info-label">Program</span><span class="dtc-review-info-value">BS Tourism Management</span></div><div><span class="dtc-review-info-label">Submitted</span><span class="dtc-review-info-value">Nov 14, 2026 10:12 AM</span></div></div><div class="dtc-payment-fee"><span>Enrollment fee</span><strong>₱500.00</strong></div><div class="dtc-payment-info-notice">Cashier payment verification sends this enrollment to the Registrar for subject review.</div></div><div class="dtc-review-footer"><div class="dtc-review-footer-default"><div class="dtc-review-footer-right"><button class="dtc-btn dtc-btn-outline-danger">Reject</button><form><button class="dtc-btn dtc-btn-success">Verify payment</button></form></div></div></div></div></div></div>`,
    form: `<div class="modal show" style="display:block"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title">Update document request</h2><button class="close" aria-label="Close">×</button></div><div class="modal-body">${controls}</div>${footer}</div></div></div>`,
    review: `<div class="modal show" style="display:block"><div class="modal-dialog modal-lg"><div class="modal-content dtc-review-modal-content"><div class="dtc-review-header"><div><h2 class="dtc-review-name">Review enrollment</h2><p class="dtc-review-email">Student · Enrollment #1042</p></div><button class="dtc-review-close" aria-label="Close">×</button></div><div class="dtc-review-body">${controls}</div>${footer}</div></div></div>`,
    confirm: `<div class="modal dtc-confirm-modal is-danger show" style="display:block"><div class="modal-dialog"><div class="modal-content"><button class="dtc-confirm-close" aria-label="Close">×</button><div class="dtc-confirm-icon-wrap">!</div><div class="modal-body"><h2 class="dtc-confirm-title">Reject enrollment?</h2><p class="dtc-confirm-message">The student will be notified. The verified payment remains in the payment history.</p></div><div class="modal-footer"><button class="dtc-confirm-cancel">Cancel</button><button class="dtc-confirm-ok">Reject enrollment</button></div></div></div></div>`,
    photo: `<div class="modal dtc-avatar-confirm-modal show" style="display:block"><div class="modal-dialog"><div class="modal-content dtc-avatar-confirm-card"><div class="modal-body text-center"><h2 class="dtc-avatar-confirm-title">Use this profile photo?</h2><p class="dtc-avatar-confirm-copy">This will replace your current profile photo.</p><div class="dtc-avatar-confirm-actions"><button class="dtc-confirm-cancel">Cancel</button><button class="dtc-confirm-ok">Save photo</button></div></div></div></div></div>`,
    user: `<div id="editUserModal" class="modal show" style="display:block"><div class="modal-dialog modal-lg"><div class="modal-content dtc-modal-content"><div class="dtc-modal-header"><div class="dtc-modal-header-text"><h5>Edit user</h5><p>Manage account details</p></div></div><div class="modal-body dtc-modal-body">${controls}</div>${footer}</div></div></div>`,
    welcome: '<div class="student-welcome"><section class="student-welcome-dialog"><div class="student-welcome-emblem"><span>✓</span></div><h2>Enrollment approved</h2><p>Your enrollment is complete.</p><button class="student-welcome-primary">Continue</button></section></div>',
};
const frames = [];
for (const theme of ['light', 'dark']) for (const width of [320, 390, 768]) for (const [name, markup] of Object.entries(variants)) {
    const src = `<!doctype html><meta charset="utf-8">${styles}<body class="${theme === 'dark' ? 'dtc-dark' : ''}">${markup}</body>`;
    frames.push(`<iframe title="${name}-${theme}-${width}" width="${width}" height="520" srcdoc="${src.replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></iframe>`);
}
const html = `<!doctype html><meta charset="utf-8"><style>body{background:#e2e8f0;font:14px sans-serif}iframe{border:1px solid #94a3b8;margin:8px}</style><h1>Portal modal verification</h1>${frames.join('')}<script>
window.addEventListener('load', function () {
  const failures = [];
  for (const frame of document.querySelectorAll('iframe')) {
    const doc = frame.contentDocument, win = frame.contentWindow;
    const card = doc.querySelector('.modal-content, .student-welcome-dialog');
    const rect = card.getBoundingClientRect(), style = win.getComputedStyle(card);
    function check(ok, message) { if (!ok) failures.push(frame.title + ': ' + message); }
    check(style.borderTopLeftRadius === '12px', 'corner radius ' + style.borderTopLeftRadius);
    check(rect.left >= -1 && rect.right <= win.innerWidth + 1, 'horizontal overflow');
    check(style.backgroundImage === 'none', 'decorative surface');
    const primary = doc.querySelector('.btn-primary, .dtc-btn-success, .dtc-confirm-ok, .student-welcome-primary');
    check(primary.getBoundingClientRect().height >= 44, 'small primary control');
    if (doc.querySelector('.dtc-confirm-modal')) check(style.textAlign === 'center', 'confirmation alignment');
    const title = doc.querySelector('h2, h5');
    check(parseFloat(win.getComputedStyle(title).fontSize) >= 18, 'title hierarchy');
    if (doc.querySelector('#paymentModal')) {
      for (const text of doc.querySelectorAll('.dtc-review-name, .dtc-review-email')) {
        check(win.getComputedStyle(text).whiteSpace === 'normal', 'identity is truncated');
        check(text.scrollWidth <= text.clientWidth + 1, 'identity overflows');
      }
      const close = doc.querySelector('.dtc-review-close').getBoundingClientRect();
      const identity = doc.querySelector('.dtc-review-identity').getBoundingClientRect();
      check(close.left >= identity.right - 1, 'close overlaps identity');
      check(primary.getBoundingClientRect().right <= rect.right, 'action overflows');
    }
  }
  document.body.dataset.result = failures.length ? JSON.stringify(failures) : 'passed';
});
</script>`;
const fixture = path.join(directory, 'fixture.html');
fs.writeFileSync(fixture, html);
const browser = process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const result = spawnSync(browser, ['--headless=new', '--no-first-run', '--disable-background-networking', '--disable-extensions',
    '--disable-gpu', '--in-process-gpu', '--user-data-dir=' + path.join(directory, 'profile'), '--window-size=1440,1100',
    '--screenshot=' + path.join(directory, 'preview.png'), '--dump-dom', pathToFileURL(fixture).href],
{ windowsHide: true, timeout: 45000, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 });
assert.ifError(result.error);
assert.equal(result.status, 0, result.stderr.slice(-1500));
const actual = result.stdout.match(/data-result="([^"]*)"/);
assert.ok(actual, 'Browser did not finish fixture verification');
assert.equal(actual[1], 'passed');
console.log('PASS: 42 modal fixtures, seven dialog families, two themes, three widths. Preview: ' + path.join(directory, 'preview.png'));
