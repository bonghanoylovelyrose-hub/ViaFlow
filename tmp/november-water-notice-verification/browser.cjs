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
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        throw new Error(`Expected rendered content at ${await run('location.href')}`);
    };
    const loaded = 'document.readyState === "complete"';
    const rows = 'Array.from(document.querySelectorAll("#disconnection-records tr[data-record-status]"))';
    const noticeRow = `${rows}.find(row=>row.cells[2].innerText.trim()==='MT-4444')`;
    const readNotice = async () => run(`(() => { const row = ${noticeRow}; return {
        cells: Array.from(row.cells).map(cell=>cell.innerText.trim()),
        status: row.dataset.recordStatus,
        red: getComputedStyle(row.cells[0]).backgroundColor,
        confirmEnabled: row.querySelector('[data-disconnection-url]')?.disabled === false,
    }; })()`);

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const { name, value } = require('./session.json');
        assert.ok((await send('Network.setCookie', { name, value, url: `${origin}/`, httpOnly: true })).success);
        await send('Page.navigate', { url: `${origin}/consumers?account_category=active&notice_status=ready#active-disconnection-notices` });
        await waitFor(`${loaded} && ${noticeRow}`);
        const before = await readNotice();
        assert.equal(before.cells[0], '17');
        assert.ok(before.cells[1].includes('John Brandon'));
        assert.equal(before.cells[4], 'P 700.00');
        assert.ok(before.cells[5].includes('Water Billing Overdue'));
        assert.equal(before.cells[6], 'Unpaid');
        assert.ok(before.cells[7].startsWith('Nov 10, 2026'));
        assert.ok(before.cells[8].includes('Nov 13, 2026 9:00 AM') && before.cells[8].includes('Three-day window elapsed'));
        assert.ok(before.cells[9].includes('Account: Active'));
        assert.equal(before.status, 'Ready for Disconnection');
        assert.equal(before.red, 'rgb(248, 215, 218)');
        assert.ok(before.confirmEnabled);
        const listed = await run(`${rows}.map(row=>row.cells[2].innerText.trim())`);
        assert.ok(listed.includes('MT-2121') && listed.includes('MT-7878'));
        assert.ok(!listed.includes('MT-3232') && !listed.includes('MT3232'));
        const loadedAt = await run('performance.timeOrigin');
        await send('Page.reload');
        await waitFor(`${loaded} && performance.timeOrigin !== ${loadedAt} && ${noticeRow}`);
        assert.deepEqual(await readNotice(), before);
        console.log('Main MySQL Admin page verified MT-4444 in a red Ready row with P 700.00, November 10 and 13 dates, and Active status after refresh. The prior loan notices are still visible.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
