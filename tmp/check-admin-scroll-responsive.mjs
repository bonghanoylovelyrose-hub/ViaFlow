import fs from 'node:fs/promises';

const source=await fs.readFile('tmp/check-account-layout.mjs','utf8');
const setup=source.slice(0,source.indexOf('const output = [];'))+'\nexport {command,evaluate,socket,exceptions};';
const {command,evaluate,socket,exceptions}=await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
const wait=duration=>new Promise(resolve=>setTimeout(resolve,duration));
const cases=[
    ...['all','active','inactive','pending','rejected'].map(category=>({name:'consumers-'+category,file:'account-layout-'+category+'.html'})),
    ...['dashboard','staff','billing','payments','loans','incidents','reports','reports-billing','meter-readings','announcements','dss-loans','dss-incidents','consumers-applications','consumers-reschedules','consumers-membership'].map(name=>({name,file:'admin-scroll-'+name+'.html'})),
];
const output=[];
for(const viewport of [{width:1366,height:768},{width:1920,height:1080},{width:390,height:844,mobile:true}]) {
    await command('Emulation.setDeviceMetricsOverride',{...viewport,mobile:!!viewport.mobile,deviceScaleFactor:1});
    for(const scenario of cases) {
        await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/'+scenario.file});
        await wait(650);
        await evaluate('(async()=>{await document.fonts.ready;return true})()');
        await wait(120);
        const metrics=await evaluate(`(() => {
            const main=document.querySelector('.main-content');
            const area=main.querySelector('.admin-records-area');
            const content=main.querySelector('.content-wrapper');
            const fallback=main.classList.contains('admin-content-scroll');
            const scroller=fallback?content:area;
            const fixed=[...main.querySelectorAll('.topbar,.page-heading,.dash-kpi,.dss-kpi,.payments-summary-grid,[data-admin-freeze]')].filter(element=>!element.closest('.modal')&&(!area.contains(element)||element.matches('[data-admin-freeze]'))).filter(element=>{const box=element.getBoundingClientRect();return box.height>0&&box.top>=0&&box.bottom<=innerHeight;});
            const before=fixed.map(element=>element.getBoundingClientRect().top);
            scroller.scrollTop=Math.min(180,scroller.scrollHeight-scroller.clientHeight);
            return new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>{
                const nested=[...area.querySelectorAll('*')].filter(element=>!element.matches('textarea,select')&&!element.closest('.modal,.dropdown-menu')).filter(element=>{const css=getComputedStyle(element);return ['auto','scroll'].includes(css.overflowY)&&element.scrollHeight>element.clientHeight+2}).map(element=>element.className);
                const primary=[...area.querySelectorAll('.admin-primary-freeze')].map(element=>({element,box:element.getBoundingClientRect()}));
                const usable=primary.filter(({box})=>box.height>0&&box.top>=area.getBoundingClientRect().top&&box.bottom<=area.getBoundingClientRect().bottom);
                const blocked=usable.map(({element,box})=>{
                    const control=element.matches('input,select,button,a')?element:element.querySelector('input:not([type="hidden"]),select,button,a');
                    if(!control) return null;
                    const bounds=control.getBoundingClientRect();
                    const target=document.elementFromPoint(bounds.left+bounds.width/2,bounds.top+bounds.height/2);
                    return target===control||control.contains(target)?null:control.id||control.textContent.trim().slice(0,30);
                }).filter(Boolean);
                const horizontal=[...area.querySelectorAll('.admin-natural-records')].find(element=>element.scrollWidth>element.clientWidth+2);
                let horizontalMoved=null;
                if(horizontal) {horizontal.scrollLeft=80;horizontalMoved=horizontal.scrollLeft>0;}
                resolve({mode:fallback?'content':'records',scroll:scroller.scrollTop,areaHeight:scroller.clientHeight,nested,blocked,horizontalMoved,bodyScroll:scrollY,fixedShifts:fixed.map((element,index)=>({text:element.textContent.trim().slice(0,32),shift:element.getBoundingClientRect().top-before[index]}))});
            })));
        })()`);
        const failures=[];
        if(metrics.nested.length) failures.push('nested');
        if(metrics.blocked.length) failures.push('blocked-controls');
        if(metrics.horizontalMoved===false) failures.push('horizontal-scroll');
        if(metrics.bodyScroll!==0) failures.push('body-scroll');
        if(metrics.mode==='records'&&metrics.fixedShifts.some(item=>Math.abs(item.shift)>1)) failures.push('moving-header');
        let modal=null;
        if(viewport.width===1366) {
            const target=await evaluate(`(() => {
                const preferred=${JSON.stringify(scenario.name==='consumers-inactive'?'#consumerReconnectModal':scenario.name==='consumers-reschedules'?'#adminReviewRescheduleModal':null)};
                const button=preferred?document.querySelector('button[data-bs-target="'+preferred+'"]:not(:disabled)'):document.querySelector('.main-content button[data-bs-toggle="modal"]:not(:disabled)');
                if(!button) return null;
                button.scrollIntoView({block:'nearest'});
                button.click();
                return button.getAttribute('data-bs-target');
            })()`);
            if(target) {
                await wait(650);
                modal=await evaluate(`(() => {
                    const modal=document.querySelector(${JSON.stringify(target)});
                    const scroller=document.querySelector('.admin-content-scroll .content-wrapper')||document.querySelector('.admin-records-area');
                    return {target:${JSON.stringify(target)},open:modal?.classList.contains('show'),locked:getComputedStyle(scroller).overflowY==='hidden'};
                })()`);
                if(!modal.open||!modal.locked) failures.push('modal');
                await evaluate(`document.querySelector(${JSON.stringify(target+' .btn-close')})?.click()`);
                await wait(650);
                if(await evaluate("document.querySelectorAll('.modal.show').length")) failures.push('modal-close');
            }
        }
        if(['consumers-active','payments','reports-billing','meter-readings'].includes(scenario.name)) {
            const screenshot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
            await fs.writeFile('tmp/admin-responsive-'+scenario.name+'-'+viewport.width+'.png',Buffer.from(screenshot.data,'base64'));
        }
        const result={name:scenario.name,viewport,metrics,modal,failures};
        output.push(result);
        console.log(JSON.stringify(result));
    }
}
await fs.writeFile('tmp/admin-scroll-responsive-results.json',JSON.stringify({output,exceptions},null,2));
console.log(JSON.stringify({cases:output.length,failures:output.filter(result=>result.failures.length).map(result=>({name:result.name,width:result.viewport.width,failures:result.failures,metrics:result.metrics})),exceptions}));
socket.close();
if(output.some(result=>result.failures.length)||exceptions.length) process.exitCode=1;
