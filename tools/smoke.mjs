import { chromium } from 'playwright';
const b=await chromium.launch({executablePath:process.env.HOME+'/Library/Caches/ms-playwright/chromium-1243/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'});
const p=await (await b.newContext({viewport:{width:390,height:844}})).newPage();const errs=[];p.on('console',m=>m.type()==='error'&&errs.push(m.text()));p.on('pageerror',e=>errs.push(String(e)));
await p.goto('http://localhost:4460/prototype/');await p.fill('#pw','password1');await p.click('#login button');await p.fill('#otp','123456');await p.click('#mfa button');
await p.waitForTimeout(300);await p.screenshot({path:'/tmp/s1.png'});console.log(errs, await p.title());await b.close();
