/* =============================================================
   SkillSwap NSU  —  ui.js
   -------------------------------------------------------------
   Shared navigation shell + data lookup helpers.

   Everything here reads from the arrays in data.js. In the final
   Flask build the lookups below are replaced by raw SQL queries
   (the comment above each helper names the query it stands in for)
   and the markup moves into Jinja templates unchanged.
   ============================================================= */

'use strict';

/* ---------------------------------------------------------------
   Tiny utilities
   --------------------------------------------------------------- */
const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

function esc(str) {
  return String(str ?? '').replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

function param(name, fallback = null) {
  const v = new URLSearchParams(location.search).get(name);
  return v === null || v === '' ? fallback : v;
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/* '2026-07-25' -> '25 Jul 2026' */
function fmtDate(iso) {
  if (!iso) return '';
  const [y, m, d] = iso.slice(0, 10).split('-');
  return `${Number(d)} ${MONTHS[Number(m) - 1]} ${y}`;
}

/* '15:30' -> '3:30 PM' */
function fmtTime(t) {
  if (!t) return '';
  let [h, m] = t.split(':').map(Number);
  const ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${h}:${String(m).padStart(2, '0')} ${ap}`;
}

const TODAY = '2026-07-28';

/* ---------------------------------------------------------------
   Lookups
   --------------------------------------------------------------- */

/* SQL: SELECT * FROM users WHERE user_id = %s */
const userById  = id => USERS.find(u => u.user_id === Number(id));

/* SQL: SELECT * FROM skills WHERE skill_id = %s */
const skillById = id => SKILLS.find(s => s.skill_id === Number(id));

/* ---------------------------------------------------------------
   Who is signed in.
   The static build keeps the id in sessionStorage; Flask keeps the
   same value in the server-side session under session['user_id'].
   --------------------------------------------------------------- */
const SESSION_KEY = 'skillswap.user';
let CURRENT_ID = DEMO_USER_ID;
try { CURRENT_ID = Number(sessionStorage.getItem(SESSION_KEY)) || DEMO_USER_ID; } catch (e) {}

const meId = () => CURRENT_ID;

function signIn(userId) {
  CURRENT_ID = Number(userId);
  try { sessionStorage.setItem(SESSION_KEY, String(CURRENT_ID)); } catch (e) {}
}

function signOut() {
  CURRENT_ID = DEMO_USER_ID;
  try { sessionStorage.removeItem(SESSION_KEY); } catch (e) {}
}

const me = () => userById(CURRENT_ID);

/* SQL: SELECT s.*, us.proficiency FROM userskills us
        JOIN skills s USING(skill_id)
        WHERE us.user_id = %s AND us.skill_type = %s          */
function skillsOf(userId, type) {
  return USERSKILLS
    .filter(us => us.user_id === Number(userId) && us.skill_type === type)
    .map(us => ({ ...skillById(us.skill_id), proficiency: us.proficiency }))
    .sort((a, b) => a.skill_name.localeCompare(b.skill_name));
}

/* SQL: SELECT COUNT(*), AVG(rating) FROM reviews WHERE reviewee_id = %s */
function ratingOf(userId) {
  const rows = REVIEWS.filter(r => r.reviewee_id === Number(userId));
  if (!rows.length) return { count: 0, avg: null };
  const avg = rows.reduce((t, r) => t + r.rating, 0) / rows.length;
  return { count: rows.length, avg: Math.round(avg * 100) / 100 };
}

/* SQL: SELECT * FROM exchangerequests WHERE sender_id = %s OR receiver_id = %s */
function requestsOf(userId) {
  const id = Number(userId);
  return {
    sent:     REQUESTS.filter(r => r.sender_id === id),
    received: REQUESTS.filter(r => r.receiver_id === id)
  };
}

/* SQL: SELECT se.* FROM sessions se JOIN exchangerequests er USING(request_id)
        WHERE er.sender_id = %s OR er.receiver_id = %s                        */
function sessionsOf(userId) {
  const id = Number(userId);
  return SESSIONS.filter(se => {
    const req = REQUESTS.find(r => r.request_id === se.request_id);
    return req && (req.sender_id === id || req.receiver_id === id);
  });
}

/* The other person in a session, as seen from `userId` */
function partnerOf(session, userId) {
  const req = REQUESTS.find(r => r.request_id === session.request_id);
  if (!req) return null;
  return userById(req.sender_id === Number(userId) ? req.receiver_id : req.sender_id);
}

function requestOfSession(session) {
  return REQUESTS.find(r => r.request_id === session.request_id);
}

/* ---------------------------------------------------------------
   Render helpers
   --------------------------------------------------------------- */
function initials(name) {
  const p = String(name || '?').trim().split(/\s+/);
  return ((p[0]?.[0] || '') + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
}

/* Deterministic avatar tint so each student keeps the same colour.
   Eight steps along the ember ground, dark enough that light initials
   stay readable and quiet enough that the accent stays the only thing
   that shouts. */
const AV_TINTS = ['#2E1A16', '#38201B', '#291512', '#43271F',
                  '#22110F', '#4E2E25', '#331C18', '#472A22'];

function avatar(user, size = 40) {
  if (!user) return '';
  const tint = AV_TINTS[user.user_id % AV_TINTS.length];
  return `<span class="avatar avatar-${size}" style="background:${tint}"
                title="${esc(user.name)}" aria-hidden="true">${initials(user.name)}</span>`;
}

const PILL_ICON = {
  Pending: 'hourglass-split', Accepted: 'check-circle', Completed: 'patch-check',
  Rejected: 'x-circle', Cancelled: 'slash-circle', Scheduled: 'calendar-check'
};

function pill(status) {
  const key = String(status).toLowerCase();
  return `<span class="pill pill-${key}"><i class="bi bi-${PILL_ICON[status] || 'circle'}"></i>${esc(status)}</span>`;
}

function modePill(mode) {
  const icon = mode === 'Online' ? 'camera-video' : 'geo-alt';
  return `<span class="pill pill-${mode.toLowerCase()}"><i class="bi bi-${icon}"></i>${mode}</span>`;
}

function stars(rating) {
  if (rating === null || rating === undefined) return '<span class="text-muted-2 small">No rating yet</span>';
  const full = Math.floor(rating);
  const half = rating - full >= 0.5;
  let out = '';
  for (let i = 1; i <= 5; i++) {
    if (i <= full)            out += '<i class="bi bi-star-fill"></i>';
    else if (i === full + 1 && half) out += '<i class="bi bi-star-half"></i>';
    else                      out += '<i class="bi bi-star"></i>';
  }
  return `<span class="stars">${out}</span>`;
}

function skillChip(skill, kind) {
  const cls = kind === 'Teach' ? 'chip-teach' : 'chip-learn';
  const lvl = skill.proficiency ? `<span class="lvl">${esc(skill.proficiency)}</span>` : '';
  return `<a class="chip ${cls}" href="search.html?skill=${encodeURIComponent(skill.skill_name)}">
            ${esc(skill.skill_name)} ${lvl}</a>`;
}

/* THE SIGNATURE COMPONENT — one skill trade, both directions.
   Pass even = true for a trade between two other people, where neither
   half belongs to the reader and the two sides carry equal weight. */
function swapCard(giveLabel, giveSkill, giveMeta, takeLabel, takeSkill, takeMeta, even) {
  return `
  <div class="swap${even ? ' even' : ''}">
    <div class="swap-side give">
      <div class="small-label">${esc(giveLabel)}</div>
      <div class="swap-skill">${esc(giveSkill)}</div>
      <div class="swap-meta">${giveMeta || ''}</div>
    </div>
    <div class="swap-badge" aria-hidden="true"><i class="bi bi-arrow-left-right"></i></div>
    <div class="swap-side take">
      <div class="small-label">${esc(takeLabel)}</div>
      <div class="swap-skill">${esc(takeSkill)}</div>
      <div class="swap-meta">${takeMeta || ''}</div>
    </div>
  </div>`;
}

function empty(icon, text, ctaHtml = '') {
  return `<div class="empty"><i class="bi bi-${icon}"></i><p>${text}</p>${ctaHtml}</div>`;
}

/* ---------------------------------------------------------------
   Demo-only feedback.
   The static build has no server, so write actions explain
   themselves instead of pretending to work.
   --------------------------------------------------------------- */
function demoAction(what) {
  const host = $('#toastHost') || (() => {
    const d = document.createElement('div');
    d.id = 'toastHost';
    d.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    d.style.zIndex = 1080;
    document.body.appendChild(d);
    return d;
  })();
  const el = document.createElement('div');
  el.className = 'toast align-items-center text-bg-dark border-0 show';
  el.innerHTML = `<div class="d-flex">
      <div class="toast-body"><i class="bi bi-info-circle me-1"></i>${esc(what)}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"
              data-bs-dismiss="toast" aria-label="Close"></button></div>`;
  host.appendChild(el);
  setTimeout(() => el.remove(), 5200);
}

/* ---------------------------------------------------------------
   Demo store
   ---------------------------------------------------------------
   The static build has no server, so every write action is recorded
   as a small operation and replayed against the in-memory arrays on
   each page load. sessionStorage keeps the changes alive while the
   user moves between pages; closing the tab clears them.

   In the Flask build `commit()` is replaced by a form POST and the
   operations below become the parameterised SQL shown in each toast.
   --------------------------------------------------------------- */
const STORE_KEY = 'skillswap.demo.ops';

function loadOps() {
  try { return JSON.parse(sessionStorage.getItem(STORE_KEY)) || []; }
  catch (e) { return []; }
}
function saveOps() {
  try { sessionStorage.setItem(STORE_KEY, JSON.stringify(OPS)); } catch (e) {}
}
let OPS = loadOps();

const nextId = (rows, key) =>
  rows.reduce((m, r) => Math.max(m, r[key]), 0) + 1;

function mutate(op) {
  const o = op.row;
  switch (op.t) {
    case 'reqStatus': {
      const r = REQUESTS.find(x => x.request_id === op.id);
      if (r) r.status = op.v;
      break;
    }
    case 'newRequest':  REQUESTS.push(o); break;
    case 'newSession':  SESSIONS.push(o); break;
    case 'sessStatus': {
      const s = SESSIONS.find(x => x.session_id === op.id);
      if (s) s.status = op.v;
      break;
    }
    case 'sessEdit': {
      const s = SESSIONS.find(x => x.session_id === op.id);
      if (s) Object.assign(s, op.v);
      break;
    }
    case 'newReview':   REVIEWS.push(o); break;
    case 'delReview': {
      const i = REVIEWS.findIndex(x => x.review_id === op.id);
      if (i > -1) REVIEWS.splice(i, 1);
      break;
    }
    case 'newUserSkill': USERSKILLS.push(o); break;
    case 'delUserSkill': {
      const i = USERSKILLS.findIndex(x => x.user_skill_id === op.id);
      if (i > -1) USERSKILLS.splice(i, 1);
      break;
    }
    case 'skillLevel': {
      const r = USERSKILLS.find(x => x.user_skill_id === op.id);
      if (r) r.proficiency = op.v;
      break;
    }
    case 'editUser': {
      const u = userById(op.id);
      if (u) Object.assign(u, op.v);
      break;
    }
    case 'newUser':  USERS.push(o); break;
    case 'newSkill': SKILLS.push(o); break;
    case 'delSkill': {
      const i = SKILLS.findIndex(x => x.skill_id === op.id);
      if (i > -1) SKILLS.splice(i, 1);
      break;
    }
    case 'delUser': {
      /* mirrors ON DELETE CASCADE in the schema */
      const id = op.id;
      const gone = REQUESTS.filter(r => r.sender_id === id || r.receiver_id === id)
                           .map(r => r.request_id);
      const goneSess = SESSIONS.filter(s => gone.includes(s.request_id))
                               .map(s => s.session_id);
      [[REVIEWS, r => goneSess.includes(r.session_id) ||
                      r.reviewer_id === id || r.reviewee_id === id],
       [SESSIONS, s => goneSess.includes(s.session_id)],
       [REQUESTS, r => gone.includes(r.request_id)],
       [USERSKILLS, s => s.user_id === id],
       [USERS, u => u.user_id === id]
      ].forEach(([arr, pred]) => {
        for (let i = arr.length - 1; i >= 0; i--) if (pred(arr[i])) arr.splice(i, 1);
      });
      break;
    }
  }
}

/* Record an operation, apply it, tell the page to redraw. */
function commit(op, message) {
  OPS.push(op);
  saveOps();
  mutate(op);
  if (message) demoAction(message);
  document.dispatchEvent(new CustomEvent('data-changed'));
}

function resetDemo() {
  OPS = [];
  saveOps();
  location.reload();
}

OPS.forEach(mutate);   /* replay everything recorded so far */

/* ---------------------------------------------------------------
   Actions shared by several pages.
   Each one names the SQL statement it stands for, so the toast
   doubles as documentation during the in-class review.
   --------------------------------------------------------------- */
function actRequestStatus(id, status) {
  commit({ t: 'reqStatus', id: Number(id), v: status },
         `Request #${id} is now ${status}. `
       + `SQL: UPDATE exchangerequests SET status = '${status}' WHERE request_id = ${id}`);
}

function actSendRequest(receiverId, teachSkillId, learnSkillId) {
  const id = nextId(REQUESTS, 'request_id');
  const row = {
    request_id: id, sender_id: meId(), receiver_id: Number(receiverId),
    teach_skill: Number(teachSkillId), learn_skill: Number(learnSkillId),
    status: 'Pending', created: TODAY + ' 10:00'
  };
  commit({ t: 'newRequest', row },
         `Request sent to ${userById(receiverId).name}. `
       + `SQL: INSERT INTO exchangerequests (sender_id, receiver_id, teach_skill, `
       + `learn_skill, status) VALUES (${meId()}, ${receiverId}, `
       + `${teachSkillId}, ${learnSkillId}, 'Pending')`);
  return id;
}

function actBookSession(fields) {
  const id = nextId(SESSIONS, 'session_id');
  const row = Object.assign({ session_id: id, status: 'Scheduled',
                              location: null, meeting_link: null }, fields);
  commit({ t: 'newSession', row },
         `Session booked for ${fmtDate(row.session_date)} at ${fmtTime(row.session_time)}. `
       + `SQL: INSERT INTO sessions (request_id, session_date, session_time, `
       + `duration, mode, ${row.mode === 'Online' ? 'meeting_link' : 'location'}) VALUES (...)`);
  return id;
}

function actEditSession(id, fields) {
  commit({ t: 'sessEdit', id: Number(id), v: fields },
         `Session #${id} moved to ${fmtDate(fields.session_date)} at ${fmtTime(fields.session_time)}. `
       + `SQL: UPDATE sessions SET session_date = %s, session_time = %s WHERE session_id = ${id}`);
}

function actSessionStatus(id, status) {
  commit({ t: 'sessStatus', id: Number(id), v: status },
         `Session #${id} is now ${status}. `
       + `SQL: UPDATE sessions SET status = '${status}' WHERE session_id = ${id}`);

  /* completing the last open session finishes the exchange too */
  if (status === 'Completed') {
    const s = SESSIONS.find(x => x.session_id === Number(id));
    const open = SESSIONS.filter(x => x.request_id === s.request_id
                                   && x.status === 'Scheduled').length;
    if (!open) {
      commit({ t: 'reqStatus', id: s.request_id, v: 'Completed' }, '');
    }
  }
}

function actAddReview(sessionId, revieweeId, rating, comment) {
  const id = nextId(REVIEWS, 'review_id');
  const row = { review_id: id, session_id: Number(sessionId),
                reviewer_id: meId(), reviewee_id: Number(revieweeId),
                rating: Number(rating), comment, created: TODAY };
  commit({ t: 'newReview', row },
         `Review published. `
       + `SQL: INSERT INTO reviews (session_id, reviewer_id, reviewee_id, rating, `
       + `comment) VALUES (${sessionId}, ${meId()}, ${revieweeId}, ${rating}, %s)`);
}

function actDeleteReview(id) {
  commit({ t: 'delReview', id: Number(id) },
         `Review removed. SQL: DELETE FROM reviews WHERE review_id = ${id}`);
}

/* ---------------------------------------------------------------
   Navigation shell
   Injected into <div id="shellNav"></div> on every signed-in page.
   In Flask this whole block lives in templates/base.html.
   --------------------------------------------------------------- */
const NAV = [
  { key: 'dashboard', href: 'dashboard.html', icon: 'house-door',    label: 'Home' },
  { key: 'search',    href: 'search.html',    icon: 'search',        label: 'Find' },
  { key: 'requests',  href: 'requests.html',  icon: 'arrow-left-right', label: 'Requests' },
  { key: 'sessions',  href: 'sessions.html',  icon: 'calendar-event', label: 'Sessions' },
  { key: 'reviews',   href: 'reviews.html',   icon: 'star',          label: 'Reviews' }
];

function pendingCount() {
  return requestsOf(meId()).received.filter(r => r.status === 'Pending').length;
}

function mountShell(active) {
  const u = me();
  const pend = pendingCount();

  const links = NAV.map(n => {
    /* 16. the badge breathes only while something is genuinely waiting */
    const dot = (n.key === 'requests' && pend)
      ? `<span class="badge-dot pulse">${pend}</span>` : '';
    return `<a class="topnav-link position-relative ${n.key === active ? 'active' : ''}"
               href="${n.href}"><i class="bi bi-${n.icon}"></i>${dot}<span>${n.label}</span></a>`;
  }).join('');

  $('#shellNav').innerHTML = `
  <div class="demo-note">
    <div class="container d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <span><i class="bi bi-display me-1"></i>Static preview &mdash; signed in as
        <strong>${esc(u.name)}</strong>. Buttons work, but changes live in this
        browser tab only until the Flask build is connected.</span>
      <span class="d-flex gap-3 align-items-center">
        ${OPS.length ? `<span class="badge text-bg-secondary">${OPS.length} unsaved change${OPS.length === 1 ? '' : 's'}</span>` : ''}
        <a href="#" onclick="event.preventDefault();resetDemo()"
           class="text-decoration-underline">Reset demo data</a>
      </span>
    </div>
  </div>

  <nav class="topbar">
    <div class="container d-flex align-items-center gap-3 py-1">
      <a class="brand-mark" href="dashboard.html">
        <span class="brand-glyph"><i class="bi bi-arrow-left-right"></i></span>
        SkillSwap <span class="brand-tag">NSU</span>
      </a>

      <form class="nav-search flex-grow-1 d-none d-md-block" role="search"
            onsubmit="event.preventDefault(); goSearch(this.elements.q.value);">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-transparent border-0 pe-1"
                style="background:var(--brand-050)!important"><i class="bi bi-search text-muted-2"></i></span>
          <input class="form-control border-0" name="q" type="search"
                 placeholder="Search students, skills or departments" aria-label="Search">
        </div>
      </form>

      <div class="d-flex align-items-center ms-auto">
        ${links}
        <div class="dropdown ms-2">
          <a class="topnav-link" href="#" data-bs-toggle="dropdown" aria-expanded="false">
            ${avatar(u, 24)}<span>Me <i class="bi bi-caret-down-fill" style="font-size:.55rem"></i></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:230px">
            <li class="px-3 py-2 d-flex gap-2 align-items-center">
              ${avatar(u, 40)}
              <div class="lh-sm">
                <div class="fw-semibold">${esc(u.name)}</div>
                <div class="small text-muted-2">${esc(u.department)}</div>
              </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="profile.html?id=${u.user_id}">
                  <i class="bi bi-person me-2"></i>View profile</a></li>
            <li><a class="dropdown-item" href="edit-profile.html">
                  <i class="bi bi-pencil-square me-2"></i>Edit profile &amp; skills</a></li>
            <li><a class="dropdown-item" href="admin.html">
                  <i class="bi bi-shield-lock me-2"></i>Admin console</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="login.html"
                   onclick="signOut()">
                  <i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>`;
}

function goSearch(q) {
  location.href = 'search.html?q=' + encodeURIComponent(q || '');
}

/* Left rail: mini profile card + section navigation */
function profileRail(active) {
  const u = me();
  const r = ratingOf(u.user_id);
  const teach = skillsOf(u.user_id, 'Teach').length;
  const learn = skillsOf(u.user_id, 'Learn').length;
  const req = requestsOf(u.user_id);
  const upcoming = sessionsOf(u.user_id).filter(s => s.status === 'Scheduled').length;

  const items = [
    ['dashboard', 'dashboard.html', 'house-door', 'Home', ''],
    ['search', 'search.html', 'search', 'Find a partner', ''],
    ['requests', 'requests.html', 'arrow-left-right', 'Requests',
      req.received.filter(x => x.status === 'Pending').length || ''],
    ['sessions', 'sessions.html', 'calendar-event', 'Sessions', upcoming || ''],
    ['reviews', 'reviews.html', 'star', 'Reviews', r.count || ''],
    ['profile', `profile.html?id=${u.user_id}`, 'person', 'My profile', ''],
    ['edit', 'edit-profile.html', 'pencil-square', 'Edit profile', ''],
    ['admin', 'admin.html', 'shield-lock', 'Admin console', '']
  ].map(([k, href, icon, label, count]) => `
      <a class="nav-link ${k === active ? 'active' : ''}" href="${href}">
        <i class="bi bi-${icon}"></i><span>${label}</span>
        ${count ? `<span class="count${k === 'requests' ? ' pulse' : ''}">${count}</span>` : ''}
      </a>`).join('');

  return `
  <div class="panel overflow-hidden">
    <div class="profile-card-cover"></div>
    <div class="profile-card-body">
      ${avatar(u, 88)}
      <h3 class="mt-2 mb-0" style="font-size:1.02rem">${esc(u.name)}</h3>
      <div class="small text-muted-2">${esc(u.department)} &middot; NSU</div>
      <div class="mt-2 d-flex justify-content-center align-items-center gap-1">
        ${stars(r.avg)} <span class="small text-muted-2">${r.count ? r.avg + ' (' + r.count + ')' : ''}</span>
      </div>
      <div class="d-flex justify-content-center gap-3 mt-3 pt-3 border-top">
        <div><div class="fw-bold" style="color:var(--brand-700)">${teach}</div>
             <div class="small text-muted-2" style="font-size:11.5px">Teaching</div></div>
        <div><div class="fw-bold" style="color:var(--brand-700)">${learn}</div>
             <div class="small text-muted-2" style="font-size:11.5px">Learning</div></div>
        <div><div class="fw-bold" style="color:var(--brand-700)">${req.sent.length + req.received.length}</div>
             <div class="small text-muted-2" style="font-size:11.5px">Exchanges</div></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body tight">
      <nav class="side-nav d-grid gap-1">${items}</nav>
    </div>
  </div>`;
}

/* Call once per page: mountShell + left rail + footer year */
function bootPage(active, railActive = active) {
  if ($('#shellNav')) mountShell(active);
  if ($('#shellRail')) $('#shellRail').innerHTML = profileRail(railActive);
  $$('[data-year]').forEach(el => el.textContent = new Date().getFullYear());
}
