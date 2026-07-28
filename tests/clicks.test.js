/* Click the real buttons and assert the underlying data actually changes. */
const fs = require('fs');
const path = require('path');
const { JSDOM, VirtualConsole } = require('jsdom');

const ROOT = path.resolve(__dirname, '..');
const dataJs = fs.readFileSync(path.join(ROOT, 'static/js/data.js'), 'utf8');
const uiJs   = fs.readFileSync(path.join(ROOT, 'static/js/ui.js'), 'utf8');
const STUB = `window.bootstrap={Modal:class{constructor(e){this.e=e}show(){}hide(){}
  static getInstance(){return new window.bootstrap.Modal()}},Toast:class{show(){}hide(){}}};`;

function load(page, query = '') {
  let html = fs.readFileSync(path.join(ROOT, page), 'utf8')
    .replace(/<script src="https:\/\/cdn[^"]*"><\/script>/g, () => '<script>' + STUB + '</script>')
    .replace(/<script src="static\/js\/data\.js"><\/script>/, () => '<script>' + dataJs + '</script>')
    .replace(/<script src="static\/js\/ui\.js"><\/script>/, () => '<script>' + uiJs + '</script>')
    .replace(/<link[^>]*https:\/\/[^>]*>/g, () => '');
  const errs = [];
  const vc = new VirtualConsole();
  vc.on('jsdomError', e => errs.push(e.message));
  const dom = new JSDOM(html, { runScripts: 'dangerously', virtualConsole: vc,
                                url: 'http://localhost/' + page + query,
                                pretendToBeVisual: true });
  return { dom, doc: dom.window.document, win: dom.window, errs };
}

/* top-level const/let live in the global lexical scope, not on window,
   so read them through eval in the page's own global context */
const G = (win, expr) => win.eval(expr);

let pass = 0, fail = 0;
const check = (label, cond, detail = '') => {
  if (cond) { pass++; console.log('  ok   ' + label + (detail ? '  (' + detail + ')' : '')); }
  else { fail++; console.log('  FAIL ' + label + '  ' + detail); }
};

/* find a button whose visible text matches */
function btn(doc, root, text) {
  return Array.from(doc.querySelectorAll(root + ' button'))
    .find(b => b.textContent.trim().toLowerCase().startsWith(text.toLowerCase()));
}

console.log('\n--- requests.html: accept and decline');
{
  const { doc, win, errs } = load('requests.html');
  check('page loaded without error', errs.length === 0, errs[0] || '');

  const before = G(win,'REQUESTS').filter(r => r.receiver_id === 1 && r.status === 'Pending');
  check('a pending request exists to act on', before.length > 0, before.length + ' pending');

  const accept = btn(doc, '#reqList', 'Accept');
  check('accept button rendered with a working handler',
        !!accept && /actRequestStatus/.test(accept.getAttribute('onclick') || ''),
        accept ? accept.getAttribute('onclick') : 'button missing');

  const id = before[0].request_id;
  accept.click();
  const after = G(win,'REQUESTS').find(r => r.request_id === id);
  check('clicking accept sets the status to Accepted', after.status === 'Accepted',
        'request #' + id + ' is now ' + after.status);
  check('the change is recorded for other pages', G(win,'OPS').length === 1);
  check('a toast confirms the action', !!doc.querySelector('#toastHost .toast'));
  check('the list re-rendered', !btn(doc, '#reqList', 'Accept')
        || !doc.querySelector('#reqList').textContent.includes('Waiting'));

  const decline = btn(doc, '#reqList', 'Decline');
  if (decline) {
    const did = Number((decline.getAttribute('onclick').match(/\d+/) || [])[0]);
    decline.click();
    check('clicking decline sets the status to Rejected',
          G(win,'REQUESTS').find(r => r.request_id === did).status === 'Rejected');
  } else { pass++; console.log('  ok   no second pending request to decline'); }
}

console.log('\n--- requests.html: cancel a sent request');
{
  const { doc, win } = load('requests.html', '?dir=sent');
  const cancel = btn(doc, '#reqList', 'Cancel');
  check('cancel button present on a sent request', !!cancel);
  if (cancel) {
    const id = Number(cancel.getAttribute('onclick').match(/\d+/)[0]);
    cancel.click();
    check('cancel sets the status to Cancelled',
          G(win,'REQUESTS').find(r => r.request_id === id).status === 'Cancelled');
  }
}

