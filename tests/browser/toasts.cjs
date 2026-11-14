const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/toast-polish');
fs.mkdirSync(directory, { recursive: true });
const url = file => pathToFileURL(path.join(root, 'public', file)).href;
const frames = [];
for (const theme of ['light', 'dark']) for (const width of [320, 768]) {
    const src = `<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="${url('css/dtc-theme.css')}"><link rel="stylesheet" href="${url('css/notifications.css')}">
<body class="${theme === 'dark' ? 'dtc-dark' : ''}"><div id="notify-list" data-last-notification-id="0" data-history-url="/notifications"></div>
<script src="${url('js/dtc-feedback.js')}"></script><script src="${url('js/notification-toasts.js')}"></script><script>
window.addEventListener('load', function () {
  for (const type of ['success','error','warning','info']) window.dtcToast.show({ type, title:type, message:'The Registrar has recorded the update. Reference DTC-2026-1042.', duration:0 });
  window.dtcIncomingNotifications([{ id:1, title:'Document ready for pickup', message:'Your requested document is ready. Check the pickup instructions.', open_url:'/notifications/1/open' }]);
});</script></body>`;
    frames.push(`<iframe title="${theme}-${width}" width="${width}" height="820" srcdoc="${src.replaceAll('&','&amp;').replaceAll('"','&quot;')}"></iframe>`);
}
fs.writeFileSync(path.join(directory, 'fixture.html'), `<!doctype html><style>body{background:#dbe3ee;font:14px sans-serif}iframe{margin:8px;border:1px solid #94a3b8}</style><h1>DTC toast verification</h1>${frames.join('')}<script>
window.addEventListener('load', function () {
 const failures=[];
 for (const frame of document.querySelectorAll('iframe')) {
  const doc=frame.contentDocument, win=frame.contentWindow;
  for (const card of doc.querySelectorAll('.dtc-toast, .notice-floating')) {
   const rect=card.getBoundingClientRect(), style=win.getComputedStyle(card);
   if(rect.left < -1 || rect.right > win.innerWidth + 1) failures.push(frame.title+' overflow');
   if(style.borderTopLeftRadius!=='12px') failures.push(frame.title+' radius');
   if(style.backgroundImage!=='none') failures.push(frame.title+' background');
   const close=card.querySelector('.dtc-toast-close, .notice-floating-close');
   if(!close || !close.getAttribute('aria-label')) failures.push(frame.title+' inaccessible dismiss');
   if(win.innerWidth<576 && close.getBoundingClientRect().height<44) failures.push(frame.title+' small dismiss');
  }
  if(doc.querySelectorAll('.dtc-toast').length!==4) failures.push(frame.title+' missing variants');
  const incoming=doc.querySelector('.notice-floating');
  if(!incoming) failures.push(frame.title+' missing incoming');
  else if(incoming.getBoundingClientRect().top < doc.querySelector('#dtc-toast-container').getBoundingClientRect().bottom) failures.push(frame.title+' overlapping stacks');
 }
 document.body.dataset.result=failures.length?JSON.stringify(failures):'passed';
});</script>`);
const result = spawnSync(process.env.EDGE_BINARY || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    ['--headless=new','--no-first-run','--disable-background-networking','--disable-extensions','--disable-gpu','--in-process-gpu',
        '--user-data-dir='+path.join(directory,'profile'),'--window-size=1440,1000','--virtual-time-budget=1000','--screenshot='+path.join(directory,'preview.png'),
        '--dump-dom',pathToFileURL(path.join(directory,'fixture.html')).href],
    { windowsHide:true, timeout:45000, encoding:'utf8', maxBuffer:4*1024*1024 });
assert.ifError(result.error);
assert.equal(result.status,0,result.stderr.slice(-1000));
assert.equal(result.stdout.match(/data-result="([^"]*)"/)?.[1],'passed');
console.log('PASS: four toast variants and incoming updates in both themes at 320px and 768px.');
