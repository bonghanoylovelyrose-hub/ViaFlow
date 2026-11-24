import fs from 'node:fs/promises';

const source = await fs.readFile('tmp/check-account-layout.mjs', 'utf8');
const setup = source.slice(0, source.indexOf('const output = [];'))+'\nexport { command, evaluate, socket, exceptions };';
const { command, evaluate, socket, exceptions } = await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
const wait = duration => new Promise(resolve => setTimeout(resolve, duration));
await command('Emulation.setDeviceMetricsOverride', {width:1440,height:1000,deviceScaleFactor:1,mobile:false});
await command('Page.navigate', {url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/admin-scroll-billing.html'});
await wait(1200);
await evaluate('(async () => { await document.fonts.ready; return true; })()');
const billing = await evaluate(`(() => {
    const table=document.querySelector('.billing-table');
    const wrapper=table.parentElement;
    const box=wrapper.getBoundingClientRect();
    return {
        rows:table.tBodies[0].rows.length,
        maximumHeight:getComputedStyle(wrapper).maxHeight,
        inlineHeight:wrapper.style.height,
        inlineMaximumHeight:wrapper.style.maxHeight,
        managed:wrapper.classList.contains('admin-records-scroll'),
        clientHeight:wrapper.clientHeight,
        scrollHeight:wrapper.scrollHeight,
        allRowsInsideTable:[...table.tBodies[0].rows].every(row=>row.getBoundingClientRect().bottom<=box.bottom+1),
        horizontalOverflow:wrapper.scrollWidth>wrapper.clientWidth,
        horizontalScrolling:getComputedStyle(wrapper).overflowX,
        filters:!!document.querySelector('.billing-filter'),
        pagination:!!document.querySelector('.pagination'),
    };
})()`);
if (billing.rows!==10 || billing.maximumHeight!=='none' || billing.inlineHeight || billing.inlineMaximumHeight || billing.managed || billing.scrollHeight>billing.clientHeight+1 || !billing.allRowsInsideTable || !billing.filters || !billing.pagination) throw new Error(JSON.stringify(billing));
await evaluate("document.querySelector('.billing-table').parentElement.scrollIntoView({block:'start'})");
await wait(150);
const start = await evaluate('window.scrollY');
await command('Input.dispatchMouseEvent',{type:'mouseWheel',x:700,y:180,deltaX:0,deltaY:300});
await wait(400);
const scrolling = await evaluate(`({page:window.scrollY,table:document.querySelector('.billing-table').parentElement.scrollTop})`);
if (scrolling.page<=start || scrolling.table!==0) throw new Error('Mouse wheel did not scroll the page: '+JSON.stringify(scrolling));
const screenshot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
await fs.writeFile('tmp/billing-page-scroll-preview.png',Buffer.from(screenshot.data,'base64'));
await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/account-layout-all.html'});
await wait(1200);
const otherTable = await evaluate(`(() => {
    const wrapper=document.querySelector('#consumer-accounts-table').parentElement;
    return {managed:wrapper.classList.contains('admin-records-scroll'),maximumHeight:wrapper.style.maxHeight};
})()`);
if (!otherTable.managed || !otherTable.maximumHeight) throw new Error('Other account scrolling changed');
console.log(JSON.stringify({billing,pageScrollDistance:scrolling.page-start,internalScrollDistance:scrolling.table,otherTable,exceptions},null,2));
await command('Browser.close');
socket.close();
if (exceptions.length) process.exitCode=1;
