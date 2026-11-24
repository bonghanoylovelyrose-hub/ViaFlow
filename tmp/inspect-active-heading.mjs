import fs from 'node:fs/promises';
const source=await fs.readFile('tmp/check-account-layout.mjs','utf8');
const setup=source.slice(0,source.indexOf('const output = [];'))+'\nexport {command,evaluate,socket};';
const {command,evaluate,socket}=await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
await command('Emulation.setDeviceMetricsOverride',{width:1366,height:768,deviceScaleFactor:1,mobile:false});
await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/account-layout-active.html'});
await new Promise(resolve=>setTimeout(resolve,2500));
await evaluate('document.querySelector(".admin-records-area").scrollTop=100000');
await new Promise(resolve=>setTimeout(resolve,200));
console.log(JSON.stringify(await evaluate(`(() => {
    return ['#active-accounts','#active-accounts > [data-admin-freeze]','#account-filters','#consumer-accounts-table','#consumer-accounts-table thead','#consumer-accounts-table tbody','#consumer-accounts-table tbody tr:last-child'].map(selector => {
        const element=document.querySelector(selector),box=element.getBoundingClientRect();
        return {selector,top:box.top,bottom:box.bottom,height:box.height,shift:element.style.getPropertyValue('--admin-table-header-shift'),clip:getComputedStyle(element).clipPath,parent:element.parentElement.className,parentClip:getComputedStyle(element.parentElement).clipPath};
    });
})()`),null,2));
console.log(await evaluate(`({url:location.href,main:document.querySelector('.main-content').className,html:document.documentElement.className,freezes:[...document.querySelectorAll('[data-admin-freeze]')].map(element=>({style:element.getAttribute('style'),height:element.offsetHeight})),area:document.querySelector('.admin-records-area').outerHTML.slice(0,400)})`));
socket.close();
