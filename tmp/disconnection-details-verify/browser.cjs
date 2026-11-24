const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

async function verify() {
    const origin = 'http://127.0.0.1:8027';
    const targets = require('./targets.json');
    const tabs = await (await fetch('http://127.0.0.1:9227/json/list')).json();
    const ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    const pending = new Map();
    let nextId = 0;
    ws.addEventListener('message', ({ data }) => {
        const reply = JSON.parse(data);
        const request = pending.get(reply.id);
        if (!request) return;
        pending.delete(reply.id);
        reply.error ? request.reject(new Error(reply.error.message)) : request.resolve(reply.result);
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
        throw new Error(`Expected rendered interface at ${await run('location.href')}`);
    };
    const rows = 'Array.from(document.querySelectorAll("#disconnection-records tbody tr[data-record-status]"))';
    const row = meter => `${rows}.find(row=>row.cells[2].innerText.trim()===${JSON.stringify(meter)})`;
    const nav = 'document.querySelector(\'nav[aria-label="Disconnection Notice pages"]\')';
    const click = async expression => {
        const { x, y, unobstructed } = await run(`(async () => {
            const control = ${expression};
            control.scrollIntoView({block:'center',inline:'center',behavior:'instant'});
            await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
            const rect = control.getBoundingClientRect();
            const x = rect.x + rect.width / 2;
            const y = rect.y + rect.height / 2;
            return { x, y, unobstructed: !!document.elementFromPoint(x,y) && control.contains(document.elementFromPoint(x,y)) };
        })()`);
        assert.ok(unobstructed, expression);
        await send('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, x, y });
        await send('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, x, y });
    };
    const closeModal = async (id, narrow = false) => {
        const dismiss = `document.querySelector('#${id} .modal-header button[data-bs-dismiss=modal]')`;
        if (narrow) await run(`${dismiss}.click()`);
        else await click(dismiss);
        await waitFor(`!document.querySelector('#${id}.show') && !document.querySelector('.modal-backdrop')`);
    };
    const verifyDetails = async (meter, reason, balance, screenshot = '', narrow = false) => {
        const target = targets[meter];
        const modal = `disconnectionNoticeDetails-${target.id}`;
        const compact = await run(`(() => {const record=${row(meter)};return {
            text: record.cells[9].innerText.trim(), children: record.cells[9].children.length,
            statusClass: record.classList.contains('table-danger'), color: getComputedStyle(record.cells[9]).backgroundColor,
            actions: Array.from(record.cells[10].querySelectorAll('button')).map(button=>button.innerText.trim()),
        };})()`);
        assert.deepEqual(compact, { text: 'Ready for Disconnection', children: 1, statusClass: true,
            color: 'rgb(248, 215, 218)', actions: ['Confirm Disconnection', 'View Details', 'Edit'] });
        const trigger = `${row(meter)}.querySelector('[data-bs-target="#${modal}"]')`;
        if (narrow) await run(`${trigger}.click()`);
        else await click(trigger);
        await waitFor(`document.activeElement===document.querySelector('#${modal}.show')`);
        const modalText = await run(String.raw`document.getElementById('${modal}').textContent.replace(/\s+/g,' ').trim()`);
        for (const value of [meter, 'Ready for Disconnection', 'Account status Active', 'Connected', reason, balance, 'Notice date', 'Payment deadline',
            'Three-day window elapsed', 'Full payment required before disconnection.', `Notice history (${target.notices})`,
            `Reconnection history (${target.reconnections})`, 'Reschedule attempts', `${target.usage.used} of ${target.usage.limit} used`]) {
            assert.ok(modalText.includes(value), value);
        }
        assert.equal(await run(`document.querySelector('#${modal} [data-reschedule-remaining]').dataset.rescheduleRemaining`), String(target.usage.remaining));
        assert.ok(await run(`document.querySelector('#${modal} .modal-dialog').getBoundingClientRect().right <= innerWidth`));
        if (screenshot) {
            const captured = await send('Page.captureScreenshot');
            fs.writeFileSync(path.join(__dirname, screenshot), Buffer.from(captured.data, 'base64'));
        }
        await closeModal(modal, narrow);
    };

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });
        const { name, value } = require('./session.json');
        assert.ok((await send('Network.setCookie', { name, value, url: `${origin}/`, httpOnly: true })).success);
        await send('Page.navigate', { url: 'about:blank' });
        await waitFor("location.href==='about:blank'");
        await send('Page.navigate', { url: `${origin}/consumers?account_category=active&notice_status=ready#active-disconnection-notices` });
        await waitFor(`document.readyState==='complete' && ${row('MT-2121')} && window.bootstrap?.Modal`);
        assert.equal(await run(`${rows}.length`), 2);
        await verifyDetails('MT-2121', 'Meter Loan Overdue', 'P 439.00', 'details-desktop.png');
        await verifyDetails('MT-7878', 'Meter Loan Overdue', 'P 439.00');
        await click(`${row('MT-2121')}.querySelector('[data-disconnection-url]')`);
        await waitFor('document.activeElement===document.querySelector("#consumerDisconnectionModal.show")');
        assert.equal(await run('document.querySelector("[data-disconnection-field=meter]").textContent'), 'MT-2121');
        await closeModal('consumerDisconnectionModal');
        await click(`${row('MT-2121')}.querySelector('[data-bs-target="#consumerEditModal"]')`);
        await waitFor('document.activeElement===document.querySelector("#consumerEditModal.show")');
        assert.equal(await run('document.getElementById("edit_meter_number").value'), 'MT-2121');
        await closeModal('consumerEditModal');
        await click(`${nav}.querySelector('[rel=next]')`);
        await waitFor(`document.readyState==='complete' && ${row('MT-4444')}`);
        assert.equal(await run(`${rows}.length`), 1);
        await verifyDetails('MT-4444', 'Water Billing Overdue', 'P 700.00');
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 820, deviceScaleFactor: 1, mobile: true });
        await waitFor('window.visualViewport.width <= 400');
        await verifyDetails('MT-4444', 'Water Billing Overdue', 'P 700.00', 'details-mobile.png', true);
        console.log('Chrome verified compact red rows, each account’s details, both pages, narrow-screen layout, and the existing Confirm Disconnection and Edit dialogs.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
