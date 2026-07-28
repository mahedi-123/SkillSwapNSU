/* Content-level checks: does each page actually render the seeded rows? */
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
  const vc = new VirtualConsole();
  const dom = new JSDOM(html, { runScripts: 'dangerously', virtualConsole: vc,
                                url: 'http://localhost/' + page + query });
  return dom.window.document;
}

let pass = 0, fail = 0;
function check(label, cond, detail = '') {
  if (cond) { pass++; console.log('  ok   ' + label + (detail ? '  (' + detail + ')' : '')); }
  else { fail++; console.log('  FAIL ' + label + '  ' + detail); }
}

const D = eval(dataJs + '; ({USERS,SKILLS,USERSKILLS,REQUESTS,SESSIONS,REVIEWS,DEPARTMENTS,CATEGORIES,DEMO_USER_ID})');

console.log('\n--- index.html');
{
  const d = load('index.html');
  check('hero shows a real swap card', d.querySelectorAll('#heroMatch .swap-skill').length === 2);
  check('hero facts filled', d.querySelectorAll('#heroMeta .small-label').length === 3);
  check('cursor trail is gone', !d.querySelector('#trailStage'));
  check('hero ask box present', !!d.querySelector('#askForm') && !!d.querySelector('#askDice'));
  check('popular skill chips offered', d.querySelectorAll('#askSuggest .chip').length === 4,
        d.querySelectorAll('#askSuggest .chip').length + ' chips');
  check('marquee filled and doubled for a seamless loop',
        d.querySelectorAll('#marqueeTrack > span').length === 2
        && d.querySelectorAll('#marqueeTrack .marquee-item').length > 20,
        d.querySelectorAll('#marqueeTrack .marquee-item').length + ' items');
  check('marquee duplicate hidden from screen readers',
        d.querySelectorAll('#marqueeTrack > span')[1].getAttribute('aria-hidden') === 'true');
  check('category menu lists all 11 categories',
        d.querySelectorAll('#megaPanel a').length === 11,
        d.querySelectorAll('#megaPanel a').length + '');
  check('category menu starts closed',
        !d.querySelector('#megaPanel').classList.contains('open')
        && d.querySelector('#megaBtn').getAttribute('aria-expanded') === 'false');
  check('FAQ accordion has six questions', d.querySelectorAll('#faqList .accordion-item').length === 6);
  check('footer has four link columns', d.querySelectorAll('.site-footer .footer-list').length === 4);
  check('numbered process has four steps', d.querySelectorAll('.step').length === 4);
  check('feature grid has six cards', d.querySelectorAll('.feature').length >= 6);
  check('5 proof numbers', d.querySelectorAll('#statRow .proof').length === 5);
  check('6 in-demand skill cards', d.querySelectorAll('#demandGrid .feature').length === 6);
  check('all 11 departments listed', d.querySelectorAll('#deptChips .chip').length === 11,
        d.querySelectorAll('#deptChips .chip').length + ' chips');
  check('table row counts shown', d.querySelectorAll('#tableStats tr').length === 6);
}

console.log('\n--- login.html / register.html');
{
  const d = load('login.html');
  check('demo-fill button present', !!d.querySelector('#fillDemo'));
  const r = load('register.html');
  check('department select filled', r.querySelectorAll('#dept option').length === 12,
        r.querySelectorAll('#dept option').length + ' options incl. placeholder');
  check('skill selects grouped by category',
        r.querySelectorAll('#teachSkill optgroup').length === D.CATEGORIES.length);
  check('all 50 skills selectable',
        r.querySelectorAll('#teachSkill option[value]:not([value=""])').length === 50);
}

console.log('\n--- dashboard.html');
{
  const d = load('dashboard.html');
  check('nav shell mounted', !!d.querySelector('#shellNav .topbar'));
  check('left rail mounted', !!d.querySelector('#shellRail .side-nav'));
  check('4 mini stats', d.querySelectorAll('#miniStats .stat').length === 4);
  check('match suggestions rendered', d.querySelectorAll('#matchList .swap').length > 0,
        d.querySelectorAll('#matchList article').length + ' matches');
  const pend = D.REQUESTS.filter(r => r.receiver_id === D.DEMO_USER_ID && r.status === 'Pending').length;
  check('pending requests match the data',
        d.querySelectorAll('#pendingList .swap').length === pend, pend + ' pending');
  check('next sessions listed', d.querySelector('#nextSessions').innerHTML.length > 50);
  check('my skills in right rail', d.querySelectorAll('#mySkills .chip').length > 0);
  check('top rated list', d.querySelectorAll('#topRated a').length === 5);
}

