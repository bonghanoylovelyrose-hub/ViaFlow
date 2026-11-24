const assert = require('node:assert/strict');

async function verify() {
    const origin = 'http://127.0.0.1:8027';
    const tabs = await (await fetch('http://127.0.0.1:9227/json/list')).json();
    const ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    const requests = new Map();
    let nextId = 0;
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
        throw new Error(`Expected rendered page at ${await run('location.href')}`);
    };
    const nav = 'document.querySelector(\'nav[aria-label="Disconnection Notice pages"]\')';
    const current = `Number(${nav}?.querySelector('[aria-current=page]')?.textContent)`;
    const loaded = 'document.readyState === "complete"';
    const filters = { account_category: 'active', notice_search: 'MT-', notice_status: 'ready', notice_membership: '' };
    const readPage = () => run(`(() => {
        const pager = ${nav};
        return {
            page: ${current},
            rows: Array.from(document.querySelectorAll('#disconnection-records tr[data-record-status]')).map(row=>row.cells[2].innerText.trim()),
            summary: pager.parentElement.querySelector('p').innerText,
            previousEnabled: !!pager.querySelector('a[rel=prev]'),
            nextEnabled: !!pager.querySelector('a[rel=next]'),
            pageHighlighted: pager.querySelector('[aria-current=page]').classList.contains('active'),
            query: Object.fromEntries(new URLSearchParams(location.search)),
        };
    })()`);
    const waitForPage = page => waitFor(`${loaded} && ${current} === ${page}`);
    async function click(expression) {
        const { x, y, unobstructed } = await run(`(() => {
            const link = ${expression};
            link.scrollIntoView({block:'center'});
            const rect = link.getBoundingClientRect();
            const x = rect.x + rect.width / 2;
            const y = rect.y + rect.height / 2;
            return { x, y, unobstructed: document.elementFromPoint(x,y)?.closest('a')===link };
        })()`);
        assert.ok(unobstructed);
        await send('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, x, y });
        await send('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, x, y });
    }
    async function checkPage(page, rows, range) {
        await waitForPage(page);
        const view = await readPage();
        assert.deepEqual(view.rows, rows);
        assert.equal(view.summary, `Showing ${range} of 3 accounts`);
        assert.equal(view.previousEnabled, page === 2);
        assert.equal(view.nextEnabled, page === 1);
        assert.ok(view.pageHighlighted);
        for (const [name, value] of Object.entries(filters)) assert.equal(view.query[name] ?? '', value);
        return view;
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const { name, value } = require('./session.json');
        assert.ok((await send('Network.setCookie', { name, value, url: `${origin}/`, httpOnly: true })).success);
        await send('Page.navigate', { url: `${origin}/consumers?${new URLSearchParams(filters)}#active-disconnection-notices` });
        const first = ['MT-7878', 'MT-2121'];
        const last = ['MT-4444'];
        await checkPage(1, first, '1\u20132');
        await click(`${nav}.querySelector('a[rel=next]')`);
        await checkPage(2, last, '3\u20133');
        await click(`${nav}.querySelector('a[rel=prev]')`);
        await checkPage(1, first, '1\u20132');
        await click(`Array.from(${nav}.querySelectorAll('a')).find(link=>link.innerText==='2')`);
        const second = await checkPage(2, last, '3\u20133');
        const loadedAt = await run('performance.timeOrigin');
        await send('Page.reload');
        await waitFor(`performance.timeOrigin !== ${loadedAt} && ${loaded} && ${current} === 2`);
        assert.deepEqual(await readPage(), second);
        await click(`Array.from(${nav}.querySelectorAll('a')).find(link=>link.innerText==='1')`);
        await checkPage(1, first, '1\u20132');
        await click(`${nav}.querySelector('a[rel=next]')`);
        await waitForPage(2);
        await run(`{ const membership = document.querySelector('#notice_membership'); membership.value='member'; membership.dispatchEvent(new Event('change', {bubbles:true})); }`);
        await waitFor(`${loaded} && ${current} === 1 && new URLSearchParams(location.search).get('notice_membership')==='member'`);
        const narrowed = await readPage();
        assert.deepEqual(narrowed.rows, first);
        assert.equal(narrowed.summary, 'Showing 1\u20132 of 2 accounts');
        assert.ok(!narrowed.previousEnabled && !narrowed.nextEnabled);
        assert.equal(narrowed.query.notice_search, 'MT-');
        assert.equal(narrowed.query.notice_status, 'ready');
        console.log('Live Admin Chrome verification passed. Next, Previous, and both page numbers were clickable when applicable. Both pages, counters, filter preservation, and refresh matched the three persisted notices.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
