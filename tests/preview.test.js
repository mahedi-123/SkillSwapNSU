const fs=require('fs'); const {JSDOM,VirtualConsole}=require('jsdom');
let html=fs.readFileSync(require('path').resolve(__dirname,'..','preview.html'),'utf8')
  .replace(/<link[^>]*https:\/\/[^>]*>/g,()=>'');
const errs=[]; const vc=new VirtualConsole(); vc.on('jsdomError',e=>errs.push(e.message));
const dom=new JSDOM(html,{runScripts:'dangerously',virtualConsole:vc,url:'http://localhost/preview.html',pretendToBeVisual:true});
const d=dom.window.document,w=dom.window;
let pass=0,fail=0; const ck=(l,c,x='')=>{c?(pass++,console.log('  ok   '+l+(x?'  ('+x+')':''))):(fail++,console.log('  FAIL '+l+'  '+x))};
// jsdom has no layout, so it reports scrollTo as unimplemented; real browsers do not
const real=errs.filter(e=>!/scrollTo|Not implemented/.test(e));
ck('no script error',real.length===0,real[0]||'');
ck('11 tabs',d.querySelectorAll('.pv-tab').length===11,d.querySelectorAll('.pv-tab').length+'');
ck('11 panes',d.querySelectorAll('.pv-pane').length===11);
ck('home shown first',d.querySelector('#pv-home').classList.contains('on'));
ck('only one pane visible',d.querySelectorAll('.pv-pane.on').length===1);
ck('theme tokens inlined',/--ember:\s*#FF5A4D/.test(html));
ck('hero present in home',!!d.querySelector('#pv-home #hero'));
ck('hero ask box captured',!!d.querySelector('#pv-home #askForm'));
ck('marquee captured',d.querySelectorAll('#pv-home .marquee-item').length>20,
   d.querySelectorAll('#pv-home .marquee-item').length+' items');
ck('category menu captured',d.querySelectorAll('#pv-home #megaPanel a').length===11);
ck('scroll progress present',!!d.querySelector('#pvBar'));
ck('spotlight present',!!d.querySelector('#pvSpot'));
ck('tilt cards captured',d.querySelectorAll('#pv-home [data-tilt]').length===6);
ck('review slider captured',d.querySelectorAll('#pv-home .review-card').length>10,
   d.querySelectorAll('#pv-home .review-card').length+' cards');
ck('two slider rows',d.querySelectorAll('#pv-home .review-track').length===2);
ck('steps captured',d.querySelectorAll('#pv-home .step').length===4);
ck('peekable cards on search',d.querySelectorAll('#pv-find .peekable').length>0,
   d.querySelectorAll('#pv-find .peekable').length+'');
ck('pulse on the waiting badge',!!d.querySelector('#pv-dash .badge-dot.pulse'));
ck('dashboard has swap cards',d.querySelectorAll('#pv-dash .swap').length>0,d.querySelectorAll('#pv-dash .swap').length+' cards');
ck('search rendered every student',d.querySelectorAll('#pv-find #results article').length===49,d.querySelectorAll('#pv-find #results article').length+'');
ck('admin table full',d.querySelectorAll('#pv-admin tbody tr').length>=50);
ck('requests rendered',d.querySelectorAll('#pv-requests article').length>0);
ck('sessions rendered',d.querySelectorAll('#pv-sessions .panel').length>0);
ck('login form captured',!!d.querySelector('#pv-login #loginForm'));
ck('no leftover page scripts',d.querySelectorAll('.pv-pane script').length===0);
// switch tabs
const tabs=d.querySelectorAll('.pv-tab');
tabs[4].click();
ck('clicking a tab switches the pane',d.querySelector('#pv-requests').classList.contains('on') && !d.querySelector('#pv-home').classList.contains('on'));
ck('file label updates',d.querySelector('#pvFile').textContent==='requests.html',d.querySelector('#pvFile').textContent);
tabs[0].click();
// dice
const input=d.querySelector('#pv-home #askInput'),dice=d.querySelector('#pv-home #askDice');
dice.click();
ck('dice fills a real skill',input.value.length>1,input.value);
const before=input.value; dice.click();
ck('dice rolls to something else',input.value!==before,input.value);
// hero card no longer hides text under the badge
const hs=d.querySelector('#pv-home #heroMatch .swap');
ck('hero swap uses the even variant',hs.classList.contains('even'));
// steps cue
ck('first step cued on load',d.querySelectorAll('#pv-home .step')[0].classList.contains('cued'));
// category menu
const mb=d.querySelector('#pv-home #megaBtn'),mp=d.querySelector('#pv-home #megaPanel');
mb.click(); ck('menu opens in the preview',mp.classList.contains('open'));
mb.click(); ck('menu closes again',!mp.classList.contains('open'));
// faq accordion
const ab=d.querySelector('#pv-home .accordion-button');
if(ab){ab.click(); const t=ab.getAttribute('data-bs-target');
 ck('FAQ accordion opens',d.querySelector(t).classList.contains('show'));}
console.log('\n'+pass+' passed, '+fail+' failed'); process.exit(fail?1:0);