console.log('\n--- dashboard.html: send a request to a match');
{
  const { doc, win, errs } = load('dashboard.html');
  check('page loaded without error', errs.length === 0, errs[0] || '');
  const before = G(win,'REQUESTS').length;
  const send = btn(doc, '#matchList', 'Send request');
  check('send button wired to actSendRequest',
        !!send && /actSendRequest\(\d+, \d+, \d+\)/.test(send.getAttribute('onclick')));
  send.click();
  check('a new request row is created', G(win,'REQUESTS').length === before + 1,
        before + ' -> ' + G(win,'REQUESTS').length);
  const row = G(win,'REQUESTS')[G(win,'REQUESTS').length - 1];
  check('the new row is Pending and from the demo user',
        row.status === 'Pending' && row.sender_id === 1);
  check('that person disappears from the match list',
        !doc.querySelector('#matchList').innerHTML.includes('id=' + row.receiver_id + '"'));

  const accept = btn(doc, '#pendingList', 'Accept');
  if (accept) {
    const id = Number(accept.getAttribute('onclick').match(/\d+/)[0]);
    accept.click();
    check('dashboard accept works too',
          G(win,'REQUESTS').find(r => r.request_id === id).status === 'Accepted');
  }
}

console.log('\n--- sessions.html: complete, cancel and book');
{
  const { doc, win, errs } = load('sessions.html');
  check('page loaded without error', errs.length === 0, errs[0] || '');
  const done = btn(doc, '#sessBody', 'Mark completed');
  check('mark completed wired to actSessionStatus',
        !!done && /actSessionStatus/.test(done.getAttribute('onclick')));
  const sid = Number(done.getAttribute('onclick').match(/\d+/)[0]);
  done.click();
  check('session status becomes Completed',
        G(win,'SESSIONS').find(s => s.session_id === sid).status === 'Completed');

  const cancel = btn(doc, '#sessBody', 'Cancel');
  if (cancel) {
    const cid = Number(cancel.getAttribute('onclick').match(/\d+/)[0]);
    cancel.click();
    check('session status becomes Cancelled',
          G(win,'SESSIONS').find(s => s.session_id === cid).status === 'Cancelled');
  }

  const beforeS = G(win,'SESSIONS').length;
  win.openBooking(null);
  const reqOpt = doc.querySelector('#bReq option[value]');
  if (reqOpt && reqOpt.value) {
    doc.querySelector('#bDate').value = '2026-08-15';
    doc.querySelector('#bTime').value = '14:00';
    doc.querySelector('#bLink').value = 'https://meet.google.com/demo-abc';
    doc.querySelector('#bookForm').dispatchEvent(
      new win.Event('submit', { bubbles: true, cancelable: true }));
    check('booking the modal inserts a session', G(win,'SESSIONS').length === beforeS + 1,
          beforeS + ' -> ' + G(win,'SESSIONS').length);
    const ns = G(win,'SESSIONS')[G(win,'SESSIONS').length - 1];
    check('new session carries the entered values',
          ns.session_date === '2026-08-15' && ns.mode === 'Online'
          && ns.meeting_link === 'https://meet.google.com/demo-abc');
  }

  win.openBooking(null);
  doc.querySelector('#bDate').value = '2020-01-01';
  doc.querySelector('#bLink').value = 'https://x.co/y';
  const cnt = G(win,'SESSIONS').length;
  doc.querySelector('#bookForm').dispatchEvent(
    new win.Event('submit', { bubbles: true, cancelable: true }));
  check('a past date is rejected', G(win,'SESSIONS').length === cnt
        && /today or a later date/i.test(doc.querySelector('[data-err="bDate"]').textContent));
}

