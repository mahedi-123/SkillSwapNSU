/* =============================================================
   SkillSwap NSU  —  app.js  (PHP build)
   -------------------------------------------------------------
   In this build the browser does no data work at all: every row
   on screen was selected by MySQL and rendered by PHP before the
   page was sent. What is left here is presentation only —
   revealing sections on scroll, the password eye, a marquee, and
   the small conveniences a form needs.

   Everything below switches itself off under
   prefers-reduced-motion.
   ============================================================= */

'use strict';

const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

const STILL = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------------- sign-in conveniences ---------------- */

const fill = $('#fillDemo');
if (fill) {
  fill.addEventListener('click', () => {
    const email = $('#email'), pw = $('#password');
    if (email) email.value = fill.dataset.email || '';
    if (pw)    pw.value    = fill.dataset.pw || '';
    if (pw)    pw.focus();
  });
}

$$('#togglePw, [data-toggle-password]').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.closest('.input-group')?.querySelector('input');
    if (!input) return;
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    const icon = btn.querySelector('i');
    if (icon) icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
  });
});

/* ---------------- toasts ---------------- */

$$('.toast.show').forEach(t => {
  setTimeout(() => t.classList.remove('show'), 6500);
});

/* ---------------- reveal on scroll ---------------- */

if (!STILL && 'IntersectionObserver' in window) {
  const seen = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('in');
      obs.unobserve(entry.target);
    });
  }, { threshold: 0.15 });

  $$('.reveal').forEach(el => seen.observe(el));
} else {
  $$('.reveal').forEach(el => el.classList.add('in'));
}

/* ---------------- counters ---------------- */

if (!STILL && 'IntersectionObserver' in window) {
  const counters = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const to = Number(el.dataset.count || el.textContent) || 0;
      const started = performance.now();
      const step = now => {
        const p = Math.min(1, (now - started) / 900);
        el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
      obs.unobserve(el);
    });
  }, { threshold: 0.5 });

  $$('[data-count]').forEach(el => counters.observe(el));
}

/* ---------------- scroll progress hairline ---------------- */

const bar = $('#scrollBar');
if (bar && !STILL) {
  const paint = () => {
    const h = document.documentElement.scrollHeight - window.innerHeight;
    bar.style.width = (h > 0 ? (window.scrollY / h) * 100 : 0) + '%';
  };
  window.addEventListener('scroll', paint, { passive: true });
  paint();
}

/* ---------------- category menu ---------------- */

const catBtn = $('#catToggle'), catMenu = $('#catMenu');
if (catBtn && catMenu) {
  catBtn.addEventListener('click', e => {
    e.stopPropagation();
    catMenu.classList.toggle('open');
  });
  document.addEventListener('click', e => {
    if (!catMenu.contains(e.target)) catMenu.classList.remove('open');
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') catMenu.classList.remove('open');
  });
}

/* ---------------- the dice in the ask box ---------------- */

const dice = $('#dice');
if (dice) {
  const pool = JSON.parse(dice.dataset.skills || '[]');
  const box  = $('#askInput');
  dice.addEventListener('click', () => {
    if (!pool.length || !box) return;
    box.value = pool[Math.floor(Math.random() * pool.length)];
    box.focus();
  });
}

/* ---------------- booking form: online vs offline ---------------- */

$$('[data-mode-switch]').forEach(sel => {
  const sync = () => {
    const online = sel.value === 'Online';
    const field  = sel.closest('form')?.querySelector('[data-where]');
    const label  = sel.closest('form')?.querySelector('[data-where-label]');
    if (!field) return;
    field.placeholder = online ? 'https://meet.example.com/abc-defg' : 'Library, 3rd floor';
    if (label) label.textContent = online ? 'Meeting link' : 'Location';
  };
  sel.addEventListener('change', sync);
  sync();
});

/* ---------------- star picker ---------------- */

$$('[data-star-picker]').forEach(picker => {
  const input = picker.querySelector('input[type=hidden]');
  const stars = $$('button', picker);
  const paint = value => stars.forEach((s, i) => {
    s.firstElementChild.className = 'bi bi-star' + (i < value ? '-fill' : '');
  });
  stars.forEach((s, i) => {
    s.addEventListener('click', () => { input.value = i + 1; paint(i + 1); });
    s.addEventListener('mouseenter', () => paint(i + 1));
  });
  picker.addEventListener('mouseleave', () => paint(Number(input.value) || 0));
  paint(Number(input.value) || 0);
});

/* ---------------- footer year ---------------- */

$$('[data-year]').forEach(el => { el.textContent = new Date().getFullYear(); });
