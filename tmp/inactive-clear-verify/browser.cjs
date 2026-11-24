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
    const filterValues = { inactive: 'member', disconnection_history: 'disconnected' };
    const filters = { inactive: 'membership', disconnection_history: 'history_status' };
    const defaults = { inactive: '', disconnection_history: 'all' };
    const labels = { inactive: 'Inactive Accounts', disconnection_history: 'Disconnection History' };
    const tabs = `document.querySelector('nav[aria-label="Consumer account views"]')`;
    const query = 'new URLSearchParams(location.search)';
    const category = key => `${query}.get('account_category') === '${key}'`;
    const form = key => `document.getElementById('${key === 'inactive' ? 'account-filters' : 'history-filters'}')`;
    const clear = key => `${form(key)}.querySelector('a[aria-label^=Clear]')`;
    const search = key => `${form(key)}.querySelector('[name=search]')`;
    const dropdown = key => `${form(key)}.querySelector('[name=${filters[key]}]')`;

    async function check(key, desktop) {
        const details = await run(`(() => {
            const form = ${form(key)};
            const search = ${search(key)}.getBoundingClientRect();
            const dropdown = ${dropdown(key)}.getBoundingClientRect();
            const clear = ${clear(key)}.getBoundingClientRect();
            return {labels: [...form.querySelectorAll('button,a')].map(control=>control.innerText.trim()).filter(text=>/^clear/i.test(text)),
                selected: ${tabs}.querySelector('[aria-current=page]').innerText.trim(),
                nextToDropdown: ${dropdown(key)}.parentElement.contains(${clear(key)}),
                gap:clear.left-dropdown.right, centered:Math.abs((clear.top+clear.height/2)-(dropdown.top+dropdown.height/2)),
                right:clear.right, width:innerWidth, searchBottom:search.bottom, dropdownBottom:dropdown.bottom};
        })()`);
        assert.deepEqual(details.labels, ['Clear']);
        assert.equal(details.selected, labels[key]);
        assert.ok(details.nextToDropdown && details.gap >= 0 && details.gap <= 16 && details.centered < 2 && details.right <= details.width, JSON.stringify(details));
        assert.ok(desktop ? Math.abs(details.searchBottom-details.dropdownBottom) < 2 : details.searchBottom < details.dropdownBottom, JSON.stringify(details));
    }

    async function applyAndReset(key, desktop) {
        await run(`{ const input = ${dropdown(key)}; input.value='${filterValues[key]}'; input.dispatchEvent(new Event('change',{bubbles:true})); }`);
        await waitFor(`${category(key)} && ${query}.get('${filters[key]}')==='${filterValues[key]}'`);
        if (key === 'disconnection_history') {
            assert.equal(await run(`${form(key)}.querySelectorAll('button[type=submit]').length`), 0);
            assert.deepEqual(await run(`Array.from(${dropdown(key)}.options, option=>option.value)`), ['all','disconnected','reconnected']);
        }
        await run(`{ const input = ${search(key)}; input.value='unmatched-inactive-clear-check'; input.dispatchEvent(new Event('input',{bubbles:true})); }`);

        await waitFor(`${category(key)} && ${query}.get('search')==='unmatched-inactive-clear-check' && ${query}.get('${filters[key]}')==='${filterValues[key]}'`);
        assert.equal(await run(`${search(key)}.value`), 'unmatched-inactive-clear-check');
        assert.equal(await run(`${dropdown(key)}.value`), filterValues[key]);
        if (key === 'disconnection_history') {
            await run(`{ const input = ${dropdown(key)}; input.value='reconnected'; input.dispatchEvent(new Event('change',{bubbles:true})); }`);
            await waitFor(`${category(key)} && ${query}.get('history_status')==='reconnected'`);
            assert.equal(await run(`${search(key)}.value`), 'unmatched-inactive-clear-check');
            assert.equal(await run(`${dropdown(key)}.value`), 'reconnected');
            assert.ok(await run(`document.getElementById('disconnection-history-table').innerText.includes('No disconnection records match')`));
        }
        await check(key, desktop);
        await run(`${clear(key)}.click()`);
        await waitFor(`${clear(key)} && location.search==='?account_category=${key}' && !${search(key)}.value && ${dropdown(key)}.value==='${defaults[key]}'`);
        await check(key, desktop);
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const {name, value} = require('./session.json');
        assert.ok((await send('Network.setCookie', {name, value, url:`${origin}/`, httpOnly:true})).success);
        await send('Page.navigate', {url:`${origin}/consumers?account_category=inactive`});
        await waitFor(`${clear('inactive')} && ${category('inactive')}`);
        for (const [width, mobile] of [[1366, false], [390, true]]) {
            await send('Emulation.setDeviceMetricsOverride', {width, height:900, deviceScaleFactor:1, mobile});
            await waitFor(`Math.abs(window.visualViewport.width - ${width}) < 2`);
            for (const key of Object.keys(filters)) {
                await run(`${tabs}.querySelector('a[href$="account_category=${key}"]').click()`);
                await waitFor(`${category(key)} && ${clear(key)}`);
                await applyAndReset(key, !mobile);
                console.log(`${width}px ${labels[key]}: one properly aligned Clear resets both filters and keeps the active tab.`);
            }
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

