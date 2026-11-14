// Real styles, representative filter structures, disposable browser profile; no database.
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/list-filters');
fs.mkdirSync(directory, { recursive: true });
const styles = ['vendor/adminlte/dist/css/adminlte.min.css', 'css/dtc-theme.css', 'css/dtc-filters.css', 'css/registrar-workspace.css', 'css/cashier-payment-pages.css', 'css/document-request-flow.css', 'css/admin-records.css', 'css/dtc-layout.css']
    .map(file => `<link rel="stylesheet" href="${pathToFileURL(path.join(root, 'public', file)).href}">`).join('');
const variants = { review: 7, documents: 5, audit: 4, applications: 6, history: 3 };
const frames = [];
for (const theme of ['light', 'dark']) for (const width of [320, 390, 768, 1440]) for (const [name, count] of Object.entries(variants)) {
    const fields = Array.from({ length: count }, (_, index) => `<div class="dtc-fl-field"><label for="field-${index}">${index % 2 ? 'Submission date through' : 'Program / actor'}</label>${index % 2 ? `<input class="form-control" id="field-${index}" type="date" value="2026-11-14">` : `<select class="form-control" id="field-${index}"><option>Long program or actor name (staff@example.edu.ph)</option></select>`}</div>`).join('');
    const markup = `<form class="dtc-fl dtc-fl-expanded"><div class="dtc-fl-toolbar"><div class="dtc-fl-search"><input class="form-control" type="search" placeholder="Search records"></div><button class="dtc-fl-toggle" type="button">Filters <span class="dtc-fl-count">3</span></button></div><div class="collapse show"><div class="dtc-fl-panel">${fields}<div class="dtc-fl-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="dtc-fl-clear" href="#">Clear all</a></div></div></div></form>`;
    const source = `<!doctype html><meta charset="utf-8">${styles}<body class="${theme === 'dark' ? 'dtc-dark' : ''}"><div class="content-wrapper" style="margin-left:0"><div class="content"><div class="container-fluid"><div class="${name === 'review' ? 'registrar-workspace' : name === 'documents' ? 'cashier-payment-page' : name === 'history' ? 'dr dr-index dr-alumni-history' : ''}">${markup}</div></div></div></div>`;
    frames.push(`<iframe title="${name}-${theme}-${width}" width="${width}" height="850" srcdoc="${source.replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></iframe>`);
}
const script = `window.onload=function(){const errors=[];for(const frame of document.querySelectorAll('iframe')){const d=frame.contentDocument,w=frame.contentWindow,p=d.querySelector('.dtc-fl-panel'),r=p.getBoundingClientRect();function check(ok,message){if(!ok)errors.push(frame.title+': '+message);}check(d.documentElement.scrollWidth<=w.innerWidth,'page overflow');for(const c of d.querySelectorAll('.form-control,.dtc-fl-actions > *')){const cr=c.getBoundingClientRect();check(cr.left>=r.left-1&&cr.right<=r.right+1,'control overflow');check(cr.height>=44,'short control');}if(w.innerWidth<480)check(w.getComputedStyle(p).gridTemplateColumns.split(' ').length===1,'phone columns');}document.body.dataset.result=errors.length?JSON.stringify(errors):'passed';}`;
const fixture = path.join(directory, 'fixture.html');
fs.writeFileSync(fixture, `<!doctype html><meta charset="utf-8">${frames.join('')}<script>${script}</script>`);
const chrome = 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe';
const browser = process.env.EDGE_BINARY || (fs.existsSync(chrome) ? chrome : 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe');
const result = spawnSync(browser, ['--headless=new', '--no-first-run', '--disable-background-networking', '--disable-extensions', '--disable-gpu', '--in-process-gpu', '--user-data-dir='+path.join(directory, 'profile-'+Date.now()), '--dump-dom', pathToFileURL(fixture).href], { windowsHide: true, timeout: 45000, encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
assert.ifError(result.error);
assert.equal(result.status, 0, result.stderr.slice(-1500));
const actual = result.stdout.match(/data-result="([^"]*)"/);
assert.ok(actual, 'Browser did not finish fixture verification');
assert.equal(actual[1], 'passed');
console.log('PASS: 40 filter fixtures; five page families, light/dark, four widths, control sizes and overflow.');
