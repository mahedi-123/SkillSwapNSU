/* Exercise each of the seven motion pieces the way a visitor would. */
const fs = require('fs');
const path = require('path');
const { JSDOM, VirtualConsole } = require('jsdom');

const ROOT = path.resolve(__dirname, '..');
const dataJs = fs.readFileSync(path.join(ROOT, 'static/js/data.js'), 'utf8');
const uiJs   = fs.readFileSync(path.join(ROOT, 'static/js/ui.js'), 'utf8');
const css    = fs.readFileSync(path.join(ROOT, 'static/css/style.css'), 'utf8');
const STUB = `window.bootstrap={Modal:class{show(){}hide(){}static getInstance(){return{hide(){}}}},Toast:class{show(){}}};`;

/* hover / motion are read from matchMedia, so tests can dictate them */
function load(page, { hover = true, reduced = false, query = '' } = {}) {
  let html = fs.readFileSync(path.join(ROOT, page), 'utf8')
    .replace(/<script src="https:\/\/cdn[^"]*"><\/script>/g, () => '<script>' + STUB + '</script>')
    .replace(/<script src="static\/js\/data\.js"><\/script>/, () => '<script>' + dataJs + '</script>')
    .replace(/<script src="static\/js\/ui\.js"><\/script>/, () => '<script>' + uiJs + '</script>')
    .replace(/<link[^>]*https:\/\/[^>]*>/g, () => '');

  const shim = `<script>window.matchMedia = q => ({
    matches: /hover: hover/.test(q) ? ${hover}
           : /prefers-reduced-motion/.test(q) ? ${reduced} : false,
    media: q, addListener(){}, removeListener(){}, addEventListener(){}, removeEventListener(){}
  });</script>`;
  html = html.replace('<body>', '<body>' + shim);

  const errs = [];
  const vc = new VirtualConsole();
  vc.on('jsdomError', e => { if (!/scrollTo|Not implemented/.test(e.message)) errs.push(e.message); });
  const dom = new JSDOM(html, { runScripts: 'dangerously', virtualConsole: vc,
                                url: 'http://localhost/' + page + query, pretendToBeVisual: true });
  return { dom, doc: dom.window.document, win: dom.window, errs };
}

let pass = 0, fail = 0;
const ck = (l, c, d = '') => {
  if (c) { pass++; console.log('  ok   ' + l + (d ? '  (' + d + ')' : '')); }
  else { fail++; console.log('  FAIL ' + l + '  ' + d); }
};
const G = (w, e) => w.eval(e);

function pointer(win, el, type, x, y) {
  const e = new win.Event(type, { bubbles: true });
  Object.defineProperty(e, 'clientX', { value: x });
  Object.defineProperty(e, 'clientY', { value: y });
  Object.defineProperty(e, 'pointerType', { value: 'mouse' });
  el.dispatchEvent(e);
}

console.log('\n--- 11. scroll progress');
{
  const { doc, win, errs } = load('index.html');
  ck('page runs clean', errs.length === 0, errs[0] || '');
  const bar = doc.querySelector('#scrollBar');
  ck('bar exists and starts empty', !!bar && (bar.style.width === '0%' || bar.style.width === ''),
     bar.style.width || 'unset');
  ck('bar sits above the page, not in it',
     /\.scroll-progress\s*\{[^}]*position:\s*fixed/.test(css)
     && /\.scroll-progress\s*\{[^}]*pointer-events:\s*none/.test(css));
}

console.log('\n--- 9. cursor spotlight');
{
  const { doc, win } = load('index.html');
  const sp = doc.querySelector('#spotlight');
  ck('spotlight present', !!sp);
  ck('unlit until the pointer moves', !sp.classList.contains('lit'));
  pointer(win, win, 'pointermove', 400, 300);
  ck('lights up on pointer move', sp.classList.contains('lit'));
  ck('never intercepts clicks', /\.spotlight\s*\{[^}]*pointer-events:\s*none/.test(css));

  const touch = load('index.html', { hover: false });
  pointer(touch.win, touch.win, 'pointermove', 300, 200);
  ck('skipped entirely without a pointer device',
     !touch.doc.querySelector('#spotlight').classList.contains('lit'));
}

