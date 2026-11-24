const assert = require('node:assert/strict');

async function verify() {
    const origin = 'http://127.0.0.1:8027';
    const tabs = await (await fetch('http://127.0.0.1:9227/json/list')).json();
    const ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    let nextId = 0;
    const requests = new Map();
    ws.addEventListener('message', ({ data }) => {
        const reply = JSON.parse(data);
        const request = requests.get(reply.id);
        if (!request) return;
        requests.delete(reply.id);
        reply.error ? request.reject(new Error(reply.error.message)) : request.resolve(reply.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        requests.set(++nextId, { resolve, reject });
        ws.send(JSON.stringify({ id: nextId, method, params }));
    });
    const run = async expression => {
        const answer = await send('Runtime.evaluate', { expression, returnByValue: true });
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
            await new Promise(resolve => setTimeout(resolve, 250));
        }
        throw new Error(`Expected rendered content at ${await run('location.href')}`);
    };
    const nav = 'document.querySelector(\'nav[aria-label="Disconnection Notice pages"]\')';
    const current = `Number(${nav}?.querySelector('[aria-current=page]')?.textContent)`;
    const loaded = 'document.readyState === "complete"';
    const selection = [ ['notice_search', 'Pager Fixture'], ['notice_status', 'ready'], ['notice_membership', 'member'] ];
    const firstUrl = origin + '/consumers?' + new URLSearchParams([['account_category', 'active'], ...selection]);
    const secondUrl = firstUrl + '&notice_page=2#active-disconnection-notices';
    const readPage = async () => ({
        number: await run(current),
        rows: await run(`Array.from(document.querySelectorAll('#disconnection-records tr[data-record-status]')).map(row=>row.cells[2].innerText)`),
        labels: await run(`Array.from(${nav}.querySelectorAll('.page-link')).map(link=>link.innerText)`),
        summary: await run(`${nav}.parentElement.querySelector('p').innerText`),
        query: Object.fromEntries(new URL(await run('location.href')).searchParams),
    });
    const waitForPage = async page => waitFor(`${loaded} && ${current} === ${page}`);

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        await send('Page.navigate', { url: `${origin}/login` });
        await waitFor(`${loaded} && document.querySelector('#login')`);
        await run(`document.querySelector('.login-welcome')?.click(); document.querySelector('#login').value='admin@example.com';
            document.querySelector('#password').value='password'; document.querySelector('#login').form.requestSubmit();`);
        await waitFor(`${loaded} && location.pathname === '/dashboard'`);
        await send('Page.navigate', { url: firstUrl });
        await waitForPage(1);
        const first = await readPage();
        assert.equal(first.rows.length, 10);
        assert.deepEqual(first.labels, ['Previous', '1', '2', 'Next']);
        assert.equal(first.summary, 'Showing 1 to 10 of 11 accounts');
        await run(`Array.from(${nav}.querySelectorAll('a')).find(link=>link.innerText==='2').click()`);
        await waitForPage(2);
        const second = await readPage();
        assert.equal(second.rows.length, 1);
        assert.ok(!first.rows.includes(second.rows[0]));
        assert.equal(second.summary, 'Showing 11 to 11 of 11 accounts');
        for (const [filter, value] of selection) assert.equal(second.query[filter], value);

        const loadStarted = await run('performance.timeOrigin');
        await send('Page.reload');
        await waitFor(`performance.timeOrigin !== ${loadStarted} && ${loaded} && ${current} === 2`);
        assert.deepEqual(await readPage(), second);
        await run(`${nav}.querySelector('a[rel=prev]').click()`);
        await waitForPage(1);
        assert.deepEqual((await readPage()).rows, first.rows);
        await run(`${nav}.querySelector('a[rel=next]').click()`);
        await waitForPage(2);

        for (const [filter, value, event, count] of [
            ['notice_search', 'Pager Fixture 01', 'input', 1], ['notice_status', 'grace', 'change', 2],
            ['notice_membership', 'non_member', 'change', 1],
        ]) {
            await send('Page.navigate', { url: secondUrl });
            await waitForPage(2);
            await run(`{ const control = document.querySelector('#${filter}'); control.value = ${JSON.stringify(value)}; control.dispatchEvent(new Event('${event}', {bubbles:true})); }`);
            await waitFor(`${loaded} && new URLSearchParams(location.search).get('${filter}') === ${JSON.stringify(value)} && ${current} === 1`);
            const reset = await readPage();
            assert.equal(reset.rows.length, count);
            assert.equal(reset.query.notice_page, undefined);
            for (const [key, previous] of selection) assert.equal(reset.query[key], key === filter ? value : previous);
        }
        console.log('Chrome verified 10 then 1 rows, numbered and Previous/Next links, matching totals, refresh and independent reset to page 1 for each filter.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
