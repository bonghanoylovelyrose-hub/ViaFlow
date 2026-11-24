import fs from 'node:fs/promises';
const source=await fs.readFile('tmp/check-account-layout.mjs','utf8');
const setup=source.slice(0,source.indexOf('const output = [];'))+'\nexport {command,evaluate,socket,exceptions};';
const {command,evaluate,socket,exceptions}=await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
const wait=duration=>new Promise(resolve=>setTimeout(resolve,duration));
const results=[];
for(const viewport of [{width:1440,height:900,mobile:false},{width:390,height:844,mobile:true}]) {
 await command('Emulation.setDeviceMetricsOverride',{...viewport,deviceScaleFactor:1});
 await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/account-layout-active.html'}); await wait(1200);
 for(const mode of ['wheel','drag','touch']) {
  const result=await evaluate(`(()=>{
    const area=document.querySelector('.admin-records-area'),bar=document.querySelector('.topbar'), title=document.querySelector('#active-accounts > .consumer-section-controls'), filters=document.querySelector('#account-filters'),head=document.querySelector('#consumer-accounts-table thead');
    const before={bar:bar.getBoundingClientRect().top,title:title.getBoundingClientRect().top,filter:filters.getBoundingClientRect().top,thead:head.getBoundingClientRect().top};
    const max=area.scrollHeight-area.clientHeight;
    if(${JSON.stringify(mode)}==='wheel') area.scrollTop=Math.min(120,max);
    if(${JSON.stringify(mode)}==='drag') area.scrollTop=Math.min(120,max);
    if(${JSON.stringify(mode)}==='touch') area.scrollTop=Math.min(120,max);
    return new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>{
      const mid={scroll:area.scrollTop,max,bar:bar.getBoundingClientRect().top,title:title.getBoundingClientRect().top,filter:filters.getBoundingClientRect().top,thead:head.getBoundingClientRect().top,body:area.querySelector('tbody').getBoundingClientRect().top,bodyOverflow:getComputedStyle(document.body).overflowY,areaOverflow:getComputedStyle(area).overflowY,mode:document.querySelector('.main-content').classList.contains('admin-content-scroll')};
      area.scrollTop=max;requestAnimationFrame(()=>requestAnimationFrame(()=>resolve({before,mid,bottom:{scroll:area.scrollTop,max,bar:bar.getBoundingClientRect().top,title:title.getBoundingClientRect().top,filter:filters.getBoundingClientRect().top,thead:head.getBoundingClientRect().top,body:area.querySelector('tbody').getBoundingClientRect().top}})));
    })));
  })()`);
  results.push({viewport:viewport.width,mode,...result});
 }
}
console.log(JSON.stringify({results,exceptions})); await fs.writeFile('tmp/consumer-scroll-regression-results.json',JSON.stringify({results,exceptions},null,2));socket.close();
if(exceptions.length||results.some(r=>r.mid.scroll<=0||Math.abs(r.mid.bar-r.before.bar)>1||r.mid.title<0||r.mid.filter<r.mid.title||r.mid.thead<r.mid.filter||r.mid.body<r.mid.thead||r.bottom.scroll<r.bottom.max-1||Math.abs(r.bottom.bar-r.before.bar)>1))process.exitCode=1;
