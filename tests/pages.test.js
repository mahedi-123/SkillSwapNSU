/* Load every SkillSwap page in jsdom, run its scripts against the real
   data.js + ui.js, and report any JavaScript error or empty container. */
const fs = require('fs');
const path = require('path');
const { JSDOM, VirtualConsole } = require('jsdom');

const ROOT = path.resolve(__dirname, '..');
const PAGES = ['index.html', 'login.html', 'register.html', 'dashboard.html',
               'search.html', 'profile.html', 'edit-profile.html',
               'requests.html', 'sessions.html', 'reviews.html', 'admin.html'];

const dataJs = fs.readFileSync(path.join(ROOT, 'static/js/data.js'), 'utf8');
const uiJs   = fs.readFileSync(path.join(ROOT, 'static/js/ui.js'), 'utf8');

/* minimal Bootstrap stub — we only need the constructors not to throw */
const BOOTSTRAP_STUB = `
window.bootstrap = {
  Modal: class { constructor(el){this.el=el;} show(){} hide(){}
                 static getInstance(){ return new window.bootstrap.Modal(); } },
  Toast: class { constructor(){} show(){} hide(){} }
};`;

let failures = 0;

for (const page of PAGES) {
  const file = path.join(ROOT, page);
  let html = fs.readFileSync(file, 'utf8');

  /* strip the CDN tags; inline our local files instead */
  /* replacer FUNCTIONS: a plain string would let $$ / $& be interpreted */
  html = html
    .replace(/<script src="https:\/\/cdn[^"]*"><\/script>/g,
             () => '<script>' + BOOTSTRAP_STUB + '</script>')
    .replace(/<script src="static\/js\/data\.js"><\/script>/,
             () => '<script>' + dataJs + '</script>')
    .replace(/<script src="static\/js\/ui\.js"><\/script>/,
             () => '<script>' + uiJs + '</script>')
    .replace(/<link[^>]*https:\/\/[^>]*>/g, () => '');

  const errors = [];
  const vc = new VirtualConsole();
  vc.on('jsdomError', e => errors.push(e.message + '\n    ' + (e.stack || '').split('\n')[1]));
  vc.on('error', (...a) => errors.push('console.error: ' + a.join(' ')));

  const url = 'http://localhost/' + page + (page === 'profile.html' ? '?id=19' : '');
  const dom = new JSDOM(html, { runScripts: 'dangerously', virtualConsole: vc, url });

  const doc = dom.window.document;
  /* visible text only — script/style contents are not shown to the user */
  const clone = doc.body.cloneNode(true);
  Array.from(clone.querySelectorAll('script, style, datalist')).forEach(n => n.remove());
  const bodyText = clone.textContent.replace(/\s+/g, ' ').trim();

  /* any container that stayed empty is a silent render failure */
  const empties = Array.from(doc.querySelectorAll('[id]'))
    .filter(el => /List$|Grid$|Row$|Body$|Table$|Stats$|Col$|Chips$|results|header|skillBlocks|toReview|adminPanel|shellNav|shellRail/.test(el.id))
    .filter(el => !el.innerHTML.trim())
    .map(el => '#' + el.id);

  const status = errors.length ? 'FAIL' : (empties.length ? 'WARN' : 'ok  ');
  if (errors.length) failures++;

  console.log(`[${status}] ${page.padEnd(18)} text=${String(bodyText.length).padStart(5)}ch` +
              `  empty=${empties.length ? empties.join(',') : '-'}`);
  errors.slice(0, 4).forEach(e => console.log('        ' + e.split('\n').join('\n        ')));

  /* leftover template literals mean a string was not interpolated */
  if (/\$\{/.test(bodyText)) {
    console.log('        !! un-interpolated ${...} visible in text');
    failures++;
  }
  /* stray "undefined" or "[object Object]" in rendered output */
  ['undefined', '[object Object]', 'NaN'].forEach(bad => {
    if (bodyText.includes(bad)) console.log(`        !! rendered output contains "${bad}"`);
  });
}

console.log(failures ? `\n${failures} page(s) with errors` : '\nAll pages executed cleanly');
