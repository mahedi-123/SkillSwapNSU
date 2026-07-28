/* Render each real page with its real scripts, capture the resulting markup,
   and stitch it all into one self-contained preview.html.

   Because the markup is captured from the pages themselves and the CSS is the
   project's own file, the preview cannot drift from the real interface. */
const fs = require('fs');
const path = require('path');
const { JSDOM, VirtualConsole } = require('jsdom');

const ROOT = path.resolve(__dirname, '..');
const css    = fs.readFileSync(path.join(ROOT, 'static/css/style.css'), 'utf8');
const dataJs = fs.readFileSync(path.join(ROOT, 'static/js/data.js'), 'utf8');
const uiJs   = fs.readFileSync(path.join(ROOT, 'static/js/ui.js'), 'utf8');

const BOOTSTRAP_STUB = `window.bootstrap={
  Modal:class{constructor(e){this.e=e}show(){}hide(){}static getInstance(){return{hide(){}}}},
  Toast:class{show(){}hide(){}}};
window.matchMedia = window.matchMedia || (q => ({ matches:/hover: hover/.test(q),
  media:q, addListener(){}, removeListener(){}, addEventListener(){}, removeEventListener(){} }));`;

const SCREENS = [
  ['home',     'index.html',        '',           'Home'],
  ['dash',     'dashboard.html',    '',           'Dashboard'],
  ['find',     'search.html',       '',           'Find a partner'],
  ['profile',  'profile.html',      '?id=8',      'Profile'],
  ['requests', 'requests.html',     '',           'Requests'],
  ['sessions', 'sessions.html',     '',           'Sessions'],
  ['reviews',  'reviews.html',      '',           'Reviews'],
  ['edit',     'edit-profile.html', '',           'Edit profile'],
  ['admin',    'admin.html',        '',           'Admin console'],
  ['login',    'login.html',        '',           'Sign in'],
  ['register', 'register.html',     '',           'Register']
];

function render(file, query) {
  let html = fs.readFileSync(path.join(ROOT, file), 'utf8')
    .replace(/<script src="https:\/\/cdn[^"]*"><\/script>/g, () => '<script>' + BOOTSTRAP_STUB + '</script>')
    .replace(/<script src="static\/js\/data\.js"><\/script>/, () => '<script>' + dataJs + '</script>')
    .replace(/<script src="static\/js\/ui\.js"><\/script>/, () => '<script>' + uiJs + '</script>');

  const vc = new VirtualConsole();
  const errs = [];
  vc.on('jsdomError', e => errs.push(e.message));

  const dom = new JSDOM(html, {
    runScripts: 'dangerously', virtualConsole: vc, pretendToBeVisual: true,
    url: 'http://localhost/' + file + query
  });

  const doc = dom.window.document;
  /* strip the scripts and the CDN links — the preview supplies its own */
  doc.querySelectorAll('script').forEach(n => n.remove());
  doc.querySelectorAll('link[rel="stylesheet"]').forEach(n => n.remove());

  const body = doc.body.innerHTML;
  const bodyClass = doc.body.className || '';
  dom.window.close();
  if (errs.length) console.log('   ! ' + file + ': ' + errs[0]);
  return { body, bodyClass };
}

/* the catalogue, for the hero dice */
const D = eval(dataJs + '; ({ USERS, SKILLS, USERSKILLS })');
const SKILL_NAMES = D.SKILLS.map(s => s.skill_name);
console.log('  catalogue:', SKILL_NAMES.length, 'skills\n');

const captured = SCREENS.map(([key, file, query, label]) => {
  process.stdout.write('  capturing ' + file.padEnd(20));
  const r = render(file, query);
  console.log(String(r.body.length).padStart(7) + ' chars');
  return { key, label, file, ...r };
});

const nav = captured.map((c, i) =>
  `<button class="pv-tab${i === 0 ? ' on' : ''}" data-screen="${c.key}">${c.label}</button>`
).join('');

