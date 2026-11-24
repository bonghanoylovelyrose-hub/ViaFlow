import fs from 'node:fs/promises';

const targets = await (await fetch('http://127.0.0.1:9241/json/list')).json();
const target = targets.find(entry => entry.type === 'page');
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
let sequence = 0;
const pending = new Map();
const exceptions = [];
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
    if (message.id && pending.has(message.id)) {
        const request = pending.get(message.id);
        pending.delete(message.id);
        if (message.error) request.reject(new Error(JSON.stringify(message.error)));
        else request.resolve(message.result);
    }
});
function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const identifier = ++sequence;
        pending.set(identifier, { resolve, reject });
        socket.send(JSON.stringify({ id: identifier, method, params }));
    });
}
async function evaluate(expression) {
    const response = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (response.exceptionDetails) throw new Error(JSON.stringify(response.exceptionDetails));
    return response.result.value;
}
await command('Page.enable');
await command('Runtime.enable');
if (process.env.ACCOUNT_UI_CLOSE) {
    await command('Browser.close');
    socket.close();
    process.exit(0);
}
const output = [];
if (process.env.ACCOUNT_UI_INSPECT) {
    await command('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
    await command('Page.navigate', {url: 'file:///C:/xampp/htdocs/waterbillingsystems/tmp/account-layout-active.html'});
    await new Promise(resolve => setTimeout(resolve, 1800));
    await evaluate("document.head.insertAdjacentHTML('beforeend', '<style>html, body {overflow:hidden;} .account-table-scroll {contain:layout paint inline-size;} .account-list {overflow:hidden;}</style>')");
    await new Promise(resolve => setTimeout(resolve, 250));
    console.log(JSON.stringify(await evaluate(`(() => {
        const selectors = ['html','body','.app-shell','.main-content','.content-wrapper','.consumer-accounts-panel','#active-disconnection-notices','#active-accounts','.account-table-scroll'];
        return {
            viewport: [innerWidth,innerHeight,document.documentElement.clientWidth],
            boxes: selectors.map(selector => [...document.querySelectorAll(selector)].map(element => {
                const box=element.getBoundingClientRect(); const css=getComputedStyle(element);
                return {selector,width:box.width,right:box.right,top:box.top,height:box.height,scrollWidth:element.scrollWidth,display:css.display,overflow:css.overflow,minWidth:css.minWidth};
            })).flat(),
            overflow: [...document.body.querySelectorAll('*')].filter(element => !element.closest('.account-table-scroll, .modal, .sidebar') && element.getBoundingClientRect().right > 410).map(element => ({tag:element.tagName,class:element.className,width:element.getBoundingClientRect().width,right:element.getBoundingClientRect().right,text:element.textContent.trim().slice(0,80)})).slice(0,25)
        };
    })()`),null,2));
    const capture=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
    await fs.writeFile('tmp/account-layout-mobile-debug.png',Buffer.from(capture.data,'base64'));
    socket.close();
    process.exit(0);
}
for (const scenario of [
    { category: 'all', width: 1440, height: 1000 },
    { category: 'active', width: 1440, height: 1000 },
    { category: 'inactive', width: 1366, height: 768 },
    { category: 'pending', width: 1440, height: 1000 },
    { category: 'rejected', width: 1440, height: 1000 },
    { category: 'active', width: 390, height: 844 },
]) {
    await command('Emulation.setDeviceMetricsOverride', { width: scenario.width, height: scenario.height, deviceScaleFactor: 1, mobile: scenario.width < 600 });
    await command('Page.navigate', { url: 'file:///C:/xampp/htdocs/waterbillingsystems/tmp/account-layout-'+scenario.category+'.html' });
    await new Promise(resolve => setTimeout(resolve, 1600));
    const metrics = await evaluate(`(() => {
        const containers = [...document.querySelectorAll('.account-table-scroll')];
        return {
            bootstrap: typeof bootstrap !== 'undefined',
            viewport: [innerWidth, innerHeight],
            bodyWidth: document.documentElement.scrollWidth,
            rows: [...document.querySelectorAll('tbody')].map(body => body.rows.length),
            filters: [...document.querySelectorAll('form[data-auto-filter]')].map(form => Math.round(form.getBoundingClientRect().top)),
            tables: containers.map(container => {
                const header = container.querySelector('thead th');
                const before = header.getBoundingClientRect().top;
                container.scrollTop = 180;
                return { height: container.clientHeight, scrollHeight: container.scrollHeight, headerShift: header.getBoundingClientRect().top-before };
            }),
            duplicateIds: [...document.querySelectorAll('[id]')].map(element => element.id).filter((identifier, index, identifiers) => identifiers.indexOf(identifier) !== index),
            rowHasSecondaryDetails: [...document.querySelectorAll('tbody tr')].some(row => row.textContent.includes('@example.test') || row.textContent.includes('Via Vida Street')),
            pagination: [...document.querySelectorAll('.account-pagination')].map(element => element.textContent.trim().replace(/\\s+/g, ' ')),
        };
    })()`);
    if (!metrics.bootstrap) throw new Error('Bootstrap did not load');
    if (metrics.viewport[0] !== scenario.width || metrics.bodyWidth > scenario.width) throw new Error('Page overflow: '+JSON.stringify(metrics));
    if (metrics.duplicateIds.length || metrics.rowHasSecondaryDetails) throw new Error(JSON.stringify(metrics));
    if (metrics.tables.some(table => Math.abs(table.headerShift) > 1)) throw new Error('Table header moved: '+JSON.stringify(metrics));
    await evaluate("document.querySelector('button[data-bs-target^=\"#consumerDetails-\"]').click()");
    await new Promise(resolve => setTimeout(resolve, 600));
    const modal = await evaluate(`(() => {
        const modal = document.querySelector('.modal.show');
        return { visible: !!modal, email: modal?.textContent.includes('@example.test'), address: modal?.textContent.includes('Via Vida Street'), scrollable: modal?.querySelector('.modal-dialog').classList.contains('modal-dialog-scrollable') };
    })()`);
    if (!modal.visible || !modal.email || !modal.address || !modal.scrollable) throw new Error('Details modal failed: '+JSON.stringify(modal));
    if (scenario.category === 'inactive') {
        const capture = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        await fs.writeFile('tmp/account-details-preview.png', Buffer.from(capture.data, 'base64'));
    }
    await evaluate("document.querySelector('.modal.show .btn-close').click()");
    await new Promise(resolve => setTimeout(resolve, 600));
    for (const modalId of ['consumerEditModal', 'consumerMeterModal', 'consumerActivateModal', 'consumerReconnectModal']) {
        const clicked = await evaluate(`(() => {
            const button = document.querySelector('tbody button[data-bs-target="#${modalId}"]:not(:disabled)');
            if (!button) return false;
            button.click();
            return true;
        })()`);
        if (!clicked) continue;
        await new Promise(resolve => setTimeout(resolve, 600));
        const opened = await evaluate(`document.getElementById('${modalId}').classList.contains('show')`);
        if (!opened) throw new Error(modalId+' did not open');
        await evaluate(`document.querySelector('#${modalId} .btn-close').click()`);
        await new Promise(resolve => setTimeout(resolve, 600));
    }
    const openModals = await evaluate("document.querySelectorAll('.modal.show').length");
    if (openModals) throw new Error('Modal close did not complete');
    await evaluate("document.querySelectorAll('.account-table-scroll').forEach(container => { container.scrollTop = 0; container.scrollLeft = 0; })");
    const capture = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await fs.writeFile('tmp/account-layout-'+scenario.category+'-'+scenario.width+'.png', Buffer.from(capture.data, 'base64'));
    output.push({ ...scenario, ...metrics, detailsModal: modal });
}
await fs.writeFile('tmp/account-layout-browser-results.json', JSON.stringify({ scenarios: output, exceptions }, null, 2));
console.log(JSON.stringify({ scenarios: output, exceptions }, null, 2));
socket.close();
if (exceptions.length) process.exitCode = 1;
