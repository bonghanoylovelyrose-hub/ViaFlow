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
    const fs = require('node:fs');
    const path = require('node:path');
    const message = fs.readFileSync(path.join(__dirname,'received.eml'),'utf8').replace(/=\r?\n/g,'').replace(/=([A-F\d]{2})/g,(_,hex)=>String.fromCharCode(parseInt(hex,16)));
    const link = [...message.matchAll(/href="([^"]+)"/g)].map(x=>x[1].replace(/&amp;/g,'&')).find(url=>url.includes('/installation/reschedule/connection/'));
    assert.ok(link?.startsWith(origin));
    try {
        await send('Page.enable'); await send('Network.enable'); await send('Network.clearBrowserCookies');
        await send('Page.navigate',{url:link});
        await waitFor(`document.body.innerText.includes('MT-OFFICE-WIZARD-QA') || !!document.querySelector('textarea[name=reason]')`);
        assert.doesNotMatch(await run('document.body.innerText'), /no currently scheduled installation date/i);
        console.log('Approval email reschedule link opens the actual consumer request page.');
    } finally {ws.close();}
}
verify().catch(error=>{ console.error(error); process.exitCode=1; });