console.log('\n--- search.html');
{
  const d = load('search.html');
  check('49 other students listed by default',
        d.querySelectorAll('#results article').length === D.USERS.length - 1,
        d.querySelectorAll('#results article').length + ' cards');
  const filtered = load('search.html', '?skill=Python');
  const pyTeachers = new Set(D.USERSKILLS.filter(us => us.skill_type === 'Teach' &&
      D.SKILLS.find(s => s.skill_id === us.skill_id).skill_name.toLowerCase().includes('python'))
      .map(us => us.user_id));
  pyTeachers.delete(D.DEMO_USER_ID);
  check('skill filter narrows results',
        filtered.querySelectorAll('#results article').length === pyTeachers.size,
        'expected ' + pyTeachers.size + ', got ' + filtered.querySelectorAll('#results article').length);
  check('active filter chip shown', filtered.querySelectorAll('#activeChips .chip').length === 1);
  const dept = load('search.html', '?department=CSE');
  const cseOthers = D.USERS.filter(u => u.department === 'CSE' && u.user_id !== D.DEMO_USER_ID).length;
  check('department filter works',
        dept.querySelectorAll('#results article').length === cseOthers,
        cseOthers + ' CSE students');
}

console.log('\n--- profile.html');
{
  const d = load('profile.html', '?id=19');
  const u = D.USERS.find(x => x.user_id === 19);
  check('shows the requested student', d.querySelector('#header h1').textContent.trim() === u.name,
        u.name);
  const teach = D.USERSKILLS.filter(x => x.user_id === 19 && x.skill_type === 'Teach').length;
  const learn = D.USERSKILLS.filter(x => x.user_id === 19 && x.skill_type === 'Learn').length;
  check('all skills listed', d.querySelectorAll('#skillBlocks .chip').length === teach + learn,
        teach + ' teach + ' + learn + ' learn');
  const revs = D.REVIEWS.filter(r => r.reviewee_id === 19).length;
  check('review count matches', d.querySelectorAll('#reviewList .stars').length >= revs,
        revs + ' reviews');
  const hist = D.REQUESTS.filter(r => r.sender_id === 19 || r.receiver_id === 19).length;
  check('exchange history complete',
        d.querySelectorAll('#historyTable tbody tr').length === hist, hist + ' rows');
  check('proposal panel present for others', !!d.querySelector('#proposal'));

  const mine = load('profile.html', '?id=1');
  check('own profile shows Edit button instead of proposal',
        !mine.querySelector('#proposal') && /Edit profile/.test(mine.querySelector('#header').textContent));
}

console.log('\n--- edit-profile.html');
{
  const d = load('edit-profile.html');
  const n = D.USERSKILLS.filter(x => x.user_id === D.DEMO_USER_ID).length;
  check('skill table lists every row of mine',
        d.querySelectorAll('#skillTable tbody tr').length === n, n + ' skills');
  check('bio prefilled', d.querySelector('#bio').value.length > 10);
  check('department preselected', !!d.querySelector('#dept option[selected]'));
}

console.log('\n--- requests.html');
{
  const d = load('requests.html');
  const recv = D.REQUESTS.filter(r => r.receiver_id === D.DEMO_USER_ID).length;
  check('received tab shows every received request',
        d.querySelectorAll('#reqList article').length === recv, recv + ' received');
  check('5 status counters', d.querySelectorAll('#statusStats .stat').length === 5);
  const sent = load('requests.html', '?dir=sent');
  const sentN = D.REQUESTS.filter(r => r.sender_id === D.DEMO_USER_ID).length;
  check('sent tab shows every sent request',
        sent.querySelectorAll('#reqList article').length === sentN, sentN + ' sent');
}

console.log('\n--- sessions.html');
{
  const d = load('sessions.html');
  const mySess = D.SESSIONS.filter(s => {
    const r = D.REQUESTS.find(x => x.request_id === s.request_id);
    return r && (r.sender_id === D.DEMO_USER_ID || r.receiver_id === D.DEMO_USER_ID);
  });
  const up = mySess.filter(s => s.status === 'Scheduled').length;
  check('upcoming tab count', d.querySelectorAll('#sessBody article').length === up, up + ' upcoming');
  check('4 session stats', d.querySelectorAll('#sessStats .stat').length === 4);
  check('booking modal lists accepted requests',
        d.querySelectorAll('#bReq option').length > 0);
}

console.log('\n--- reviews.html');
{
  const d = load('reviews.html');
  const recv = D.REVIEWS.filter(r => r.reviewee_id === D.DEMO_USER_ID).length;
  check('rating breakdown rendered', d.querySelectorAll('#reviewBody .progress').length === 5);
  check('received reviews shown', d.querySelectorAll('#reviewBody .stars').length >= recv,
        recv + ' about me');
  check('5 clickable stars in modal', d.querySelectorAll('#starInput i').length === 5);
}

console.log('\n--- admin.html');
{
  const d = load('admin.html');
  check('6 admin stats', d.querySelectorAll('#adminStats .stat').length === 6);
  check('all 50 students in the table',
        d.querySelectorAll('#adminPanel tbody tr').length === 50,
        d.querySelectorAll('#adminPanel tbody tr').length + ' rows');
}

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
