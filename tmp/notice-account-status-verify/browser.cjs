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
    const trigger = `document.querySelector('#disconnection-records button[data-bs-target^="#disconnectionNoticeDetails-"]')`;
    const labels = ['Disconnection status','Disconnection reason','Outstanding balance','Notice date','Payment deadline'];

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const {name, value} = require('./session.json');
        assert.ok((await send('Network.setCookie', {name, value, url:`${origin}/`, httpOnly:true})).success);
        await send('Page.navigate', {url:`${origin}/consumers?account_category=active&notice_search=MT-DETAILS-QA`});
        await waitFor(`${trigger} && window.bootstrap?.Modal`);
        const selector = await run(`${trigger}.dataset.bsTarget`);
        for (const [width, mobile] of [[1366, false], [390, true]]) {
            await send('Emulation.setDeviceMetricsOverride', {width, height:850, deviceScaleFactor:1, mobile});
            await run(`${trigger}.click()`);
            await waitFor(`document.activeElement===document.querySelector('${selector}.show')`);
            const details = await run(`(() => {
                const modal = document.querySelector('${selector}');
                const entries = [...modal.querySelectorAll('dl dt')];
                const fields = entries.map(entry=>entry.parentElement.getBoundingClientRect().toJSON());
                return {fields, labels:entries.map(entry=>entry.textContent.trim()), inputs:modal.querySelectorAll('input,select').length, text:modal.innerText, width:modal.querySelector('.modal-dialog').getBoundingClientRect().right, viewport:innerWidth};
            })()`);
            assert.deepEqual(details.labels, labels);
            assert.equal(details.inputs, 0);
            assert.doesNotMatch(details.text, /Account status/i);
            for (const phrase of ['MT-DETAILS-QA','Water Billing Overdue','Reschedule attempts','Notice history','Reconnection history']) assert.ok(details.text.includes(phrase), phrase);
            assert.match(details.text, /Ready for Disconnection|Within Grace Period/);
            assert.ok(details.width <= details.viewport);
            if (mobile) assert.ok(details.fields.every((field, i) => i === 0 || field.top > details.fields[i-1].top));
            else assert.ok(details.fields[0].width > details.fields[1].width && details.fields[1].top === details.fields[2].top && details.fields[3].top === details.fields[4].top);
            await run(`document.querySelector('${selector} .modal-footer button').click()`);
            await waitFor(`!document.querySelector('${selector}.show') && !document.querySelector('.modal-backdrop')`);
            console.log(`${width}px: View Details shows the notice fields and histories without the Account Status field.`);
        }
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

