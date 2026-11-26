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
<p class="tt-help">Current term: <strong>2026-2027 / 1st</strong>. New drafts use this term automatically.</p>
<div class="tt-start-options">
<details class="tt-start-option" open><summary>Try demo <span class="tt-help">Fictional sample schedule</span></summary><p class="tt-help">Choose a program and year. We fill a fictional group of 30 students, room, instructor and 60-minute MWF requirements. Demonstrations cannot be published.</p><form><div class="tt-grid"><label>Program<select class="form-control"><option>BS Tourism Management</option></select></label><label>Year level<select class="form-control"><option>Year 1</option></select></label></div><div class="tt-actions"><button class="dtc-btn dtc-btn-primary">Try demo</button></div></form></details>
<details class="tt-start-option" open><summary>Copy timetable <span class="tt-help">Reuse teaching requirements</span></summary><p class="tt-help">Program, year, mode and latest class end come from the source. All assignments are checked again.</p><form><div class="tt-grid"><label>Source timetable<select class="form-control"><option>BSTM 1A / BS Tourism Management / Year 1 / 2026-2027 Sem 1</option></select></label>${field('New student group', 'BSTM 1B')}${field('Group size (optional)', '')}</div><div class="tt-actions"><button class="dtc-btn dtc-btn-primary">Copy to new draft</button></div></form></details>
<details class="tt-start-option" open><summary>Import CSV <span class="tt-help">Preview before saving</span></summary><p class="tt-help">Start from an exported CSV. Need a sample? Try demo, then export it.</p><form><div class="tt-grid">${field('Program', 'BS Tourism Management')}${field('Year level', 'Year 1')}${field('Student group', 'BSTM 1A')}${field('Group size', '30')}</div><label>Mode<select class="form-control"><option>Demonstration - cannot publish</option></select></label><p class="tt-help">For sample CSVs with demo-room and demo-instructor IDs, choose Demonstration.</p><label>CSV file<input type="file" class="form-control"></label><div class="tt-actions"><button class="dtc-btn dtc-btn-primary">Preview CSV</button></div></form><section class="tt-saved-summary"><h6>CSV preview - not saved</h6><div class="tt-table-wrap"><table class="tt-table tt-schedule-table"><thead><tr><th>Subject</th><th>Days / time</th><th>Room</th><th>Instructor</th><th>Review</th></tr></thead><tbody><tr><th scope="row">GE107</th><td data-label="Days / time"><span>MWF 08:00-09:00</span></td><td data-label="Room"><span>Unassigned</span></td><td data-label="Instructor"><span>Demonstration instructor</span></td><td data-label="Review"><span>Room not found. Check the selected mode.</span></td></tr></tbody></table></div><div class="tt-actions"><button class="dtc-btn dtc-btn-primary">Save draft</button></div></section></details>
</div>
<details class="tt-section" open><summary>Shared rooms and instructors</summary><form class="tt-resource-form"><div class="tt-grid">${field('Room name', 'COMPLAB')}${field('Seat capacity', '40')}${field('Facility', 'Computer lab')}</div><div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Save room</button></div></form></details>
<div class="tt-draft-heading"><h5>BSTM 1A <span class="tt-help">Demonstration only</span></h5><p class="tt-help">BS Tourism Management · Year 1 · 2026-2027 · Semester 1 · 30 students</p></div>
<p class="tt-notice">Demonstration data. These fictional presets cannot become live offerings.</p>
<details class="tt-section" open><summary>CSV and draft tools</summary>
<p class="tt-help">Export saved assignments as a CSV template for Excel. Keep column headings and subject IDs unchanged.</p>
<div class="tt-actions"><a class="dtc-btn dtc-btn-secondary" href="#">Export CSV</a></div>
<form><label>Import CSV preview<input class="form-control" type="file" accept=".csv,text/csv"></label><p class="tt-help">Nothing is saved until you choose Save review.</p><div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Preview CSV</button></div></form>
<form><label class="tt-confirm"><input type="checkbox"> Clear all schedule assignments for this draft</label><div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Clear draft schedule</button></div></form></details>
<section class="tt-saved-summary"><h6>Saved schedule</h6><p class="tt-help">1 of 1 subjects placed &middot; 0 need attention</p><div class="tt-table-wrap" tabindex="0"><table class="tt-table tt-schedule-table"><thead><tr><th>Subject</th><th>Days / time</th><th>Room</th><th>Instructor</th><th>Review</th></tr></thead><tbody><tr><th scope="row">GE107</th><td data-label="Days / time"><span>MWF<br>08:00&ndash;09:00</span></td><td data-label="Room"><span>DEMO 1T</span></td><td data-label="Instructor"><span>Demonstration instructor</span></td><td data-label="Review"><span>Placed</span></td></tr></tbody></table></div></section><form><div class="tt-subject tt-needs-attention"><div class="tt-subject-title"><strong>GE107</strong><span class="tt-attention-label">Needs attention</span><span>The Contemporary World with Indigenous People Studies · 3 units</span></div><div class="tt-grid">${field('Instructor', 'Demonstration instructor')}${field('Meeting days', 'MWF')}${field('Minutes per meeting', '60')}${field('Required facility', 'Classroom')}${field('Room', 'DEMO 1T · 40 seats')}${field('Start', '08:00')}${field('End', '09:00')}</div><p class="tt-issue">No placement found with these resources and availability. Adjust the requirements or place it manually.</p></div>
<div class="tt-actions"><button class="dtc-btn dtc-btn-secondary">Save review</button><button class="dtc-btn dtc-btn-primary">Generate suggestions</button></div></form>
<form class="tt-publish"><label class="tt-confirm"><input type="checkbox"> I reviewed the saved timetable, teaching requirements and resource details</label><div class="tt-actions"><button class="dtc-btn dtc-btn-primary">Publish saved timetable</button></div></form></div></section>`;
const frames = [];
for (const theme of ['light', 'dark']) for (const width of [320, 375, 390, 768, 1024, 1440]) {
    const source = `<!doctype html><meta charset="utf-8">${styles}<body class="${theme === 'dark' ? 'dtc-dark' : ''}" style="margin:0;padding:8px"><div class="wrapper"><main class="content-wrapper" style="margin-left:0!important">${markup}</main><footer class="main-footer" style="margin-left:0!important"><div class="dtc-footer-content"><span>Danao Technological College</span><span class="dtc-footer-system">Centralized Enrollment System</span></div></footer></div></body>`;
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
    doc.documentElement.style.scrollBehavior = 'auto';
    frame.contentWindow.scrollTo({top: doc.documentElement.scrollHeight, behavior: 'instant'});
    const finalButton = [...doc.querySelectorAll('button')].at(-1).getBoundingClientRect();
    check(finalButton.bottom <= doc.querySelector('.main-footer').getBoundingClientRect().top, 'footer obscures final action');
    check(doc.querySelectorAll('.tt-start-option').length === 3, 'missing entry action');
    if (width < 768) for (const cell of doc.querySelectorAll('.tt-schedule-table td')) {
      const box = cell.getBoundingClientRect();
      check(box.left >= 0 && box.right <= width, 'schedule detail clipped');
      check(frame.contentWindow.getComputedStyle(cell).display === 'grid', 'mobile schedule not stacked');
      check(!!cell.dataset.label, 'mobile detail lacks a label');
    }
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
  for (const frame of document.querySelectorAll('iframe')) {
    const preview = frame.contentDocument.querySelector('.tt-saved-summary');
    frame.contentWindow.scrollTo({top: preview.getBoundingClientRect().top + frame.contentWindow.scrollY - 24, behavior: 'instant'});
  }
  document.body.dataset.result = failures.length ? JSON.stringify(failures) : 'passed';
});
</script>`);
const browser = process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const result = spawnSync(browser, ['--headless=new', '--no-first-run', '--disable-background-networking', '--disable-extensions',
    '--host-resolver-rules=MAP fonts.googleapis.com ~NOTFOUND,MAP fonts.gstatic.com ~NOTFOUND',
    '--disable-gpu', '--in-process-gpu', '--user-data-dir=' + path.join(directory, 'profile-' + process.pid), '--window-size=1500,1200',
    '--screenshot=' + path.join(directory, 'preview.png'), '--dump-dom', pathToFileURL(fixture).href],
{ windowsHide: true, timeout: 45000, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 });
assert.ifError(result.error);
assert.equal(result.status, 0, result.stderr.slice(-1500));
assert.match(result.stdout, /data-result="passed"/, result.stdout.match(/data-result="([^"]*)"/)?.[1] || result.stdout.slice(-2500));
console.log('PASS: timetable fields and action spacing at six widths in light and dark themes. Preview: ' + path.join(directory, 'preview.png'));
