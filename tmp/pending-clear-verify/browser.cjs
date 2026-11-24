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
    const clear = `${form}.querySelector('[data-pending-clear]')`;
    const selected = 'document.querySelector("#pendingTypeFilters [aria-current=page]").dataset.pendingType';
    const query = 'new URLSearchParams(location.search)';
    const tableReady = '!document.querySelector("#consumer-accounts-table").hasAttribute("aria-busy")';
    const searchValue = 'document.getElementById("search").value';
    const membershipValue = 'document.getElementById("membership").value';

    async function checkClear(type) {
        assert.deepEqual(await run(`Array.from(${form}.querySelectorAll('button,a')).map(el=>el.textContent.trim()).filter(text=>/^clear/i.test(text))`), ['Clear']);
        assert.ok(await run('document.querySelector("#membership ~ [data-pending-clear]") !== null'));
        const href = new URL(await run(`${clear}.href`));
        assert.deepEqual(Object.fromEntries(href.searchParams), { account_category: 'pending', pending_type: type });
        const geometry = await run(`(() => {
            const select = document.getElementById('membership').getBoundingClientRect();
            const button = ${clear}.getBoundingClientRect();
            return {gap:button.left-select.right, y:Math.abs((button.top+button.height/2)-(select.top+select.height/2)),right:button.right,width:innerWidth};
        })()`);
        assert.ok(geometry.gap >= 0 && geometry.gap <= 16 && geometry.y < 2 && geometry.right <= geometry.width, JSON.stringify(geometry));
    }

    async function reset(type) {
        await run(`${clear}.click()`);
        await waitFor(`${clear} && ${query}.get('pending_type')===${JSON.stringify(type)} && !${query}.has('search') && !${query}.has('membership') && !${query}.has('page')`);
        assert.equal(await run(searchValue), '');
        assert.equal(await run(membershipValue), '');
        assert.equal(await run(selected), type);
        await checkClear(type);
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const { name, value } = require('./session.json');
        assert.ok((await send('Network.setCookie', { name, value, url: `${origin}/`, httpOnly: true })).success);
        await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
        for (const [type, label] of [['all', 'All Pending'], ['applications', 'Connection Applications'], ['reschedules', 'Reschedule Requests']]) {
            await send('Page.navigate', { url: `${origin}/consumers?account_category=pending&pending_type=all&search=MT-8181&membership=member&page=2` });
            await waitFor(`${clear} && ${query}.get('page')==='2' && ${selected}==='all'`);
            if (type !== 'all') {
                await run(`window.pendingVerificationMarker = true; document.querySelector('#pendingTypeFilters [data-pending-type=${type}]').click()`);
                await waitFor(`${query}.get('pending_type')==='${type}' && ${selected}==='${type}' && ${tableReady}`);
                assert.equal(await run('window.pendingVerificationMarker'), true);
            }
            assert.equal(await run(searchValue), 'MT-8181');
            assert.equal(await run(membershipValue), 'member');
            await checkClear(type);
            await reset(type);
            console.log(`${label}: one aligned Clear resets both filters and preserves the tab.`);
        }

        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 820, deviceScaleFactor: 1, mobile: true });
        await waitFor('window.visualViewport.width <= 400');
        await run(`{ const field = document.getElementById('membership'); field.value='member'; field.dispatchEvent(new Event('change',{bubbles:true})); }`);
        await waitFor(`${query}.get('membership')==='member' && ${selected}==='reschedules'`);
        await run(`{ const field = document.getElementById('search'); field.value='MT-8181'; field.dispatchEvent(new Event('input',{bubbles:true})); }`);
        await waitFor(`${query}.get('search')==='MT-8181' && ${selected}==='reschedules'`);
        assert.equal(await run(membershipValue), 'member');
        await checkClear('reschedules');
        await reset('reschedules');
        console.log('Membership and Search also combine and reset at a 390px viewport.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

