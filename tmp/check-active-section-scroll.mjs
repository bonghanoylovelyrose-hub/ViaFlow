import fs from 'node:fs/promises';
const source=await fs.readFile('tmp/check-account-layout.mjs','utf8');
const setup=source.slice(0,source.indexOf('const output = [];'))+'\nexport {command,evaluate,socket,exceptions};';
const {command,evaluate,socket,exceptions}=await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
const wait=duration=>new Promise(resolve=>setTimeout(resolve,duration));
const results=[];
for(const width of [1366,1920,390]) {
    await command('Emulation.setDeviceMetricsOverride',{width,height:width===1920?1080:width===390?844:768,deviceScaleFactor:1,mobile:width===390});
    for(const empty of [false,true]) {
        const file=empty?'admin-scroll-consumers-active-empty-notices.html':'account-layout-active.html';
        await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/'+file});
        await wait(1000);
        await evaluate('(async()=>{await document.fonts.ready;return true})()');
        const start=await evaluate(`(() => {
            const area=document.querySelector('.admin-records-area');
            return document.querySelector('#active-accounts').getBoundingClientRect().top-area.getBoundingClientRect().top;
        })()`);
        const checks=[];
        for(const position of [0,150,Math.max(0,start-180),start+200,100000]) {
            await evaluate(`(() => {const main=document.querySelector('.main-content');const scroller=main.classList.contains('admin-content-scroll')?main.querySelector('.content-wrapper'):main.querySelector('.admin-records-area');scroller.scrollTop=${position};})()`);
            await wait(100);
            checks.push(await evaluate(`(() => {
                const main=document.querySelector('.main-content');
                const area=document.querySelector('.admin-records-area');
                const fallback=main.classList.contains('admin-content-scroll');
                const box=area.getBoundingClientRect();
                const failures=[];
                const notice=document.querySelector('#active-disconnection-notices > [data-admin-freeze]');
                const active=document.querySelector('#active-accounts > [data-admin-freeze]');
                const first=notice.getBoundingClientRect();
                const second=active.getBoundingClientRect();
                if(!fallback&&first.bottom>box.top&&second.top<box.bottom&&Math.min(first.bottom,second.bottom)-Math.max(first.top,second.top)>1) failures.push('overlapping-section-controls');
                if(!fallback&&Math.abs(second.top-box.top-parseFloat(active.style.getPropertyValue('--admin-freeze-top')))<1&&first.bottom>box.top+1) failures.push('notice-controls-did-not-release');
                for(const control of document.querySelectorAll('#notice-filters input:not([type="hidden"]),#notice-filters select,#account-filters input:not([type="hidden"]),#account-filters select')) {
                    const rect=control.getBoundingClientRect();
                    if(rect.top<Math.max(box.top,0)||rect.bottom>innerHeight) continue;
                    const x=rect.left+rect.width/2,y=rect.top+rect.height/2;
                    if(x<0||x>=innerWidth) continue;
                    const target=document.elementFromPoint(x,y);
                    if(target!==control&&!control.contains(target)) failures.push('covered-control-'+control.id);
                }
                const nested=[...area.querySelectorAll('*')].filter(element=>!element.matches('textarea,select')&&!element.closest('.modal,.dropdown-menu')).filter(element=>['auto','scroll'].includes(getComputedStyle(element).overflowY)&&element.scrollHeight>element.clientHeight+2);
                if(nested.length) failures.push('nested-scrollbar');
                if(document.querySelectorAll('#notice-filters').length!==1||document.querySelectorAll('#account-filters').length!==1) failures.push('filter-structure');
                if(document.querySelector('#notice-filters').closest('#active-accounts')||document.querySelector('#account-filters').closest('#active-disconnection-notices')) failures.push('mixed-sections');
                return {scroll:fallback?main.querySelector('.content-wrapper').scrollTop:area.scrollTop,fallback,noticeBottom:first.bottom,activeTop:second.top,failures};
            })()`));
        }
        if(!empty) {
            const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
            await fs.writeFile('tmp/active-section-fixed-'+width+'.png',Buffer.from(shot.data,'base64'));
        }
        results.push({width,empty,checks});
        console.log(JSON.stringify(results.at(-1)));
    }
}
console.log(JSON.stringify({exceptions}));
await fs.writeFile('tmp/active-section-scroll-results.json',JSON.stringify({results,exceptions},null,2));
socket.close();
if(exceptions.length||results.some(result=>result.checks.some(check=>check.failures.length))) process.exitCode=1;
