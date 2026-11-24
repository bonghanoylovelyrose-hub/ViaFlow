const assert = require('node:assert/strict');

async function verify() {
    const origin = 'http://127.0.0.1:8027';
    const tab = (await (await fetch('http://127.0.0.1:9227/json/list')).json()).find(item => item.type === 'page');
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
    const form = 'document.getElementById("account-filters")';
    const clear = `${form}.querySelector('[aria-label="Clear all consumer filters"]')`;
    const checkPosition = async () => {
        const geometry = await run(`(() => {
            const select = document.getElementById('membership').getBoundingClientRect();
            const clear = ${clear}.getBoundingClientRect();
            return {gap:clear.left-select.right, y:Math.abs((clear.top+clear.height/2)-(select.top+select.height/2)),right:clear.right,width:innerWidth};
        })()`);
        assert.ok(geometry.gap >= 0 && geometry.gap <= 16 && geometry.y < 2 && geometry.right <= geometry.width);
    };
    const reset = async () => {
        await run(`${clear}.click()`);
        await waitFor(`location.pathname==='/consumers' && !location.search && ${clear}`);
        assert.equal(await run('document.getElementById("search").value'), '');
        assert.equal(await run('document.getElementById("membership").value'), '');
        assert.equal(await run(`!!${form}.querySelector('[name="membership_verification"]')`), false);
    };

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const { name, value } = require('./session.json');
        assert.ok((await send('Network.setCookie', { name, value, url: `${origin}/`, httpOnly: true })).success);
        await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
        await send('Page.navigate', { url: `${origin}/consumers?search=MT-8181&membership=member&membership_verification=verified&page=2` });
        await waitFor(`${clear} && location.search.includes('page=2')`);
        assert.deepEqual(await run(`Array.from(${form}.querySelectorAll('button,a')).map(el=>el.textContent.trim()).filter(text=>/^clear/i.test(text))`), ['Clear']);
        assert.equal(await run('document.getElementById("search").value'), 'MT-8181');
        assert.equal(await run('document.getElementById("membership").value'), 'member');
        assert.equal(await run(`${form}.querySelector('[name="membership_verification"]').value`), 'verified');
        await checkPosition();
        await reset();
        assert.ok(await run('document.querySelectorAll("#consumer-accounts-table tbody tr").length > 1'));

        await run(`{ const field = document.getElementById('membership'); field.value='member'; field.dispatchEvent(new Event('change',{bubbles:true})); }`);
        await waitFor("new URLSearchParams(location.search).get('membership')==='member'");
        await run(`{ const field = document.getElementById('search'); field.value='MT-8181'; field.dispatchEvent(new Event('input',{bubbles:true})); }`);
        await waitFor("new URLSearchParams(location.search).get('search')==='MT-8181'");
        assert.equal(await run('document.getElementById("membership").value'), 'member');
        assert.deepEqual(await run("Array.from(document.querySelectorAll('#consumer-accounts-table tbody tr')).map(row=>row.cells[2].innerText.trim())"), ['MT-8181']);

        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 820, deviceScaleFactor: 1, mobile: true });
        await waitFor('window.visualViewport.width <= 400');
        await checkPosition();
        await reset();
        console.log('Live All Consumers showed one Clear beside Membership. Combined filtering worked, and Clear restored all defaults and page one at desktop and narrow widths.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
