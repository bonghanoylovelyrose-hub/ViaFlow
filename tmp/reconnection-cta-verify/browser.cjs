const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const origin = 'http://127.0.0.1:8027';
const fixture = require('./fixture.json');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function receivedEmail(number) {
    const file = path.join(__dirname, `received-${number}.eml`);
    for (let attempt = 0; !fs.existsSync(file) && attempt < 80; attempt++) await delay(200);
    const mail = fs.readFileSync(file, 'utf8');
    const split = mail.indexOf('\r\n\r\n');
    const headers = mail.slice(0, split);
    assert.ok(headers.includes(`To: ${fixture.email}`));
    const body = mail.slice(split + 4);
    const html = /Content-Transfer-Encoding: quoted-printable/i.test(headers)
        ? Buffer.from(body.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, code) => String.fromCharCode(parseInt(code, 16))), 'latin1').toString('utf8')
        : /Content-Transfer-Encoding: base64/i.test(headers) ? Buffer.from(body, 'base64').toString('utf8') : body;
    return { headers, html };
}

async function verify() {
    const tabs = await (await fetch('http://127.0.0.1:9227/json/list')).json();
    const ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    const calls = new Map();
    let nextId = 0;
    ws.addEventListener('message', ({ data }) => {
        const response = JSON.parse(data);
        const call = calls.get(response.id);
        if (!call) return;
        calls.delete(response.id);
        response.error ? call.reject(new Error(response.error.message)) : call.resolve(response.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        calls.set(++nextId, { resolve, reject });
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
            await delay(250);
        }
        throw new Error(`Expected content on ${await run('location.protocol === "data:" ? "email preview" : location.pathname')}: ${expression}`);
    };
    const ready = 'document.readyState === "complete"';
    const admin = async () => {
        await send('Network.clearBrowserCookies');
        const session = require('./session.json');
        assert.ok((await send('Network.setCookie', { name: session.name, value: session.value, url: `${origin}/`, httpOnly: true })).success);
    };
    const visit = async (url, predicate) => {
        await send('Page.navigate', { url });
        await waitFor(`${ready} && (${predicate})`);
    };
    const submitForm = async (id, fields) => run(`(() => {
        const form = document.querySelector(${JSON.stringify(id)});
        const fields = ${JSON.stringify(fields)};
        for (const [name,value] of Object.entries(fields)) form.elements[name].value = value;
        form.requestSubmit();
    })()`);
    const cta = 'Array.from(document.links).find(link=>link.textContent.trim()==="Request for Reschedule")';
    const verifyLimit = async (firstLink, lastAction) => {
        await visit(firstLink, 'location.pathname.startsWith("/reconnection/") && document.querySelector("[data-reschedule-remaining]")');
        assert.equal(await run('document.querySelector("[data-reschedule-remaining]").dataset.rescheduleRemaining'), '0');
        assert.equal(await run('!!document.getElementById("preferred-date")'), false);
        assert.ok(await run('Array.from(document.querySelectorAll("button:disabled")).some(button=>button.innerText.trim()==="Request for Reschedule")'));
        const status = await run(`(() => {
            const token = decodeURIComponent(document.cookie.split('; ').find(cookie=>cookie.startsWith('XSRF-TOKEN=')).slice('XSRF-TOKEN='.length));
            return fetch(${JSON.stringify(lastAction)}, {
                method:'POST', headers:{Accept:'application/json','X-XSRF-TOKEN':token},
                body:new URLSearchParams({preferred_date:${JSON.stringify(fixture.dates[3].input)},preferred_time:'15:00',reason:'Attempted fourth request'}),
            }).then(async response=>({code:response.status,error:(await response.json()).errors?.reschedule?.[0]}));
        })()`);
        assert.equal(status.code, 422);
        assert.match(status.error, /all 3 reconnection reschedules/);
        const recipients = fs.readFileSync(path.join(__dirname, 'recipients.txt'), 'utf8').trim().split(/\r?\n/);
        assert.deepEqual(recipients, Array(4).fill(`RCPT TO:<${fixture.email}>`));
    };
    const inspectEmail = async (number, expectedDay, expectedTime, remaining) => {
        const { headers, html } = await receivedEmail(number);
        assert.ok(headers.includes(number === 1 ? 'Water Service Reconnection Schedule Confirmation' : 'Updated Reconnection Schedule'));
        await send('Network.clearBrowserCookies');
        await visit('data:text/html;charset=utf-8;base64,' + Buffer.from(html).toString('base64'), 'location.protocol==="data:" && document.querySelector("h1")');
        const text = await run('document.body.innerText');
        for (const part of [fixture.account, fixture.meter, fixture.email, expectedDay, expectedTime, 'Philippine', `Reschedule Attempts Remaining: ${remaining}`]) {
            assert.ok(text.includes(part), part);
        }
        if (remaining === 0) {
            assert.equal(await run(`!!(${cta})`), false);
            assert.ok(text.includes('limit of three'));
            return null;
        }
        const link = await run(`${cta}.href`);
        const url = new URL(link);
        assert.equal(url.origin, origin);
        assert.match(url.pathname, /^\/reconnection\/\d+\/reschedule$/);
        assert.ok(url.searchParams.has('signature') && url.searchParams.has('expires'));
        await run(`(() => { const link = ${cta}; link.target='_self'; link.click(); })()`);
        await waitFor(`${ready} && location.pathname===${JSON.stringify(url.pathname)} && document.getElementById('preferred-date')`);
        assert.ok((await run('document.body.innerText')).includes(`${expectedDay} ${expectedTime}`));
        assert.equal(await run('document.querySelector("[data-reschedule-remaining]").dataset.rescheduleRemaining'), String(remaining));
        return link;
    };

    try {
        await send('Page.enable');
        await send('Network.enable');
        if (process.argv.includes('--verify-limit')) {
            const { html } = await receivedEmail(1);
            await send('Network.clearBrowserCookies');
            await visit('data:text/html;charset=utf-8;base64,' + Buffer.from(html).toString('base64'), 'location.protocol==="data:" && document.querySelector("h1")');
            const link = await run(`${cta}.href`);
            await verifyLimit(link, link);
            console.log('The earliest received email link now shows zero attempts. A valid signed fourth request was rejected by the backend, and the inbox contains four messages.');
            return;
        }
        await admin();
        const reconnectButton = 'document.querySelector("#consumer-accounts-table [data-bs-target=\'#consumerReconnectModal\']")';
        await visit(`${origin}/consumers?account_category=inactive&search=${fixture.account}`, reconnectButton);
        await run(`${reconnectButton}.click()`);
        await waitFor('document.activeElement === document.querySelector("#consumerReconnectModal.show")');
        await run('document.getElementById("consumerReconnectForm").elements.confirm_full_payment.click()');
        await submitForm('#consumerReconnectForm', { installation_scheduled_at: `${fixture.dates[0].input}T09:30:00` });
        await waitFor(`${ready} && new URLSearchParams(location.search).get('account_category')==='active'`);

        const firstLink = await inspectEmail(1, fixture.dates[0].label, '9:30 AM', 3);
        let lastAction;
        const times = ['14:15', '15:45', '13:10'];
        const formattedTimes = ['2:15 PM', '3:45 PM', '1:10 PM'];
        for (let index = 0; index < 3; index++) {
            const date = fixture.dates[index + 1];
            const reason = `QA reconnection preference ${index + 1}`;
            lastAction = await run('document.getElementById("preferred-date").form.action');
            await submitForm('main form:has(#preferred-date)', { preferred_date: date.input, preferred_time: times[index], reason });
            await waitFor(`${ready} && document.body.innerText.includes('Your reschedule request has been submitted for admin approval')`);
            assert.ok((await run('document.body.innerText')).includes(fixture.dates[index].label));
            await admin();
            const pending = 'document.querySelector("#consumer-accounts-table button[data-reschedule]")';
            await visit(`${origin}/consumers?account_category=pending&pending_type=reschedules`, pending);
            assert.equal(await run(`${pending}.disabled`), false);
            await run(`${pending}.click()`);
            await waitFor('document.activeElement === document.querySelector("#adminReviewRescheduleModal.show")');
            assert.ok((await run('document.getElementById("arr_reason").textContent')).includes(reason));
            assert.equal(await run('document.getElementById("arr_type_badge").textContent'), 'Reconnection');
            assert.equal(await run('document.getElementById("arr_usage").textContent'), `${index} of 3 used; ${3 - index} attempts remaining.`);
            const loadedAt = await run('performance.timeOrigin');
            await submitForm('#assignRescheduleForm', { new_installation_date: date.input, new_installation_time: times[index], admin_notes: `Approved schedule ${index + 1}` });
            await waitFor(`${ready} && performance.timeOrigin!==${loadedAt} && !(${pending})`);
            await inspectEmail(index + 2, date.label, formattedTimes[index], 2 - index);
        }

        await verifyLimit(firstLink, lastAction);
        console.log('Received all four test emails. Each permitted button opened its signed page, requests appeared in Admin Pending, and every assignment produced the matching Philippine schedule. The fourth request was blocked in the page and backend.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
