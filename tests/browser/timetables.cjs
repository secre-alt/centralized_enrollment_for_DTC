// Isolated layout fixture using the actual shared and timetable stylesheets.
// This does not exercise authenticated pages or database publication.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/timetables-layout');
fs.mkdirSync(directory, { recursive: true });
const styles = ['vendor/adminlte/dist/css/adminlte.min.css', 'css/dtc-theme.css', 'css/timetables.css']
    .map(file => `<link rel="stylesheet" href="${pathToFileURL(path.join(root, 'public', file)).href}">`).join('');
const field = (name, value) => `<label>${name}<input class="form-control" value="${value}"></label>`;
const markup = `<section class="card tt-workspace"><div class="card-header"><h5>Draft timetables</h5><p class="tt-help">Prepare group schedules using shared rooms and instructors.</p></div><div class="card-body">
<details class="tt-section" open><summary>Shared rooms and instructors</summary><form class="tt-resource-form"><div class="tt-grid">${field('Room name', 'COMPLAB')}${field('Seat capacity', '40')}${field('Facility', 'Computer lab')}</div><div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Save room</button></div></form></details>
<div class="tt-draft-heading"><h5>BSTM 1A <span class="tt-help">Demonstration only</span></h5><p class="tt-help">BS Tourism Management · Year 1 · 2026-2027 · Semester 1 · 30 students</p></div>
<p class="tt-notice">Demonstration data. These fictional presets cannot become live offerings.</p>
<details class="tt-section" open><summary>CSV and draft tools</summary>
<p class="tt-help">Export saved assignments as a CSV template for Excel. Keep column headings and subject IDs unchanged.</p>
<div class="tt-actions"><a class="dtc-btn dtc-btn-secondary" href="#">Export CSV</a></div>
<form><label>Import CSV preview<input class="form-control" type="file" accept=".csv,text/csv"></label><p class="tt-help">Nothing is saved until you choose Save review.</p><div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Preview CSV</button></div></form>
<form><label class="tt-confirm"><input type="checkbox"> Clear all schedule assignments for this draft</label><div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Clear draft schedule</button></div></form></details>
<section class="tt-saved-summary"><h6>Saved schedule</h6><p class="tt-help">1 of 1 subjects placed &middot; 0 need attention</p><div class="tt-table-wrap" tabindex="0"><table class="tt-table"><thead><tr><th>Subject</th><th>Days / time</th><th>Room</th><th>Instructor</th><th>Review</th></tr></thead><tbody><tr><th>GE107</th><td>MWF<br>08:00&ndash;09:00</td><td>DEMO 1T</td><td>Demonstration instructor</td><td>Placed</td></tr></tbody></table></div></section><form><div class="tt-subject"><div class="tt-subject-title"><strong>GE107</strong><span>The Contemporary World with Indigenous People Studies · 3 units</span></div><div class="tt-grid">${field('Instructor', 'Demonstration instructor')}${field('Meeting days', 'MWF')}${field('Minutes per meeting', '60')}${field('Required facility', 'Classroom')}${field('Room', 'DEMO 1T · 40 seats')}${field('Start', '08:00')}${field('End', '09:00')}</div><p class="tt-issue">No placement found with these resources and availability. Adjust the requirements or place it manually.</p></div>
<div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Save review</button><button class="dtc-btn dtc-btn-primary">Generate suggestions</button></div></form>
<form class="tt-publish"><label class="tt-confirm"><input type="checkbox"> I reviewed the saved timetable, teaching requirements and resource details</label><div class="tt-actions"><button class="dtc-btn dtc-btn-primary">Publish saved timetable</button></div></form></div></section>`;
const frames = [];
for (const theme of ['light', 'dark']) for (const width of [320, 768, 1440]) {
    const source = `<!doctype html><meta charset="utf-8">${styles}<body class="${theme === 'dark' ? 'dtc-dark' : ''}" style="margin:0;padding:8px">${markup}</body>`;
    frames.push(`<iframe data-width="${width}" data-theme="${theme}" style="width:${width}px;height:1000px;border:0" srcdoc="${source.replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></iframe>`);
}
const fixture = path.join(directory, 'fixture.html');
fs.writeFileSync(fixture, `<!doctype html><meta charset="utf-8"><body>${frames.join('')}<script>
window.addEventListener('load', () => {
  const failures = [];
  for (const frame of document.querySelectorAll('iframe')) {
    const doc = frame.contentDocument;
    const width = Number(frame.dataset.width);
    const check = (ok, message) => { if (!ok) failures.push(width + '/' + frame.dataset.theme + ': ' + message); };
    check(doc.documentElement.scrollWidth <= width, 'page overflows');
    for (const input of doc.querySelectorAll('.form-control')) {
      const box = input.getBoundingClientRect();
      check(box.left >= 0 && box.right <= width, 'field overflows');
      check(box.width >= 120, 'field too narrow');
    }
    for (const actions of doc.querySelectorAll('.tt-actions')) {
      const buttons = [...actions.querySelectorAll('button')];
      buttons.forEach(button => {
        const box = button.getBoundingClientRect();
        check(box.left >= 0 && box.right <= width, 'action overflows');
        check(box.height >= 44, 'action touch target too small');
      });
      if (buttons.length === 2) {
        const a = buttons[0].getBoundingClientRect();
        const b = buttons[1].getBoundingClientRect();
        check(a.right <= b.left || a.bottom <= b.top, 'actions overlap');
      }
    }
  }
  document.body.dataset.result = failures.length ? JSON.stringify(failures) : 'passed';
});
</script>`);
const browser = process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const result = spawnSync(browser, ['--headless=new', '--no-first-run', '--disable-background-networking', '--disable-extensions',
    '--disable-gpu', '--in-process-gpu', '--user-data-dir=' + path.join(directory, 'profile-' + process.pid), '--window-size=1500,1200',
    '--screenshot=' + path.join(directory, 'preview.png'), '--dump-dom', pathToFileURL(fixture).href],
{ windowsHide: true, timeout: 45000, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 });
assert.ifError(result.error);
assert.equal(result.status, 0, result.stderr.slice(-1500));
assert.match(result.stdout, /data-result="passed"/, result.stdout.slice(-2500));
console.log('PASS: timetable fields and action spacing at three widths in light and dark themes. Preview: ' + path.join(directory, 'preview.png'));
