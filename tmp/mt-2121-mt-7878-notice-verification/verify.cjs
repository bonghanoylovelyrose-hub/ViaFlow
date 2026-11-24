const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const records = require('./records.json');
const origin = 'http://127.0.0.1:8027';
const mailbox = 'loan-overdue-qa@example.test';
const subject = 'LOAN OVERDUE \u2014 DISCONNECTION NOTICE';
const showRows = 'document.querySelector("#disconnection-records tbody tr[data-record-status]")';

function decodeQuotedPrintable(value) {
    const bytes = value.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
    return Buffer.from(bytes, 'latin1').toString('utf8');
}

function verifyMailbox() {
    const received = fs.readdirSync(__dirname).filter(name => /^received-\d+\.eml$/.test(name));
    assert.equal(received.length, 2);
    const recipients = fs.readFileSync(path.join(__dirname, 'recipients.txt'), 'utf8').trim().split(/\r?\n/);
    assert.deepEqual(recipients, Array(2).fill(`RCPT TO:<${mailbox}>`));
    const meters = received.map(name => {
        const message = fs.readFileSync(path.join(__dirname, name), 'utf8');
        const separator = message.indexOf('\r\n\r\n');
        assert.ok(separator > 0);
        const headers = message.slice(0, separator).replace(/\r\n[\t ]+/g, ' ');
        assert.ok(headers.includes(`To: ${mailbox}`));
        assert.match(headers, /Content-Type: text\/html/i);
        assert.match(headers, /Content-Transfer-Encoding: quoted-printable/i);
        const rawSubject = headers.match(/^Subject: (.+)$/im)[1];
        const decodedSubject = rawSubject.replace(/=\?utf-8\?([bq])\?([^?]+)\?=/gi, (_, encoding, value) => encoding.toLowerCase() === 'b'
            ? Buffer.from(value, 'base64').toString('utf8') : decodeQuotedPrintable(value.replace(/_/g, ' ')));
        assert.equal(decodedSubject, subject);
        const html = decodeQuotedPrintable(message.slice(separator + 4));
        const record = Object.values(records).find(item => html.includes(`account ${item.account} reached`));
        assert.ok(record);
        for (const expected of [record.name, record.final_due, record.issued, record.deadline, 'PHP 439.00', 'within three days', 'background:#991b1b']) {
            assert.ok(html.includes(expected), `Missing email content for ${record.meter}: ${expected}`);
        }
        console.log(`${record.meter}: ${name} received by the local SMTP inbox and matched the red warning and persisted notice.`);
        return record.meter;
    });
    assert.deepEqual(meters.sort(), Object.keys(records).sort());
}

async function verifyBrowser() {
    const targets = await (await fetch('http://127.0.0.1:9227/json/list')).json();
    const ws = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    const requests = new Map();
    let id = 0;
    ws.addEventListener('message', ({ data }) => {
        const reply = JSON.parse(data);
        const request = requests.get(reply.id);
        if (!request) return;
        requests.delete(reply.id);
        reply.error ? request.reject(new Error(reply.error.message)) : request.resolve(reply.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        requests.set(++id, { resolve, reject });
        ws.send(JSON.stringify({ id, method, params }));
    });
    const run = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true });
        if (result.exceptionDetails) throw new Error(result.result.description);
        return result.result.value;
    };
    const waitFor = async expression => {
        for (let attempt = 0; attempt < 100; attempt++) {
            try {
                if (await run(`Boolean(${expression})`)) return;
            } catch (error) {
                if (!/context|navigat/i.test(error.message)) throw error;
            }
            await new Promise(resolve => setTimeout(resolve, 250));
        }
        throw new Error(`Expected Admin content at ${await run('location.href')}`);
    };
    const readRows = () => Array.from(document.querySelectorAll('#disconnection-records tbody tr[data-record-status]'))
        .slice(0, 2).map(row => ({
            meter: row.cells[2].innerText.trim(),
            account: row.cells[0].innerText.trim(),
            name: row.cells[1].querySelector('.fw-semibold').innerText.trim(),
            balance: row.cells[4].innerText.trim(),
            reason: row.cells[5].innerText.trim(),
            issued: row.cells[7].innerText.trim(),
            deadline: row.cells[8].innerText.trim(),
            status: row.dataset.recordStatus,
            accountStatus: row.cells[9].innerText.trim(),
            confirmEnabled: !row.querySelector('[data-disconnection-url]')?.disabled && !!row.querySelector('[data-disconnection-url]'),
        }));
    const verifyRows = async () => {
        const rows = await run(`(${readRows.toString()})()`);
        assert.deepEqual(rows.map(row => row.meter).sort(), Object.keys(records).sort());
        for (const row of rows) {
            const record = records[row.meter];
            assert.equal(row.account, record.account);
            assert.equal(row.name, record.name);
            assert.equal(row.balance, 'P 439.00');
            assert.ok(row.reason.includes('Meter Loan Overdue') && row.reason.includes('Meter loan: P 439.00'));
            assert.equal(row.issued, record.issued_ui);
            assert.ok(row.deadline.includes(record.deadline_ui) && row.deadline.includes('Three-day window elapsed'));
            assert.equal(row.status, 'Ready for Disconnection');
            assert.ok(row.accountStatus.includes('Account: Active'));
            assert.ok(row.confirmEnabled);
        }
        return rows;
    };
    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const { name, value } = require('./browser-session.json');
        assert.ok((await send('Network.setCookie', { name, value, url: `${origin}/`, httpOnly: true })).success);
        await send('Page.navigate', { url: `${origin}/consumers?account_category=active#active-disconnection-notices` });
        await waitFor(`${showRows} && document.readyState === 'complete'`);
        const initial = await verifyRows();
        await run(`document.querySelector('#notice_status').value = 'ready'; document.querySelector('#notice_status').dispatchEvent(new Event('change', {bubbles:true}));`);
        await waitFor(`new URLSearchParams(location.search).get('notice_status') === 'ready' && ${showRows} && document.readyState === 'complete'`);
        assert.deepEqual(await verifyRows(), initial);
        const started = await run('performance.timeOrigin');
        await send('Page.reload');
        await waitFor(`performance.timeOrigin !== ${started} && ${showRows} && document.readyState === 'complete'`);
        assert.deepEqual(await verifyRows(), initial);
        console.log('Chrome verified both accounts in the first two rows on All Status and Ready for Disconnection, with all displayed fields intact after refresh.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verifyMailbox();
verifyBrowser().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