(async function tiltTests(){
console.log('\n--- 15. card tilt');
{
  const { doc, win } = load('index.html');
  const cards = doc.querySelectorAll('[data-tilt]');
  ck('tilt applied to the feature cards', cards.length === 6, cards.length + ' cards');
  const c = cards[0];
  c.getBoundingClientRect = () => ({ left: 0, top: 0, width: 300, height: 200 });
  pointer(win, c, 'pointermove', 300, 200);          /* bottom-right corner */
  await new Promise(r => setTimeout(r, 40));        /* the tilt lands on the next frame */
  const ry = parseFloat(c.style.getPropertyValue('--ry'));
  const rx = parseFloat(c.style.getPropertyValue('--rx'));
  ck('tilts toward the pointer', ry > 0 && rx < 0, `rx ${rx} ry ${ry}`);
  ck('stays within a few degrees', Math.abs(rx) <= 3.6 && Math.abs(ry) <= 3.6);
  pointer(win, c, 'pointerleave', 0, 0);
  await new Promise(r => setTimeout(r, 40));
  ck('returns flat on leave',
     parseFloat(c.style.getPropertyValue('--rx')) === 0
     && parseFloat(c.style.getPropertyValue('--ry')) === 0);

  const touch = load('index.html', { hover: false });
  const tc = touch.doc.querySelector('[data-tilt]');
  tc.getBoundingClientRect = () => ({ left: 0, top: 0, width: 300, height: 200 });
  pointer(touch.win, tc, 'pointermove', 300, 200);
  await new Promise(r => setTimeout(r, 40));
  ck('not wired on touch', !tc.style.getPropertyValue('--rx'));
}

})().then(() => {
console.log('\n--- 12. self-advancing steps');
{
  const { doc, win } = load('index.html');
  const steps = doc.querySelectorAll('.step');
  ck('four steps found', steps.length === 4);
  ck('the first is cued on load', steps[0].classList.contains('cued'));
  ck('only one cued at a time', doc.querySelectorAll('.step.cued').length === 1);

  steps[2].dispatchEvent(new win.Event('pointerenter', { bubbles: true }));
  ck('hovering takes control', steps[2].classList.contains('cued')
     && !steps[0].classList.contains('cued'));
  steps[2].dispatchEvent(new win.Event('pointerleave', { bubbles: true }));
  ck('control handed back on leave', true);

  const rm = load('index.html', { reduced: true });
  ck('reduced motion cues all four at once',
     rm.doc.querySelectorAll('.step.cued').length === 4);
}

console.log('\n--- 13. review slider');
{
  const { doc, win } = load('index.html');
  const A = doc.querySelector('#reviewTrackA'), B = doc.querySelector('#reviewTrackB');
  ck('two rows built', !!A && !!B);
  ck('rows drift opposite ways', B.classList.contains('back'));

  const halvesA = A.querySelectorAll(':scope > span');
  ck('each track written twice for a seamless loop', halvesA.length === 2);
  ck('the duplicate is hidden from screen readers',
     halvesA[1].getAttribute('aria-hidden') === 'true'
     && !halvesA[0].hasAttribute('aria-hidden'));
  ck('both halves carry identical cards',
     halvesA[0].innerHTML === halvesA[1].innerHTML);

  const cards = A.querySelectorAll(':scope > span:first-child .review-card');
  ck('cards rendered', cards.length > 3, cards.length + ' in the top row');

  /* every quote must be a real row, not copy written for the page */
  const real = G(win, 'REVIEWS').map(r => r.comment);
  const shown = [...doc.querySelectorAll('.review-card blockquote')]
    .map(q => q.textContent.replace(/[\u201c\u201d]/g, '').trim());
  ck('every quote comes from the reviews table',
     shown.every(t => real.includes(t)), shown[0].slice(0, 38) + '…');
  ck('highest ratings lead the row',
     cards[0].querySelectorAll('.bi-star-fill').length === 5);

  /* the two rows must not carry the same review row.
     Comment text repeats across the seed, so this compares review_id. */
  const idsA = new Set([...A.querySelectorAll(':scope > span:first-child .review-card')]
    .map(c => c.dataset.review));
  const idsB = [...B.querySelectorAll(':scope > span:first-child .review-card')]
    .map(c => c.dataset.review);
  ck('no review row appears in both tracks', idsB.every(id => !idsA.has(id)),
     idsA.size + ' + ' + idsB.length + ' distinct rows');
  ck('every card is tied to a real review row',
     [...doc.querySelectorAll('.review-card')].every(c =>
       G(win, 'REVIEWS').some(r => String(r.review_id) === c.dataset.review)));
  ck('slider pauses on hover, by CSS',
     /\.review-rail:hover\s+\.review-track\s*\{\s*animation-play-state:\s*paused/.test(css.replace(/\n/g, ' ')));
  ck('reduced motion stops the drift and allows manual scroll',
     /prefers-reduced-motion[\s\S]*\.review-track\s*\{\s*animation:\s*none/.test(css)
     && /\.review-rail\s*\{\s*overflow-x:\s*auto/.test(css.replace(/\n/g, ' ')));
}

console.log('\n--- hero swap card balance');
{
  const { doc } = load('index.html');
  const hero = doc.querySelector('#heroMatch .swap');
  ck('hero trade uses the even variant', hero.classList.contains('even'));
  const labels = [...hero.querySelectorAll('.small-label')].map(l => l.textContent.trim());
  ck('labels are short verbs, not names', labels.every(l => l.split(' ').length <= 2),
     labels.join(' / '));
  ck('names moved to the meta line',
     [...hero.querySelectorAll('.swap-meta')].every(m => /·/.test(m.textContent)));
  ck('the badge is padded clear of the text',
     /\.swap-side\.give\s*\{\s*padding-right:\s*1\.85rem/.test(css)
     && /\.swap-side\.take\s*\{\s*padding-left:\s*1\.85rem/.test(css));
  ck('long skill names wrap instead of hiding',
     /\.swap-skill\s*\{[^}]*text-wrap:\s*balance/.test(css));
}

console.log('\n--- 8. hover peek on search results');
{
  const { doc, win } = load('search.html');
  const cards = doc.querySelectorAll('#results article');
  ck('cards marked peekable', cards.length > 0
     && [...cards].every(c => c.classList.contains('peekable')), cards.length + ' cards');
  const peeks = doc.querySelectorAll('#results .peek-body');
  ck('every card carries a peek panel', peeks.length === cards.length);
  ck('peek is collapsed until hover, by CSS not JS',
     /\.peek\s*\{[^}]*grid-template-rows:\s*0fr/.test(css)
     && /\.peekable:hover\s+\.peek[^{]*\{\s*grid-template-rows:\s*1fr/.test(css.replace(/\n/g, ' ')));
  ck('keyboard users get it too', /\.peekable:focus-within\s+\.peek/.test(css));

  /* the numbers in the peek must match the data */
  const first = cards[0];
  const name = first.querySelector('a[href^="profile.html"]').textContent.trim();
  const u = G(win, 'USERS').find(x => x.name === name);
  const exch = G(win, 'REQUESTS').filter(r => r.sender_id === u.user_id
                                           || r.receiver_id === u.user_id).length;
  const shown = [...first.querySelectorAll('.peek-body .fw-semibold')].map(e => e.textContent.trim());
  ck('exchange count in the peek is the real one',
     shown[0] === String(exch), name + ' has ' + exch);
  ck('department shown matches the row', shown[3] === u.department, u.department);
}

console.log('\n--- 16. attention pulse');
{
  const { doc, win } = load('dashboard.html');
  const pend = G(win, 'REQUESTS')
    .filter(r => r.receiver_id === 1 && r.status === 'Pending').length;
  const dot = doc.querySelector('#shellNav .badge-dot');
  if (pend) {
    ck('badge shown while a request waits', !!dot, pend + ' pending');
    ck('and it pulses', dot.classList.contains('pulse'));
    ck('sidebar count pulses too',
       !!doc.querySelector('#shellRail .nav-link .count.pulse'));
  } else {
    ck('no badge when nothing is waiting', !dot);
  }

  /* once the queue is cleared the pulse must stop */
  const btn = [...doc.querySelectorAll('#pendingList button')]
    .find(b => b.textContent.trim().startsWith('Accept'));
  if (btn) {
    const id = Number(btn.getAttribute('onclick').match(/\d+/)[0]);
    win.actRequestStatus(id, 'Accepted');
    const still = G(win, 'REQUESTS')
      .filter(r => r.receiver_id === 1 && r.status === 'Pending').length;
    const d2 = doc.querySelector('#shellNav .badge-dot');
    ck('pulse clears when the queue empties', still ? !!d2 : !d2,
       still + ' left waiting');
  }

  const rm = load('index.html', { reduced: true });
  ck('reduced motion switches the animation off',
     /prefers-reduced-motion[^}]*\}[\s\S]*\.pulse\s*\{\s*animation:\s*none/.test(css.replace(/\n/g, ' '))
     || /\.pulse\s*\{\s*animation:\s*none/.test(css.replace(/\n/g, ' ')));
  rm.dom.window.close();
}

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
});
