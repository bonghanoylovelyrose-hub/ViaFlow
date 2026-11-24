import fs from 'node:fs/promises';

const toolkitSource = await fs.readFile('tmp/check-account-layout.mjs', 'utf8');
const toolkit = toolkitSource.slice(0, toolkitSource.indexOf('const output = [];'))+'\nexport { command, evaluate, socket, exceptions };';
const { command, evaluate, socket, exceptions } = await import('data:text/javascript;base64,'+Buffer.from(toolkit).toString('base64'));
const wait = duration => new Promise(resolve => setTimeout(resolve, duration));
const results = [];

function snapshot() {
    const controls = [...document.querySelectorAll('.topbar, .page-heading, #notice-filters, #account-filters')];
    const tables = [...document.querySelectorAll('.main-content table')].filter(table => table.tHead && table.tBodies.length && !table.closest('.modal'));
    const rectangle = element => {
        const box = element.getBoundingClientRect();
        return [box.x, box.y, box.width, box.height].map(value => Math.round(value * 100)/100);
    };
    return {
        viewport: [innerWidth, innerHeight],
        controls: controls.map(element => ({text: element.textContent.trim().slice(0,50), rectangle: rectangle(element)})),
        tables: tables.map(table => {
            const cell = table.tBodies[0].rows[0]?.cells[0];
            const styles = cell ? getComputedStyle(cell) : null;
            const wrapper = table.parentElement;
            return {
                headings: [...table.tHead.querySelectorAll('th')].map(header => header.textContent.trim()),
                tableLayout: getComputedStyle(table).tableLayout,
                firstRowHeight: table.tBodies[0].rows[0]?.getBoundingClientRect().height,
                font: styles?.font,
                padding: styles?.padding,
                border: getComputedStyle(wrapper).border,
                radius: getComputedStyle(wrapper).borderRadius,
                height: wrapper.clientHeight,
                scrollHeight: wrapper.scrollHeight,
                maximumHeight: wrapper.style.maxHeight,
                scrollbar: wrapper.scrollHeight > wrapper.clientHeight,
                managed: wrapper.classList.contains('admin-records-scroll'),
            };
        }),
    };
}

