import { chromium } from 'playwright';import fs from 'fs';
const B='http://localhost:4460/prototype/';const out='../design/screenshots/';
const b=await chromium.launch({executablePath:process.env.HOME+'/Library/Caches/ms-playwright/chromium-1243/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'});
const res={steps:[],errors:[]};const ok=(n,c)=>{res.steps.push({step:n,pass:!!c});if(!c)console.log('FAIL',n)};
async function login(p,acct){await p.goto(B);await p.selectOption('#acct',acct);await p.fill('#pw','password1');await p.click('#login button');await p.fill('#otp','123456');await p.click('#mfa button');await p.waitForSelector('.main')}
const snap=async(p,n)=>p.screenshot({path:out+n+'.png',fullPage:false});
for(const [vn,w,h] of [['mobile',390,844],['desktop',1440,900]])for(const scheme of ['light','dark']){
 const ctx=await b.newContext({viewport:{width:w,height:h},colorScheme:scheme});const p=await ctx.newPage();
 p.on('console',m=>m.type()==='error'&&res.errors.push(`${vn}/${scheme}: ${m.text()}`));p.on('pageerror',e=>res.errors.push(`${vn}/${scheme}: ${e}`));
 const S=n=>`${vn}-${scheme}-${n}`;const full=scheme==='light';
 await p.goto(B);await snap(p,S('login'));
 // validation
 await p.click('#login button');ok('login validation error',await p.textContent('#pwerr'));
 await login(p,'u1');ok('login+MFA reaches home',await p.locator('h1').textContent().then(t=>/Good morning/.test(t)));
 ok('title has no PHI',!/Asha|Mwende|SYN-P/.test(await p.title()));
 await snap(p,S('home'));
 // wizard
 await p.goto(B+'#/new');await p.click('[data-act=next]');ok('wizard step1 validation',await p.locator('.err').first().isVisible());
 await p.check('input[name=pid][value=p1]');await p.click('[data-act=next]');
 await p.check('input[name=prio][value=Emergency]');await p.click('[data-act=next]');ok('step2 reason validation',await p.locator('#e-reason').count());
 await p.fill('#reason','Suspected severe pre-eclampsia, needs obstetric care');await snap(p,S('new-step2'));await p.click('[data-act=next]');
 await p.check('input[name=dest][value=f5]');await snap(p,S('new-step3-stale'));await p.click('[data-act=next]');ok('stale facility warning dialog',await p.locator('dialog').count());await snap(p,S('dialog-stale'));await p.click('dialog [data-act=stalego]');
 await snap(p,S('new-step4'));await p.click('[data-act=send]');ok('consent validation',await p.locator('#e-consent').count());
 await p.check('input[name=consent]>>nth=0');await p.click('[data-act=send]');ok('sent success',await p.locator('h1').textContent().then(t=>/Referral sent/.test(t)));await snap(p,S('sent'));
 // offline
 await p.goto(B+'#/settings');await p.check('#offl');await snap(p,S('settings-offline'));
 await p.goto(B+'#/new');await p.check('input[name=pid][value=p2]');await p.click('[data-act=next]');await p.fill('#reason','Fever and drowsy child');await p.click('[data-act=next]');await p.check('input[name=dest][value=f3]');await p.click('[data-act=next]');await p.check('input[name=consent]>>nth=1');await p.click('[data-act=send]');
 ok('offline queues',await p.locator('h1').textContent().then(t=>/Saved/.test(t)));await p.goto(B+'#/home');await snap(p,S('home-outbox'));
 await p.goto(B+'#/settings');await p.uncheck('#offl');await p.goto(B+'#/home');await p.click('[data-act=sync]');await p.waitForTimeout(1200);ok('sync empties outbox',await p.locator('[data-act=sync]').count()===0);
 // referral detail, reveal
 await p.goto(B+'#/referral/r-7f3a');ok('masked by default',await p.locator('.masked').first().textContent().then(t=>t.includes('•')));await snap(p,S('referral'));
 await p.click('[data-act=reveal]');await p.click('dialog form button');ok('reveal requires reason',await p.locator('#rre').textContent());await p.selectOption('#rr',{index:1});await p.click('dialog form button');await p.waitForTimeout(150);ok('reveal shows name',await p.locator('.masked').first().textContent().then(t=>t.includes('Asha')));
 await p.click('[data-act=rt][data-t=messages]');await p.click('#msgf button');ok('msg validation',await p.textContent('#mterr'));await p.fill('#mt','Ambulance dispatched');await p.click('#msgf button');await snap(p,S('messages'));
 // break glass
 await p.goto(B+'#/referral/r-91de');ok('non-party locked',await p.locator('[data-act=bg]').count());await snap(p,S('locked'));await p.click('[data-act=bg]');await p.click('dialog form .danger');ok('bg needs reason',await p.textContent('#bge'));await p.fill('#bgr','Patient collapsed at our facility, need history');await p.click('dialog form .danger');await snap(p,S('breakglass'));
 await p.goto(B+'#/access');ok('access log shows break-glass',(await p.textContent('main')).includes('Break-glass'));await snap(p,S('access'));
 await p.goto(B+'#/directory');await snap(p,S('directory'));await p.fill('#dq','dialysis');ok('directory search',await p.locator('.row').count()===1);await p.fill('#dq','zzzz');ok('directory empty',/No facilities/.test(await p.textContent('main')));await p.fill('#dq','');
 await p.goto(B+'#/facility/f5');await snap(p,S('facility-stale'));
 ok('clinician blocked from dashboard',/No access/.test(await (async()=>{await p.goto(B+'#/dashboard');return p.textContent('main')})()));
 await p.goto(B+'#/states');await snap(p,S('states'));
 // coordinator
 await login(p,'u2');ok('coordinator inbox',await p.locator('h1').textContent().then(t=>t==='Inbox'));await snap(p,S('inbox'));
 await p.goto(B+'#/referral/r-0d6b');await p.click('[data-act=r-accept]');
 ok('status Accepted',(await p.textContent('main')).includes('Accepted'));await p.click('[data-act=r-schedule]');await p.click('dialog form button');ok('schedule validation',await p.textContent('#sde'));await p.fill('#sd','2026-03-25T10:00');await p.click('dialog form button');ok('scheduled',(await p.textContent('main')).includes('Scheduled'));await snap(p,S('referral-scheduled'));
 await p.goto(B+'#/referral/r-7f3a');await p.click('[data-act=r-decline]');await p.click('dialog form button');ok('decline needs reason',await p.textContent('#rce'));await p.selectOption('#rc',{index:1});await p.click('dialog form button');ok('declined',(await p.textContent('main')).includes('Declined'));await snap(p,S('declined'));


 await p.goto(B+'#/dashboard');await snap(p,S('dashboard'));
 // manager/admin
 await login(p,'u3');ok('manager lands on dashboard',/dashboard/.test(p.url()));
 await login(p,'u4');await snap(p,S('admin-users'));await p.click('[data-act=invite]');await p.click('dialog form button');ok('invite validation',await p.textContent('#iee'));await p.keyboard.press('Escape');
 await p.goto(B+'#/admin/audit');await p.fill('#aq','break');await snap(p,S('admin-audit'));await p.goto(B+'#/admin/retention');await p.fill('#ret1','0');await p.click('#ret button');ok('retention validation',await p.textContent('#ret1e'));await snap(p,S('admin-retention'));
 await p.goto(B+'#/admin/facilities');await snap(p,S('admin-facilities'));await p.goto(B+'#/notifications');await snap(p,S('notifications'));
 // timeout
 await p.goto(B+'#/settings');await p.click('[data-act=timeout]');await snap(p,S('session-timeout'));await p.click('#so');ok('timeout signs out',await p.locator('#login').count());
 await ctx.close();}
await b.close();
res.passed=res.steps.filter(s=>s.pass).length;res.failed=res.steps.filter(s=>!s.pass).length;
fs.writeFileSync(out+'clickthrough-results.json',JSON.stringify(res,null,1));
console.log('steps',res.steps.length,'passed',res.passed,'failed',res.failed,'console errors',res.errors.length);res.errors.slice(0,8).forEach(e=>console.log(e));
