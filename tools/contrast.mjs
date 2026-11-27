import { chromium } from 'playwright';import fs from 'fs';
const T=JSON.parse(fs.readFileSync('../design/design-tokens.json','utf8'));
const lin=c=>{c/=255;return c<=.03928?c/12.92:((c+.055)/1.055)**2.4};const rgb=h=>[1,3,5].map(i=>parseInt(h.slice(i,i+2),16));
const L=h=>{const [r,g,b]=rgb(h);return .2126*lin(r)+.7152*lin(g)+.0722*lin(b)};const cr=(a,b)=>{const x=L(a),y=L(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05)};
const pairs=[['ink','paper',4.5],['ink','surface',4.5],['ink-2','paper',4.5],['ink-2','surface',4.5],['ink-2','sunken',4.5],['on-brand','brand',4.5],['on-brand-soft','brand-soft',4.5],['brand','surface',4.5],['brand','paper',4.5],['ok','ok-soft',4.5],['warn','warn-soft',4.5],['bad','bad-soft',4.5],['info','info-soft',4.5],['ok','surface',4.5],['bad','surface',4.5],['line-strong','surface',3],['focus','surface',3],['focus','paper',3],['brand','brand-soft',3],['chart-a','surface',3],['chart-b','surface',3],['chart-c','surface',3]];
const out=[];let fail=0;for(const th of ['light','dark'])for(const [f,bg,min] of pairs){const r=cr(T[th][f],T[th][bg]);const pass=r>=min;if(!pass)fail++;out.push({theme:th,fg:f,bg,ratio:+r.toFixed(2),min,pass})}
console.log('token pairs:',out.length,'fail:',fail);out.filter(o=>!o.pass).forEach(o=>console.log(' FAIL',o));
const b=await chromium.launch({executablePath:process.env.HOME+'/Library/Caches/ms-playwright/chromium-1243/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'});
const dom=[];const B='http://localhost:4460/prototype/';
const sample=()=>{const parse=c=>{const m=c.match(/[\d.]+/g).map(Number);return{r:m[0],g:m[1],b:m[2],a:m.length>3?m[3]:1}};
const lin=c=>{c/=255;return c<=.03928?c/12.92:((c+.055)/1.055)**2.4};const Lm=c=>.2126*lin(c.r)+.7152*lin(c.g)+.0722*lin(c.b);
const over=(f,b)=>({r:f.r*f.a+b.r*(1-f.a),g:f.g*f.a+b.g*(1-f.a),b:f.b*f.a+b.b*(1-f.a),a:1});
const bgOf=el=>{const st=[];for(let e=el;e;e=e.parentElement){const c=parse(getComputedStyle(e).backgroundColor);if(c.a>0)st.push(c);if(c.a===1)break}let base=parse(getComputedStyle(document.body).backgroundColor);for(let i=st.length-1;i>=0;i--)base=over(st[i],base);return base};
const bad=[];let n=0;for(const el of document.querySelectorAll('body *')){const cs=getComputedStyle(el);if(el.closest('[disabled]')||cs.visibility==='hidden'||+cs.opacity===0)continue;const r=el.getBoundingClientRect();if(!r.width||!r.height)continue;if(![...el.childNodes].some(x=>x.nodeType===3&&x.textContent.trim()))continue;
if(el.closest('.sr,.skip'))continue;const bg=bgOf(el),f=over(parse(cs.color),bg);const a=Lm(f),b2=Lm(bg);const ratio=(Math.max(a,b2)+.05)/(Math.min(a,b2)+.05);const fs=parseFloat(cs.fontSize),bold=+cs.fontWeight>=700;const min=(fs>=24||(fs>=18.66&&bold))?3:4.5;n++;if(ratio<min)bad.push({text:el.textContent.trim().slice(0,30),ratio:+ratio.toFixed(2),min,cls:el.className})}return{n,bad}};
async function login(p,acct){await p.goto(B);await p.selectOption('#acct',acct);await p.fill('#pw','password1');await p.click('#login button');await p.fill('#otp','123456');await p.click('#mfa button');await p.waitForSelector('.main')}
const routes={u1:['home','new','referrals','referral/r-7f3a','referral/r-91de','directory','facility/f5','access','settings','notifications','states'],u2:['inbox','referral/r-83aa','referral/r-5a07','referral/r-e3c8','dashboard'],u4:['admin/users','admin/audit','admin/retention','admin/facilities']};
for(const scheme of ['light','dark'])for(const [vn,w,h] of [['desktop',1440,900],['mobile',390,844]]){const ctx=await b.newContext({viewport:{width:w,height:h},colorScheme:scheme});const p=await ctx.newPage();
 for(const [u,rs] of Object.entries(routes)){await login(p,u);for(const r of rs){await p.goto(B+'#/'+r);await p.waitForTimeout(80);if(r==='new'){await p.check('input[name=pid][value=p1]');}const res=await p.evaluate(sample);dom.push({scheme,vn,r,n:res.n,bad:res.bad})}}
 await ctx.close()}
await b.close();const total=dom.reduce((s,d)=>s+d.n,0),bad=dom.flatMap(d=>d.bad.map(x=>({...x,scheme:d.scheme,vn:d.vn,route:d.r})));
console.log('DOM text elements sampled:',total,'below threshold:',bad.length);const seen=new Set();bad.forEach(x=>{const k=x.scheme+x.cls+x.text;if(!seen.has(k)){seen.add(k);console.log(' ',x.scheme,x.vn,x.route,JSON.stringify(x.text),x.ratio,'/',x.min,x.cls)}});
fs.writeFileSync('../design/screenshots/contrast-results.json',JSON.stringify({tokenPairs:out,tokenFailures:fail,domSampled:total,domFailures:bad},null,1));