async function navigate(path) {
    await command('Page.navigate', {url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/'+path});
    await wait(1200);
    await evaluate('(async () => { await document.fonts.ready; return true; })()');
    await wait(100);
}

for (const category of ['all', 'active', 'inactive', 'pending', 'rejected']) {
    const html = await fs.readFile('tmp/account-layout-'+category+'.html', 'utf8');
    const baseline = html.replace(/<script>[\s\S]*?<\/script>/g, script => script.includes("wrapper.classList.add('admin-records-scroll')") ? '' : script);
    await fs.writeFile('tmp/account-original-'+category+'.html', baseline);
    await command('Emulation.setDeviceMetricsOverride', {width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await navigate('account-original-'+category+'.html');
    const original = await evaluate('('+snapshot.toString()+')()');
    await navigate('account-layout-'+category+'.html');
    const actual = await evaluate('('+snapshot.toString()+')()');
    if (original.tables.length !== actual.tables.length) throw new Error('Table count changed');
    actual.tables.forEach((table, index) => {
        const before = original.tables[index];
        for (const key of ['headings','font','padding','tableLayout','border','radius','firstRowHeight']) {
            if (JSON.stringify(before[key]) !== JSON.stringify(table[key])) throw new Error(category+' changed '+key+': '+JSON.stringify([before[key],table[key]]));
        }
        if (!table.managed) throw new Error('Table is not managed');
    });
    if (JSON.stringify(original.controls.slice(0,3)) !== JSON.stringify(actual.controls.slice(0,3))) throw new Error('Page controls changed');
    const scroll = await evaluate(`(() => {
        const wrapper = document.querySelector('.admin-records-scroll');
        const header = wrapper.querySelector('thead');
        const record = wrapper.querySelector('tbody tr');
        const before = {header:header.getBoundingClientRect().top, record:record.getBoundingClientRect().top, controls:[...document.querySelectorAll('.topbar,.page-heading,form[data-auto-filter]')].map(element=>element.getBoundingClientRect().top)};
        wrapper.scrollTop=120;
        return {distance:wrapper.scrollTop,headerShift:header.getBoundingClientRect().top-before.header,recordShift:record.getBoundingClientRect().top-before.record,controlShifts:[...document.querySelectorAll('.topbar,.page-heading,form[data-auto-filter]')].map((element,index)=>element.getBoundingClientRect().top-before.controls[index]),headersAligned:[...wrapper.querySelectorAll('thead th')].every((element,index)=>Math.abs(element.getBoundingClientRect().left-record.cells[index].getBoundingClientRect().left)<1)};
    })()`);
    if (scroll.distance === 0 || Math.abs(scroll.headerShift) > 1 || scroll.recordShift !== -scroll.distance || scroll.controlShifts.some(shift=>Math.abs(shift)>1) || !scroll.headersAligned) throw new Error('Scrolling failed: '+category+' '+JSON.stringify(scroll));
    if (category === 'active') {
        await evaluate("document.querySelector('#active-accounts').scrollIntoView({block:'start'})");
        await wait(150);
        const reachable = await evaluate("document.querySelector('#active-accounts').getBoundingClientRect().top < innerHeight");
        if (!reachable) throw new Error('Second account table is not reachable');
        await evaluate('window.scrollTo(0,0)');
    }
    await evaluate("document.querySelectorAll('.admin-records-scroll').forEach(wrapper=>{wrapper.scrollTop=0;wrapper.scrollLeft=0})");
    const screenshot = await command('Page.captureScreenshot', {format:'png',captureBeyondViewport:false});
    await fs.writeFile('tmp/account-original-scroll-'+category+'.png',Buffer.from(screenshot.data,'base64'));
    results.push({category,originalDesignPreserved:true,tables:actual.tables,scroll});
}

for (const module of ['payments','billing','staff','incidents','loans']) {
    await navigate('admin-scroll-'+module+'.html');
    const checked = await evaluate(`({module:'${module}',tables:[...document.querySelectorAll('.main-content table')].filter(table=>table.tHead&&!table.closest('.modal')).map(table=>({managed:table.parentElement.classList.contains('admin-records-scroll'),layout:getComputedStyle(table).tableLayout}))})`);
    if (checked.tables.some(table=>!table.managed)) throw new Error('Admin table missed: '+JSON.stringify(checked));
    results.push(checked);
}

await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
await navigate('account-layout-inactive.html');
const mobile = await evaluate('('+snapshot.toString()+')()');
const mobileScroll = await evaluate(`(() => {
    const wrapper=document.querySelector('.admin-records-scroll');
    const header=wrapper.querySelector('thead');
    const top=header.getBoundingClientRect().top;
    const page=scrollY;
    wrapper.scrollTop=100;
    return {distance:wrapper.scrollTop,headerShift:header.getBoundingClientRect().top-top,pageShift:scrollY-page};
})()`);
if (mobileScroll.distance===0 || Math.abs(mobileScroll.headerShift)>1 || mobileScroll.pageShift!==0) throw new Error('Mobile record scrolling failed: '+JSON.stringify(mobileScroll));
const mobileScreenshot = await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
await fs.writeFile('tmp/account-original-scroll-mobile.png',Buffer.from(mobileScreenshot.data,'base64'));
await fs.writeFile('tmp/original-table-scroll-results.json',JSON.stringify({results,mobile,mobileScroll,exceptions},null,2));
console.log(JSON.stringify({categories:results.map(result=>({category:result.category??result.module,designPreserved:result.originalDesignPreserved,scroll:result.scroll,tables:result.tables.length})),exceptions},null,2));
socket.close();
if (exceptions.length) process.exitCode=1;