const panes = captured.map((c, i) => `
<section class="pv-pane${i === 0 ? ' on' : ''}" id="pv-${c.key}" data-file="${c.file}"
         data-bodyclass="${c.bodyClass}">
${c.body}
</section>`).join('\n');

const out = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillSwap NSU — design preview</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
${css}

/* ---- preview chrome, not part of the product ---- */
.pv-bar {
  position: sticky; top: 0; z-index: 2000;
  background: #060E0F;
  border-bottom: 1px solid var(--line);
  padding: .55rem .9rem;
  display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
}
.pv-title {
  font-family: var(--data); font-size: 10px; letter-spacing: .16em;
  text-transform: uppercase; color: var(--fg-3); margin-right: .4rem;
}
.pv-tab {
  font-family: var(--ui); font-size: 12px; font-weight: 500;
  color: var(--fg-2); background: transparent;
  border: 1px solid var(--line-strong); border-radius: 999px;
  padding: 3px 12px; cursor: pointer; transition: all .15s ease;
}
.pv-tab:hover { color: var(--fg-0); border-color: var(--mint-30); }
.pv-tab.on { background: var(--mint); border-color: var(--mint); color: var(--mint-ink); font-weight: 600; }
.pv-file {
  margin-left: auto; font-family: var(--data); font-size: 10.5px; color: var(--fg-3);
}
.pv-pane { display: none; }
.pv-pane.on { display: block; }
.pv-note {
  font-family: var(--data); font-size: 10.5px; color: var(--fg-3);
  text-align: center; padding: 1.4rem 1rem 2rem; letter-spacing: .06em;
}
</style>
</head>
<body>

<div class="scroll-progress" aria-hidden="true"><span id="pvBar"></span></div>
<div class="spotlight" id="pvSpot" aria-hidden="true"></div>

<div class="pv-bar">
  <span class="pv-title">SkillSwap NSU / preview</span>
  ${nav}
  <span class="pv-file" id="pvFile"></span>
</div>

${panes}

<p class="pv-note">
  Captured from the real pages. Open index.html for the working build.
</p>

