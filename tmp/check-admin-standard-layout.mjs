import fs from 'node:fs/promises';

const source = await fs.readFile('tmp/check-account-layout.mjs', 'utf8');
const setup = source.slice(0, source.indexOf('const output = [];')) + '\nexport {command,evaluate,socket,exceptions};';
const {command,evaluate,socket,exceptions} = await import('data:text/javascript;base64,' + Buffer.from(setup).toString('base64'));
const wait = duration => new Promise(resolve => setTimeout(resolve, duration));
const scenarios = [
    ...['all','active','inactive','pending','rejected'].map(category => ({name:'consumers-'+category,file:'account-layout-'+category+'.html'})),
    ...['dashboard','staff','billing','payments','loans','incidents','reports','reports-billing','meter-readings','announcements','dss-loans','dss-incidents','consumers-applications','consumers-reschedules','consumers-membership'].map(name => ({name,file:'admin-scroll-'+name+'.html'})),
];
const results = [];
await command('Emulation.setDeviceMetricsOverride', {width:1440,height:1000,deviceScaleFactor:1,mobile:false});
for (const scenario of scenarios) {
    await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/'+scenario.file});
    await wait(800);
    await evaluate('(async()=>{await document.fonts.ready;return true})()');
    const checks = [];
    for (const position of [0,200,500,100000]) {
        await evaluate(`document.querySelector('.admin-records-area').scrollTop=${position}`);
        await wait(100);
        checks.push(await evaluate(`(() => {
            const area=document.querySelector('.admin-records-area');
            const areaBox=area.getBoundingClientRect();
            const failures=[];
            const tables=[...area.querySelectorAll('table')].filter(table=>!table.closest('.modal'));
            let checked=0;
            for(const table of tables) {
                const heading=table.tHead;
                if(!heading) continue;
                const box=heading.getBoundingClientRect();
                const tableBox=table.getBoundingClientRect();
                let boundary=areaBox.top;
                for(const control of area.querySelectorAll('[data-admin-freeze]')) {
                    const bounds=control.getBoundingClientRect();
                    if((control.compareDocumentPosition(heading)&Node.DOCUMENT_POSITION_FOLLOWING)&&bounds.height>0&&bounds.bottom>areaBox.top&&bounds.left<box.right-1&&bounds.right>box.left+1) boundary=Math.max(boundary,bounds.bottom+(parseFloat(getComputedStyle(control).marginBottom)||0));
                }
                const surface=table.closest('.admin-records-surface');
                if(!surface||getComputedStyle(surface).padding!=='24px') failures.push('surface-spacing');
                if(tableBox.bottom>boundary+box.height+1&&tableBox.top<areaBox.bottom&&boundary+box.height<areaBox.bottom) {
                    checked++;
                    if(box.top<boundary-1) failures.push('header-under-filter: '+box.top+' < '+boundary);
                    const row=table.tBodies[0]?.rows[0];
                    const headerCells=[...heading.rows[0].cells];
                    if(row&&row.cells.length===headerCells.length&&[...row.cells].every(cell=>cell.colSpan===1)) {
                        headerCells.forEach((cell,index)=>{
                            const first=cell.getBoundingClientRect();
                            const second=row.cells[index].getBoundingClientRect();
                            if(Math.abs(first.left-second.left)>1||Math.abs(first.width-second.width)>1) failures.push('column-alignment-'+index);
                        });
                    }
                    const wrapper=table.parentElement;
                    const bounds=wrapper.getBoundingClientRect();
                    const x=Math.max(bounds.left+2,box.left+2);
                    const y=box.top+box.height/2;
                    if(y>=areaBox.top&&y<areaBox.bottom&&x<Math.min(bounds.right,box.right)) {
                        const target=document.elementFromPoint(x,y);
                        if(!heading.contains(target)) failures.push('header-covered: '+target?.className);
                    }
                    if(wrapper.scrollWidth>wrapper.clientWidth+2) {
                        const oldLeft=heading.rows[0].cells[0].getBoundingClientRect().left;
                        wrapper.scrollLeft=80;
                        const moved=oldLeft-heading.rows[0].cells[0].getBoundingClientRect().left;
                        if(Math.abs(moved-wrapper.scrollLeft)>1&&wrapper.scrollLeft!==80) failures.push('horizontal-header');
                        wrapper.scrollLeft=0;
                    }
                }
            }
            return {scroll:area.scrollTop,checked,failures};
        })()`));
    }
    await evaluate("document.querySelector('.admin-records-area').scrollTop=200");
    await wait(100);
    if(['billing','consumers-all','payments','reports-billing','meter-readings'].includes(scenario.name)) {
        const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        await fs.writeFile('tmp/admin-standard-'+scenario.name+'.png',Buffer.from(shot.data,'base64'));
    }
    results.push({name:scenario.name,checks});
    console.log(JSON.stringify(results.at(-1)));
}
await fs.writeFile('tmp/admin-standard-layout-results.json',JSON.stringify({results,exceptions},null,2));
console.log(JSON.stringify({failures:results.filter(result=>result.checks.some(check=>check.failures.length)),exceptions}));
socket.close();
if(results.some(result=>result.checks.some(check=>check.failures.length))||exceptions.length) process.exitCode=1;
