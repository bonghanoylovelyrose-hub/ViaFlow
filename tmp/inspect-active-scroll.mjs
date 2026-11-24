import fs from 'node:fs/promises';
const source=await fs.readFile('tmp/check-account-layout.mjs','utf8');
const setup=source.slice(0,source.indexOf('const output = [];'))+'\nexport {command,evaluate,socket,exceptions};';
const {command,evaluate,socket,exceptions}=await import('data:text/javascript;base64,'+Buffer.from(setup).toString('base64'));
const wait=duration=>new Promise(resolve=>setTimeout(resolve,duration));
await command('Emulation.setDeviceMetricsOverride',{width:1366,height:768,deviceScaleFactor:1,mobile:false});
await command('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/account-layout-active.html'});
await wait(1000);
for(const position of [0,300,2500,6000,100000]) {
    await evaluate(`document.querySelector('.admin-records-area').scrollTop=${position}`);
    await wait(120);
    console.log(JSON.stringify(await evaluate(`(() => {
        const area=document.querySelector('.admin-records-area');
        return {scroll:area.scrollTop,height:area.clientHeight,total:area.scrollHeight,
            boxes:[...document.querySelectorAll('[data-admin-freeze],thead,#active-accounts')].map(element=>{const b=element.getBoundingClientRect();return {name:element.id||element.textContent.trim().slice(0,40),top:b.top,bottom:b.bottom,shift:element.style.getPropertyValue('--admin-extra-shift')}})};
    })()`)));
    const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
    await fs.writeFile('tmp/active-scroll-'+position+'.png',Buffer.from(shot.data,'base64'));
}
socket.close();
