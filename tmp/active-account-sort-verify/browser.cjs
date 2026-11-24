const assert = require('node:assert/strict');

async function verify() {
    const main = 'http://127.0.0.1:8027';
    const qa = 'http://127.0.0.1:8028';
    const tabs = await (await fetch('http://127.0.0.1:9227/json/list')).json();
    const ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    const pending = new Map();
    let nextId = 0;
    ws.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        const request = pending.get(message.id);
        if (!request) return;
        pending.delete(message.id);
        message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        pending.set(++nextId, { resolve, reject });
        ws.send(JSON.stringify({ id: nextId, method, params }));
    });
    const run = async expression => {
        const answer = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (answer.exceptionDetails) throw new Error(answer.result.description);
        return answer.result.value;
    };
    const waitFor = async expression => {
        for (let attempt = 0; attempt < 120; attempt++) {
            try {
                if (await run(`Boolean(${expression})`)) return;
            } catch (error) {
                if (!/context|navigat/i.test(error.message)) throw error;
            }
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        throw new Error(`Expected rendered account rows at ${await run('location.href')}`);
    };
    const selector = '#consumer-accounts-table tbody tr';
    const active = '/consumers?account_category=active';
    const render = `location.pathname==='/consumers' && document.readyState==='complete' && document.querySelector('${selector}')`;
    const meters = () => run(`Array.from(document.querySelectorAll('${selector}')).filter(row=>row.cells.length>3).map(row=>row.cells[2].innerText.trim())`);
    const authorize = async (origin, session) => {
        await send('Network.clearBrowserCookies');
        assert.ok((await send('Network.setCookie', { name: session.name, value: session.value, url: `${origin}/`, httpOnly: true })).success);
    };
    const visit = async url => {
        await send('Page.navigate', { url });
        await waitFor(`${render} && location.href===${JSON.stringify(url)}`);
    };
    const refresh = async () => {
        const started = await run('performance.timeOrigin');
        await send('Page.reload');
        await waitFor(`${render} && performance.timeOrigin !== ${started}`);
    };
    const submit = async (method, action, data = {}) => {
        const result = await run(`fetch(${JSON.stringify(action)}, {
            method: ${JSON.stringify(method)}, redirect:'manual',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]').value },
            body: new URLSearchParams(${JSON.stringify(data)}),
        }).then(response=>response.type==='opaqueredirect' ? 'redirect' : response.status)`);
        assert.equal(result, 'redirect');
    };

    try {
        await send('Page.enable');
        await send('Network.enable');
        await authorize(main, require('./main-session.json'));
        await visit(main + active);
        const expected = require('./main-order.json');
        assert.deepEqual(await meters(), expected);
        await refresh();
        assert.deepEqual(await meters(), expected);
        console.log(`Live MySQL and refreshed Admin UI agree on all ${expected.length} rows. The first three are ${expected.slice(0, 3).join(', ')}.`);

        await authorize(qa, require('./qa-session.json'));
        const pendingList = qa + active + '&search=QA%20Sort&membership=non_member&installation_status=pending';
        await visit(pendingList);
        assert.equal((await meters())[0], 'QA-SORT-RECONNECT');
        const { applicant, prior, reconnection } = require('./qa-fixtures.json');
        const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
        await submit('PATCH', `/consumers/${applicant}/activate`, {
            meter_number: 'QA-SORT-NEW', membership_type: 'Non-Member', installation_date: tomorrow,
        });
        await refresh();
        const firstPage = await meters();
        assert.equal(firstPage.length, 10);
        assert.deepEqual(firstPage.slice(0, 2), ['QA-SORT-NEW', 'QA-SORT-RECONNECT']);
        await visit(pendingList + '&page=2');
        const secondPage = await meters();
        assert.equal(secondPage.length, 3);
        assert.equal(new Set([...firstPage, ...secondPage]).size, 13);
        await refresh();
        assert.deepEqual(await meters(), secondPage);

        await visit(qa + active);
        await submit('POST', `/consumers/${prior}/reconnections/${reconnection}/complete`);
        await refresh();
        assert.deepEqual((await meters()).slice(0, 2), ['QA-SORT-RECONNECT', 'QA-SORT-NEW']);
        console.log('The isolated Admin UI put a newly approved application first, then moved the reconnected account above it after completion. Search, membership, installation filters and pagination stayed correct after refresh.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