<script>
(function () {
  var tabs  = document.querySelectorAll('.pv-tab');
  var panes = document.querySelectorAll('.pv-pane');
  var label = document.getElementById('pvFile');

  function show(key) {
    tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.screen === key); });
    panes.forEach(function (p) {
      var on = p.id === 'pv-' + key;
      p.classList.toggle('on', on);
      if (on) {
        label.textContent = p.dataset.file;
        document.body.className = p.dataset.bodyclass || '';
      }
    });
    try { window.scrollTo(0, 0); } catch (e) {}
    if (key === 'home') wireHome();
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () { show(t.dataset.screen); });
  });

  /* the captured markup carries no scripts, so the hero controls are
     rewired here from the same catalogue the pages were built from */
  var skills = ${JSON.stringify(SKILL_NAMES)};

  /* motion flags, same two questions the real page asks */
  var RM = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var HV = !window.matchMedia || window.matchMedia('(hover: hover)').matches;

  /* 11 + 9 live at document level, so they are set up once */
  (function () {
    var bar = document.getElementById('pvBar');
    if (bar && !RM) {
      var t = false;
      addEventListener('scroll', function () {
        if (t) return; t = true;
        requestAnimationFrame(function () {
          var h = document.documentElement.scrollHeight - innerHeight;
          bar.style.width = (h > 0 ? Math.min(100, scrollY / h * 100) : 0) + '%';
          t = false;
        });
      }, { passive: true });
    }
    var sp = document.getElementById('pvSpot');
    if (sp && !RM && HV) {
      var x = 0, y = 0, q = false;
      addEventListener('pointermove', function (e) {
        if (e.pointerType === 'touch') return;
        x = e.clientX; y = e.clientY; sp.classList.add('lit');
        if (q) return; q = true;
        requestAnimationFrame(function () {
          sp.style.setProperty('--sx', x + 'px');
          sp.style.setProperty('--sy', y + 'px'); q = false;
        });
      }, { passive: true });
    }
  })();

  /* 15. tilt, wired for whatever pane is on screen */
  function wireTilt(root) {
    if (RM || !HV) return;
    root.querySelectorAll('[data-tilt]').forEach(function (card) {
      if (card.dataset.tiltOn) return;
      card.dataset.tiltOn = '1';
      var q = false, rx = 0, ry = 0;
      function put() {
        card.style.setProperty('--rx', rx.toFixed(2) + 'deg');
        card.style.setProperty('--ry', ry.toFixed(2) + 'deg'); q = false;
      }
      card.addEventListener('pointermove', function (e) {
        if (e.pointerType === 'touch') return;
        var b = card.getBoundingClientRect(); if (!b.width) return;
        rx = -((e.clientY - b.top) / b.height - .5) * 7;
        ry = ((e.clientX - b.left) / b.width - .5) * 7;
        card.classList.add('active');
        if (!q) { q = true; requestAnimationFrame(put); }
      });
      card.addEventListener('pointerleave', function () {
        card.classList.remove('active'); rx = ry = 0; put();
      });
    });
  }

  /* 12. steps walk themselves */
  function wireSteps(root) {
    var steps = root.querySelectorAll('.step');
    if (!steps.length || steps[0].dataset.stepOn) return;
    steps[0].dataset.stepOn = '1';
    var i = 0, held = false;
    function cue(n) { steps.forEach(function (s, k) { s.classList.toggle('cued', k === n); }); }
    cue(0);
    if (RM) { steps.forEach(function (s) { s.classList.add('cued'); }); return; }
    steps.forEach(function (s, k) {
      s.addEventListener('pointerenter', function () { held = true; i = k; cue(k); });
      s.addEventListener('pointerleave', function () { held = false; });
    });
    setInterval(function () { if (!held) { i = (i + 1) % steps.length; cue(i); } }, 2600);
  }

  /* 13. the review slider needs no JS — the drift is a CSS animation and
     the pause is a :hover rule, so nothing is rewired here. */

  var wired = false;
  function wireHome() {
    if (wired) return;
    var input = document.querySelector('#pv-home #askInput');
    var dice  = document.querySelector('#pv-home #askDice');
    var form  = document.querySelector('#pv-home #askForm');
    var btn   = document.querySelector('#pv-home #megaBtn');
    var panel = document.querySelector('#pv-home #megaPanel');
    if (!input && !btn) return;
    wired = true;

    if (dice && input) {
      var last = -1;
      dice.addEventListener('click', function () {
        var i = last;
        while (i === last && skills.length > 1) i = Math.floor(Math.random() * skills.length);
        last = i;
        input.value = skills[i];
        input.focus();
        dice.classList.add('rolling');
        setTimeout(function () { dice.classList.remove('rolling'); }, 460);
      });
    }
    if (form) form.addEventListener('submit', function (e) { e.preventDefault(); });

    document.querySelectorAll('#pv-home .ask-suggest .chip').forEach(function (c) {
      c.addEventListener('click', function () { if (input) input.value = c.dataset.skill; });
    });

    var home = document.getElementById('pv-home');
    wireTilt(home); wireSteps(home);

    if (btn && panel) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var open = panel.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.addEventListener('click', function (e) {
        if (!e.target.closest('.megawrap')) {
          panel.classList.remove('open');
          btn.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          panel.classList.remove('open');
          btn.setAttribute('aria-expanded', 'false');
        }
      });
    }
  }

  /* accordions in the captured FAQ need their own tiny handler */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.accordion-button');
    if (!btn) return;
    var sel = btn.getAttribute('data-bs-target');
    var body = sel && document.querySelector(sel);
    if (!body) return;
    var open = body.classList.toggle('show');
    btn.classList.toggle('collapsed', !open);
  });

  show('home');
})();
</script>
</body>
</html>
`;

fs.writeFileSync(path.join(ROOT, 'preview.html'), out);
console.log('\npreview.html written —', (out.length / 1024).toFixed(0) + ' KB, ' +
            captured.length + ' screens');
