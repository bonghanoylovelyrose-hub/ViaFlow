import fs from 'node:fs/promises';
const source=await fs.readFile('tmp/check-account-layout.mjs','utf8');
const setup=source.slice(0,source.indexOf('const output = [];'))+'\nexport {command,evaluate,socket,exceptions};';
const {command,evaluate,socket,exceptions}=await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
const wait=duration=>new Promise(resolve=>setTimeout(resolve,duration));
const pages=[['all','account-layout-all.html'],['active','account-layout-active.html'],['inactive','account-layout-inactive.html'],['pending','account-layout-pending.html'],['rejected','account-layout-rejected.html'],['applications','admin-scroll-consumers-applications.html'],['reschedules','admin-scroll-consumers-reschedules.html'],['membership','admin-scroll-consumers-membership.html']];
const results=[];
for(const viewport of [{width:1440,height:900,mobile:false},{width:390,height:844,mobile:true}]) {
 await command('Emulation.setDeviceMetricsOverride',{...viewport,deviceScaleFactor:1});
 for(const [name,file] of pages) {
  await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/'+file}); await wait(850);
  const result=await evaluate(`(()=>{const main=document.querySelector('.main-content'),bar=main.querySelector('.topbar'),content=main.querySelector('.content-wrapper'),heading=content.querySelector('.page-heading');const before=bar.getBoundingClientRect().top;const contentBefore=heading.getBoundingClientRect().top;const style=getComputedStyle(bar);content.scrollTop=Math.min(220,content.scrollHeight-content.clientHeight);return new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>resolve({fixed:style.position==='fixed',top:style.top,barBefore:before,barAfter:bar.getBoundingClientRect().top,contentMoved:heading.getBoundingClientRect().top<contentBefore,headerHeight:bar.getBoundingClientRect().height,contentTop:content.getBoundingClientRect().top,overflow:content.scrollHeight>content.clientHeight,bodyOverflow:getComputedStyle(document.body).overflowY}))))})()`);
  results.push({viewport:viewport.width,name,...result});
 }
}
console.log(JSON.stringify({results,exceptions})); await fs.writeFile('tmp/consumer-fixed-header-results.json',JSON.stringify({results,exceptions},null,2)); socket.close();
if(exceptions.length||results.some(r=>!r.fixed||parseFloat(r.top)!==0||Math.abs(r.barBefore-r.barAfter)>1||!r.contentMoved||r.contentTop<r.headerHeight-1)) process.exitCode=1;