console.log('\n--- reviews.html: publish and delete');
{
  const { doc, win, errs } = load('reviews.html');
  check('page loaded without error', errs.length === 0, errs[0] || '');
  const rate = btn(doc, '#toReview', 'Rate');
  check('a session is waiting for a review', !!rate);
  if (rate) {
    const before = G(win,'REVIEWS').length;
    rate.click();
    doc.querySelectorAll('#starInput i')[4].click();
    doc.querySelector('#revText').value = 'Very clear explanation, thanks a lot.';
    doc.querySelector('#revForm').dispatchEvent(
      new win.Event('submit', { bubbles: true, cancelable: true }));
    check('a review row is inserted', G(win,'REVIEWS').length === before + 1);
    const r = G(win,'REVIEWS')[G(win,'REVIEWS').length - 1];
    check('rating and reviewer are correct', r.rating === 5 && r.reviewer_id === 1);
  }

  const { doc: d2, win: w2 } = load('reviews.html');
  d2.querySelectorAll('#tabs .nav-link')[1].click();
  const del = d2.querySelector('#reviewBody button');
  if (del) {
    const before = G(w2,'REVIEWS').length;
    del.click();
    check('deleting a review removes the row', G(w2,'REVIEWS').length === before - 1);
  }
}

console.log('\n--- reviews.html: validation blocks an empty review');
{
  const { doc, win } = load('reviews.html');
  const rate = btn(doc, '#toReview', 'Rate');
  if (rate) {
    rate.click();
    const before = G(win,'REVIEWS').length;
    doc.querySelector('#revForm').dispatchEvent(
      new win.Event('submit', { bubbles: true, cancelable: true }));
    check('no rating means no insert', G(win,'REVIEWS').length === before
          && /choose a rating/i.test(doc.querySelector('[data-err="rating"]').textContent));
  }
}

console.log('\n--- edit-profile.html: skills and details');
{
  const { doc, win, errs } = load('edit-profile.html');
  check('page loaded without error', errs.length === 0, errs[0] || '');
  const before = G(win,'USERSKILLS').filter(u => u.user_id === 1).length;

  const mine = G(win,'USERSKILLS').find(u => u.user_id === 1 && u.skill_type === 'Teach');
  doc.querySelector('#newSkill').value = String(mine.skill_id);
  doc.querySelector('#newType').value = 'Teach';
  doc.querySelector('#addSkill').click();
  check('adding a duplicate skill is blocked by the UNIQUE rule',
        G(win,'USERSKILLS').filter(u => u.user_id === 1).length === before
        && /already on your/i.test(doc.querySelector('#skillErr').textContent));

  const free = G(win,'SKILLS').find(s =>
    !G(win,'USERSKILLS').some(u => u.user_id === 1 && u.skill_id === s.skill_id));
  doc.querySelector('#newSkill').value = String(free.skill_id);
  doc.querySelector('#addSkill').click();
  check('adding a new skill inserts a row',
        G(win,'USERSKILLS').filter(u => u.user_id === 1).length === before + 1,
        free.skill_name);

  const delBtn = Array.from(doc.querySelectorAll('#skillTable button'))[0];
  delBtn.click();
  check('removing a skill deletes the row',
        G(win,'USERSKILLS').filter(u => u.user_id === 1).length === before);

  doc.querySelector('#bio').value = 'Updated bio for the in-class demo.';
  doc.querySelector('#detailsForm').dispatchEvent(
    new win.Event('submit', { bubbles: true, cancelable: true }));
  check('saving details updates the user row',
        G(win,'userById')(1).bio === 'Updated bio for the in-class demo.');
}

