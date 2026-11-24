const assert = require('node:assert/strict');

async function verify() {
    const origin = 'http://127.0.0.1:8032';
    const tab = (await (await fetch('http://127.0.0.1:9228/json/list')).json()).find(item => item.type === 'page');
    const ws = new WebSocket(tab.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    const calls = new Map();
    let nextId = 0;
    ws.addEventListener('message', ({ data }) => {
        const reply = JSON.parse(data);
        const request = calls.get(reply.id);
        if (!request) return;
        calls.delete(reply.id);
        reply.error ? request.reject(new Error(reply.error.message)) : request.resolve(reply.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        calls.set(++nextId, { resolve, reject });
        ws.send(JSON.stringify({ id: nextId, method, params }));
    });
    const run = async expression => {
        const reply = await send('Runtime.evaluate', { expression, returnByValue: true });
        if (reply.exceptionDetails) throw new Error(reply.result.description);
        return reply.result.value;
    };
    const waitFor = async condition => {
        for (let attempt = 0; attempt < 100; attempt++) {
            try {
                if (await run(`Boolean(document.readyState==='complete' && (${condition}))`)) return;
            } catch (error) {
                if (!/context|navigat/i.test(error.message)) throw error;
            }
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        throw new Error(`Expected consumer filter state at ${await run('location.href')}`);
    };
    const nav = `document.querySelector('nav[aria-label="Filter incident reports by status"]')`;
    const heading = `document.getElementById('incident-reports-title')`;
    const rows = `document.querySelectorAll('.incident-table tbody tr')`;
    const filters = new URLSearchParams({search:'Via Vida Lane',category:'Water Leak'});
    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const {name,value} = require('./tabs-session.json');
        assert.ok((await send('Network.setCookie',{name,value,url:`${origin}/`,httpOnly:true})).success);
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:`${origin}/incidents?${filters}`});
        await waitFor(`${rows}.length===5`);
        const geometry = () => run(`(() => { const box = element => element.getBoundingClientRect().toJSON(); return {title:box(${heading}),tabs:box(${nav}),filters:box(document.querySelector('.incident-filters'))}; })()`);
        const desktop = await geometry();
        assert.ok(Math.abs(desktop.title.top+desktop.title.height/2-desktop.tabs.top-desktop.tabs.height/2)<2);
        assert.ok(desktop.tabs.left-desktop.title.right>=0 && desktop.tabs.left-desktop.title.right<=12);
        assert.ok(desktop.filters.top>desktop.tabs.bottom);
        assert.deepEqual(await run(`[...${nav}.querySelectorAll('a')].map(tab=>tab.textContent.trim())`), ['All','Pending','Resolved']);
        assert.equal(await run(`${nav}.querySelector('[aria-current=page]').innerText.trim()`),'All');
        for (const [status,count] of [['Pending',2],['Resolved',3],['All',5]]) {
            await run(`[...${nav}.querySelectorAll('a')].find(tab=>tab.innerText.trim()==='${status}').click()`);
            await waitFor(`${rows}.length===${count} && ${nav}.querySelector('[aria-current=page]').innerText.trim()==='${status}'`);
            assert.equal(await run(`document.getElementById('incident_filter_search').value`),'Via Vida Lane');
            assert.equal(await run(`document.getElementById('incident_filter_category').value`),'Water Leak');
        }
        await send('Emulation.setDeviceMetricsOverride',{width:320,height:820,deviceScaleFactor:1,mobile:true});
        await waitFor('innerWidth===320');
        const mobile = await geometry();
        assert.ok(Math.abs(mobile.tabs.left-mobile.title.left)<2 && mobile.filters.top>mobile.tabs.bottom);
        console.log('Chrome verified the left-aligned status tabs on desktop and mobile and all three filter transitions with Search and Category retained.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}
verify().catch(error=>{ console.error(error); process.exitCode=1; });
