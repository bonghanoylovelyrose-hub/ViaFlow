import fs from 'node:fs/promises';

const source = await fs.readFile('tmp/check-account-layout.mjs', 'utf8');
const setup = source.slice(0, source.indexOf('const output = [];'))+'\nexport { command, evaluate, socket, exceptions };';
const { command, evaluate, socket, exceptions } = await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
const wait = duration => new Promise(resolve => setTimeout(resolve, duration));
const scenarios = [
    ...['all','active','inactive','pending','rejected'].map(category=>({name:'consumers-'+category,file:'account-layout-'+category+'.html'})),
    ...['dashboard','staff','billing','payments','loans','incidents','reports','reports-billing','meter-readings','announcements','dss-loans','dss-incidents','consumers-applications','consumers-reschedules','consumers-membership'].map(name=>({name,file:'admin-scroll-'+name+'.html'})),
];
const results=[];
function snapshot() {
    const main=document.querySelector('.main-content');
    const content=main.querySelector('.content-wrapper');
    const area=content.querySelector('.admin-records-area');
    const rect=element=>{const box=element.getBoundingClientRect();return {top:box.top,left:box.left,width:box.width,height:box.height,bottom:box.bottom};};
    const tableMetrics=[...main.querySelectorAll('table')].filter(table=>!table.closest('.modal')).map(table=>{
        const row=table.tBodies[0]?.rows[0];
        const cell=row?.cells[0];
        const styles=cell?getComputedStyle(cell):null;
        return {headings:[...table.querySelectorAll('thead th')].map(cell=>cell.textContent.trim()),width:table.getBoundingClientRect().width,rowHeight:row?.getBoundingClientRect().height,font:styles?.font,padding:styles?.padding};
    });
    const fixed=[...main.querySelectorAll('.topbar,.page-heading,.dash-kpi,.dashboard-card,.billing-summary-card,.payments-summary-grid,[data-admin-freeze]')].filter(element=>!element.closest('.modal')&&(!area?.contains(element)||element.matches('[data-admin-freeze]'))).filter(element=>{const box=element.getBoundingClientRect();return box.height>0&&box.top>=0&&box.bottom<=innerHeight;});
    return {viewport:[innerWidth,innerHeight],mode:main.classList.contains('admin-content-scroll')?'content':'records',area:area?{...rect(area),client:area.clientHeight,total:area.scrollHeight}:null,tableMetrics,fixed:fixed.map(element=>({text:element.textContent.trim().slice(0,70),...rect(element)})),body:document.documentElement.scrollHeight};
}
async function load(file) {
    await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/'+file});
    await wait(1100);
    await evaluate('(async()=>{await document.fonts.ready;return true})()');
    await wait(150);
}
await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
for(const scenario of scenarios) {
    const original=await fs.readFile('tmp/'+scenario.file,'utf8');
    await fs.writeFile('tmp/one-scroll-baseline-'+scenario.name+'.html',original.replace(/<script>[\s\S]*?<\/script>/g,script=>script.includes("area.className = 'admin-records-area'")?'':script));
    await load('one-scroll-baseline-'+scenario.name+'.html');
    const baseline=await evaluate('('+snapshot.toString()+')()');
    await load(scenario.file);
    const before=await evaluate('('+snapshot.toString()+')()');
    const differences=[];
    before.tableMetrics.forEach((table,index)=>{
        const prior=baseline.tableMetrics[index];
        for(const key of ['headings','font','padding']) if(JSON.stringify(prior?.[key])!==JSON.stringify(table[key])) differences.push(key+' '+index);
        for(const key of ['width','rowHeight']) if(Math.abs((prior?.[key]??0)-(table[key]??0))>1) differences.push(key+' '+index+': '+prior?.[key]+' -> '+table[key]);
    });
    const movement=await evaluate(`(() => {
        const main=document.querySelector('.main-content');
        const area=main.querySelector('.admin-records-area');
        if(!area) return {error:'No records area'};
        const fixed=[...main.querySelectorAll('.topbar,.page-heading,.dash-kpi,.dashboard-card,.billing-summary-card,.payments-summary-grid,[data-admin-freeze]')].filter(element=>!element.closest('.modal')&&(!area.contains(element)||element.matches('[data-admin-freeze]'))).filter(element=>{const box=element.getBoundingClientRect();return box.height>0&&box.top>=0&&box.bottom<=innerHeight;});
        const positions=fixed.map(element=>element.getBoundingClientRect().top);
        const record=area.querySelector('tbody tr,canvas,.announcement-card,.dss-chart-card,.dash-panel');
        const recordTop=record?.getBoundingClientRect().top;
        const nested=[...area.querySelectorAll('*')].filter(element=>!element.matches('textarea,select')&&!element.closest('.modal,.dropdown-menu')).filter(element=>{const css=getComputedStyle(element);return ['auto','scroll'].includes(css.overflowY)&&element.scrollHeight>element.clientHeight+2;}).map(element=>({class:element.className,height:element.clientHeight,total:element.scrollHeight}));
        area.scrollTop=200;
        return new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>resolve({
            scroll:area.scrollTop,recordShift:record?record.getBoundingClientRect().top-recordTop:null,
            fixedShifts:fixed.map((element,index)=>({text:element.textContent.trim().slice(0,55),shift:element.getBoundingClientRect().top-positions[index]})),
            bodyScroll:window.scrollY,nested,
            duplicates:[...document.querySelectorAll('[id]')].map(element=>element.id).filter((id,index,ids)=>ids.indexOf(id)!==index),
        }))))
    })()`);
    const deep = await evaluate(`(() => {
        const area=document.querySelector('.admin-records-area');
        area.scrollTop=area.scrollHeight;
        return new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(()=>{
            const areaTop=area.getBoundingClientRect().top;
            const shifts=[...area.querySelectorAll('.admin-primary-freeze')].map(element=>({text:element.textContent.trim().slice(0,40),shift:element.getBoundingClientRect().top-areaTop-parseFloat(element.style.getPropertyValue('--admin-freeze-top'))}));
            const visible=[...area.querySelectorAll('[data-admin-freeze]')].map(element=>({element,box:element.getBoundingClientRect()})).filter(({box})=>box.height>0&&box.top>=areaTop&&box.bottom<=area.getBoundingClientRect().bottom);
            const overlaps=[];
            visible.forEach((first,index)=>visible.slice(index+1).forEach(second=>{
                if(Math.min(first.box.right,second.box.right)-Math.max(first.box.left,second.box.left)>2&&Math.min(first.box.bottom,second.box.bottom)-Math.max(first.box.top,second.box.top)>2) overlaps.push([first.element.textContent.trim().slice(0,25),second.element.textContent.trim().slice(0,25)]);
            }));
            const pagination=[...area.querySelectorAll('.pagination')].map(element=>({items:element.querySelectorAll('a').length,hrefs:[...element.querySelectorAll('a')].every(link=>!!link.href)}));
            resolve({scroll:area.scrollTop,shifts,overlaps,pagination});
        })));
    })()`);
    await evaluate("document.querySelector('.admin-records-area').scrollTop=200");
    await wait(100);
    const screenshot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
    await fs.writeFile('tmp/admin-one-scroll-'+scenario.name+'.png',Buffer.from(screenshot.data,'base64'));
    results.push({name:scenario.name,baseline,before,differences,movement,deep});
    console.log(JSON.stringify({name:scenario.name,mode:before.mode,area:before.area,differences,movement,deep}));
}
await fs.writeFile('tmp/admin-one-scroll-results.json',JSON.stringify({results,exceptions},null,2));
console.log(JSON.stringify({exceptions}));
socket.close();
const failures=results.filter(result=>result.differences.length||result.movement.nested.length||result.movement.duplicates.length||result.movement.fixedShifts.some(item=>Math.abs(item.shift)>1)||result.deep.shifts.some(item=>Math.abs(item.shift)>1)||result.deep.overlaps.length);
if(failures.length||exceptions.length) {
    console.log(JSON.stringify({failed:failures.map(result=>result.name)}));
    process.exitCode=1;
}