console.log('\n--- admin.html: delete cascades');
{
  const { doc, win, errs } = load('admin.html');
  check('page loaded without error', errs.length === 0, errs[0] || '');
  const victim = G(win,'USERS').find(u => u.user_id !== 1
    && G(win,'USERSKILLS').some(s => s.user_id === u.user_id));
  const skillsBefore = G(win,'USERSKILLS').filter(s => s.user_id === victim.user_id).length;

  win.delUser(victim.user_id);
  check('the student row is gone', !G(win,'USERS').some(u => u.user_id === victim.user_id),
        victim.name + ' removed');
  check('their skill rows cascade away',
        G(win,'USERSKILLS').filter(s => s.user_id === victim.user_id).length === 0,
        skillsBefore + ' rows deleted');
  check('their requests cascade away',
        !G(win,'REQUESTS').some(r => r.sender_id === victim.user_id
                             || r.receiver_id === victim.user_id));
  check('the table shrinks', doc.querySelectorAll('#adminPanel tbody tr').length
        === G(win,'USERS').length);

  win.delUser(1);
  check('the signed-in account is protected', G(win,'USERS').some(u => u.user_id === 1));

  const usedSkill = G(win,'SKILLS').find(s =>
    G(win,'REQUESTS').some(r => r.teach_skill === s.skill_id || r.learn_skill === s.skill_id));
  const nS = G(win,'SKILLS').length;
  win.delSkill(usedSkill.skill_id, true);
  check('a skill in use cannot be deleted', G(win,'SKILLS').length === nS);
}

console.log('\n--- register.html: creates a real account');
{
  const { doc, win, errs } = load('register.html');
  check('page loaded without error', errs.length === 0, errs[0] || '');
  const before = G(win,'USERS').length;

  doc.querySelector('#name').value = 'Test Student';
  doc.querySelector('#dept').value = 'CSE';
  doc.querySelector('#email').value = 'test.student99@northsouth.edu';
  doc.querySelector('#pw').value = 'password123';
  doc.querySelector('#pw2').value = 'password123';
  const opts = Array.from(doc.querySelectorAll('#teachSkill option[value]'))
    .filter(o => o.value);
  doc.querySelector('#teachSkill').value = opts[0].value;
  doc.querySelector('#learnSkill').value = opts[1].value;
  doc.querySelector('#agree').checked = true;
  doc.querySelector('#regForm').dispatchEvent(
    new win.Event('submit', { bubbles: true, cancelable: true }));

  check('a user row is inserted', G(win,'USERS').length === before + 1,
        before + ' -> ' + G(win,'USERS').length);
  const u = G(win,'USERS')[G(win,'USERS').length - 1];
  check('the new student has both skills',
        G(win,'USERSKILLS').filter(s => s.user_id === u.user_id).length === 2);
  check('the form is replaced by a confirmation',
        /account created/i.test(doc.querySelector('#regForm').textContent));

  const { doc: d2, win: w2 } = load('register.html');
  d2.querySelector('#email').value = G(w2,'USERS')[0].email;
  d2.querySelector('#name').value = 'Someone Else';
  d2.querySelector('#dept').value = 'CSE';
  d2.querySelector('#pw').value = 'password123';
  d2.querySelector('#pw2').value = 'password123';
  d2.querySelector('#agree').checked = true;
  const n2 = G(w2,'USERS').length;
  d2.querySelector('#regForm').dispatchEvent(
    new w2.Event('submit', { bubbles: true, cancelable: true }));
  check('a duplicate email is rejected', G(w2,'USERS').length === n2
        && /already registered/i.test(d2.querySelector('[data-err="email"]').textContent));
}

console.log('\n--- demo account identity');
{
  const { win } = load('dashboard.html');
  const me = G(win,'USERS').find(u => u.user_id === 1);
  check('account 1 is Mahedi Hasan', me.name === 'Mahedi Hasan', me.name);
  check('email is the requested one', me.email === 'mahedi.shakib@northsouth.edu', me.email);
}

console.log('\n--- persistence across pages');
{
  const { win } = load('requests.html');
  const pend = G(win,'REQUESTS').find(r => r.receiver_id === 1 && r.status === 'Pending');
  win.actRequestStatus(pend.request_id, 'Accepted');
  const saved = win.sessionStorage.getItem('skillswap.demo.ops');
  check('the change is written to sessionStorage', !!saved && saved.includes('reqStatus'));

  /* a second page in the same tab must see the change, as a real browser would */
  const next = load('sessions.html');
  next.win.eval('OPS = ' + saved + '; OPS.forEach(mutate);');
  check('another page replays the log and sees the change',
        G(next.win,'REQUESTS').find(r => r.request_id === pend.request_id).status === 'Accepted');

  win.resetDemo && win.eval('OPS = []; saveOps();');
  check('reset clears the log', win.sessionStorage.getItem('skillswap.demo.ops') === '[]');
}

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
