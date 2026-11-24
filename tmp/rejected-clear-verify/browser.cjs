const assert = require('node:assert/strict');

async function verify() {
    const origin = 'http://127.0.0.1:8027';
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
    const form = 'document.getElementById("account-filters")';
    const clear = `${form}.querySelector('[aria-label="Clear all consumer filters"]')`;
    const category = 'new URLSearchParams(location.search).get("account_category") === "rejected"';
    const search = 'document.getElementById("search")';
    const membership = 'document.getElementById("membership")';

    async function checkLayout(isDesktop) {
        const details = await run(`(() => {
            const filters = ${form};
            const search = ${search}.getBoundingClientRect();
            const membership = ${membership}.getBoundingClientRect();
            const clear = ${clear}.getBoundingClientRect();
            return {labels: [...filters.querySelectorAll('button,a')].map(control=>control.innerText.trim()).filter(text=>/^clear/i.test(text)),
                besideMembership: ${membership}.parentElement.contains(${clear}), header: document.querySelector('#consumerSubmenu [data-consumer-category=rejected][aria-current=page]').textContent.trim(),
                gap:clear.left-membership.right, centered:Math.abs((clear.top+clear.height/2)-(membership.top+membership.height/2)),
                right:clear.right, width:innerWidth, searchBottom:search.bottom, membershipBottom:membership.bottom};
        })()`);
        assert.deepEqual(details.labels, ['Clear']);
        assert.equal(details.header, 'Rejected Accounts');
        assert.ok(details.besideMembership && details.gap >= 0 && details.gap <= 16 && details.centered < 2 && details.right <= details.width, JSON.stringify(details));
        assert.ok(isDesktop ? Math.abs(details.searchBottom-details.membershipBottom) < 2 : details.searchBottom < details.membershipBottom, JSON.stringify(details));
    }

    async function reset() {
        assert.equal(await run(`${search}.value`), 'unmatched-clear-check');
        assert.equal(await run(`${membership}.value`), 'member');
        await run(`${clear}.click()`);
        await waitFor(`${clear} && ${category} && location.search==='?account_category=rejected' && !${search}.value && !${membership}.value`);
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const {name, value} = require('./session.json');
        assert.ok((await send('Network.setCookie', {name, value, url:`${origin}/`, httpOnly:true})).success);
        await send('Emulation.setDeviceMetricsOverride', {width:1366, height:900, deviceScaleFactor:1, mobile:false});
        await send('Page.navigate', {url:`${origin}/consumers?account_category=rejected&search=unmatched-clear-check&membership=member&page=2`});
        await waitFor(`${clear} && ${category} && location.search.includes('page=2')`);
        await checkLayout(true);
        await reset();
        await checkLayout(true);
        console.log('Desktop Rejected Accounts shows one Clear beside Membership and resets Search, Membership, and pagination.');

        await send('Emulation.setDeviceMetricsOverride', {width:390, height:820, deviceScaleFactor:1, mobile:true});
        await waitFor('window.visualViewport.width <= 400');
        await run(`{ const field = ${membership}; field.value='member'; field.dispatchEvent(new Event('change', {bubbles:true})); }`);
        await waitFor(`${clear} && ${category} && location.search.includes('membership=member')`);
        await run(`{ const field = ${search}; field.value='unmatched-clear-check'; field.dispatchEvent(new Event('input', {bubbles:true})); }`);
        await waitFor(`${clear} && ${category} && location.search.includes('search=unmatched-clear-check')`);
        await checkLayout(false);
        await reset();
        await checkLayout(false);
        console.log('Search and Membership work together, and Clear stays aligned and resets both on the 390px layout.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

