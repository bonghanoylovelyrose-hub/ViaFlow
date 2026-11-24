const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const result = require('./result.json');
const origin = 'http://127.0.0.1:8027';

const messages = fs.readdirSync(__dirname).filter(name => /^received-\d+\.eml$/.test(name));
assert.equal(messages.length, 1);
const eml = fs.readFileSync(path.join(__dirname, messages[0]), 'utf8');
const separator = eml.indexOf('\r\n\r\n');
const headers = eml.slice(0, separator);
assert.ok(headers.includes(`Subject: ${result.subject}`));
assert.ok(headers.includes(`To: ${result.inbox}`));
assert.match(headers, /Content-Transfer-Encoding: quoted-printable/i);
const html = Buffer.from(eml.slice(separator + 4).replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi,
    (_, hex) => String.fromCharCode(parseInt(hex, 16))), 'latin1').toString('utf8');
for (const value of ['background:#991b1b', result.name, result.account, result.meter, 'Water Billing Overdue',
    'PHP '+result.amount, result.deadline, 'Philippine time', 'three days']) assert.ok(html.includes(value), value);
assert.equal(fs.readFileSync(path.join(__dirname, 'recipients.txt'), 'utf8').trim(), `RCPT TO:<${result.inbox}>`);
console.log('The loopback SMTP inbox received exactly one red warning with the account, meter, reason, amount and database deadline.');

async function verifyInChrome() {
    const tabs = await (await fetch('http://127.0.0.1:9227/json/list')).json();
    const ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    let nextId = 0;
    const pending = new Map();
    ws.addEventListener('message', ({ data }) => {
        const reply = JSON.parse(data);
        const resolver = pending.get(reply.id);
        if (!resolver) return;
        pending.delete(reply.id);
        reply.error ? resolver.reject(new Error(reply.error.message)) : resolver.resolve(reply.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        pending.set(++nextId, { resolve, reject });
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
    const row = 'document.querySelector("#disconnection-records tr[data-record-status=\\"Ready for Disconnection\\"]")';

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        await send('Page.navigate', { url: `${origin}/login` });
        await waitFor('document.readyState === "complete" && document.querySelector("#login")');
        await run(`document.querySelector('.login-welcome')?.click(); document.querySelector('#login').value='admin@example.com';
            document.querySelector('#password').value='password'; document.querySelector('#login').form.requestSubmit();`);
        await waitFor('location.pathname === "/dashboard" && document.readyState === "complete"');
        await send('Page.navigate', { url: `${origin}/consumers?account_category=active&notice_status=ready&notice_search=${result.meter}` });
        await waitFor(`${row} && document.readyState === 'complete'`);
        const before = await run(`${row}.innerText`);
        for (const value of [result.name, result.account, result.meter, result.amount, result.deadline_ui, 'Philippine time',
            'Water Billing Overdue', 'Ready for Disconnection', 'Account: Active', 'Confirm Disconnection']) assert.ok(before.includes(value), value);
        const started = await run('performance.timeOrigin');
        await send('Page.reload');
        await waitFor(`performance.timeOrigin !== ${started} && ${row} && document.readyState === 'complete'`);
        assert.equal(await run(`${row}.innerText`), before);
        console.log('Chrome verified the live MySQL QA row and the same Ready status, amount, deadline and Active account state after refresh.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verifyInChrome().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
