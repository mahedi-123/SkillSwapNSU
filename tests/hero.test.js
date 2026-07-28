/* Drive the new hero controls the way a visitor would. */
const fs=require('fs'),path=require('path'),{JSDOM,VirtualConsole}=require('jsdom');
const ROOT=path.resolve(__dirname,'..');
const dataJs=fs.readFileSync(ROOT+'/static/js/data.js','utf8');
const uiJs=fs.readFileSync(ROOT+'/static/js/ui.js','utf8');
const STUB=`window.bootstrap={Modal:class{show(){}hide(){}static getInstance(){return{hide(){}}}},Toast:class{show(){}}};`;
function load(){
  let html=fs.readFileSync(ROOT+'/index.html','utf8')
    .replace(/<script src="https:\/\/cdn[^"]*"><\/script>/g,()=>'<script>'+STUB+'</script>')
    .replace(/<script src="static\/js\/data\.js"><\/script>/,()=>'<script>'+dataJs+'</script>')
    .replace(/<script src="static\/js\/ui\.js"><\/script>/,()=>'<script>'+uiJs+'</script>')
    .replace(/<link[^>]*https:\/\/[^>]*>/g,()=>'');
  const errs=[];const vc=new VirtualConsole();vc.on('jsdomError',e=>errs.push(e.message));
  const dom=new JSDOM(html,{runScripts:'dangerously',virtualConsole:vc,url:'http://localhost/index.html',pretendToBeVisual:true});
  return {dom,doc:dom.window.document,win:dom.window,errs};
}
let pass=0,fail=0;const ck=(l,c,x='')=>{c?(pass++,console.log('  ok   '+l+(x?'  ('+x+')':''))):(fail++,console.log('  FAIL '+l+'  '+x))};
const G=(w,e)=>w.eval(e);

console.log('\n--- the dice');
{
  const {doc,win,errs}=load();
  ck('no script error',errs.length===0,errs[0]||'');
  const input=doc.querySelector('#askInput'),dice=doc.querySelector('#askDice');
  ck('input starts empty',input.value==='');
  const names=new Set(G(win,'SKILLS').map(s=>s.skill_name));
  const rolled=new Set();
  for(let i=0;i<25;i++){dice.click();rolled.add(input.value);}
  ck('every roll lands on a real catalogue skill',[...rolled].every(v=>names.has(v)),
     [...rolled].slice(0,3).join(', ')+'…');
  ck('rolls vary rather than sticking',rolled.size>5,rolled.size+' distinct in 25 rolls');
  ck('never repeats immediately',(()=>{let prev=null,bad=0;
     for(let i=0;i<40;i++){dice.click();if(input.value===prev)bad++;prev=input.value;}return bad===0})());
  ck('dice spins then settles',dice.className.includes('rolling'));
}

console.log('\n--- popular chips and submit');
{
  const {doc,win}=load();
  const chips=doc.querySelectorAll('#askSuggest .chip');
  ck('four popular skills offered',chips.length===4);
  const demand={};
  G(win,'USERSKILLS').filter(u=>u.skill_type==='Learn')
    .forEach(u=>demand[u.skill_id]=(demand[u.skill_id]||0)+1);
  const topName=G(win,'SKILLS').find(s=>s.skill_id===Number(
    Object.entries(demand).sort((a,b)=>b[1]-a[1])[0][0])).skill_name;
  ck('the most wanted skill leads the list',chips[0].textContent.trim()===topName,topName);
  ck('chips carry the skill for the search',chips[0].dataset.skill===topName);
}

console.log('\n--- marquee');
{
  const {doc,win}=load();
  const spans=doc.querySelectorAll('#marqueeTrack > span');
  ck('track written twice',spans.length===2);
  ck('both halves identical so the loop has no seam',
     spans[0].innerHTML===spans[1].innerHTML.replace(/ aria-hidden="true"/,''));
  const taught=new Set(G(win,'USERSKILLS').filter(u=>u.skill_type==='Teach').map(u=>u.skill_id));
  ck('one entry per taught skill',
     spans[0].querySelectorAll('.marquee-item').length===taught.size,
     taught.size+' skills have a teacher');
  ck('each item links into search',
     [...spans[0].querySelectorAll('.marquee-item')].every(a=>a.href.includes('search.html?skill=')));
  ck('counts shown are real',
     [...spans[0].querySelectorAll('.marquee-item .n')].every(n=>Number(n.textContent)>0));
}

console.log('\n--- category menu');
{
  const {doc,win}=load();
  const btn=doc.querySelector('#megaBtn'),panel=doc.querySelector('#megaPanel');
  ck('closed on load',!panel.classList.contains('open'));
  btn.click();
  ck('opens on click',panel.classList.contains('open')
     && btn.getAttribute('aria-expanded')==='true');
  const cats=G(win,'CATEGORIES');
  ck('one entry per category',panel.querySelectorAll('a').length===cats.length,cats.length+'');
  const first=panel.querySelector('a');
  ck('entries link to a real filter',first.href.includes('search.html?category='));
  const counts=[...panel.querySelectorAll('.n')].map(n=>Number(n.textContent));
  const total=counts.reduce((a,b)=>a+b,0);
  ck('counts add up to the whole catalogue',total===G(win,'SKILLS').length,total+' skills');
  btn.click();
  ck('toggles shut',!panel.classList.contains('open'));
  btn.click();
  doc.body.click();
  ck('an outside click closes it',!panel.classList.contains('open'));
  btn.click();
  const esc=new win.KeyboardEvent('keydown',{key:'Escape',bubbles:true});
  doc.dispatchEvent(esc);
  ck('Escape closes it',!panel.classList.contains('open'));
}
console.log('\n'+pass+' passed, '+fail+' failed');
process.exit(fail?1:0);
