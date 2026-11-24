
const assert = require('node:assert/strict');
const origin = 'http://127.0.0.1:8027';
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function main() {
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
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.result.description);
        return result.result.value;
    };
    const waitFor = async expression => {
        for (let index = 0; index < 140; index++) {
            try {
                if (await run('Boolean('+expression+')')) return;
            } catch (error) {
                if (!/context|navigat/i.test(error.message)) throw error;
            }
            await delay(250);
        }
        throw new Error('Timed out '+expression+' at '+await run('location.href'));
    };
    const ready = 'document.readyState === "complete"';
    const visit = async (path, predicate) => {
        await send('Page.navigate', { url: origin + path });
        await waitFor('location.pathname === '+JSON.stringify(path.split('?')[0])+' && '+ready+' && ('+predicate+')');
    };
    const refresh = async () => {
        const loadedAt = await run('performance.timeOrigin');
        await send('Page.reload');
        await waitFor('performance.timeOrigin !== '+loadedAt+' && '+ready);
    };

    const fs = require('node:fs');
    const dir = 'tmp/loan-installation-reschedule-verify';
    const fixture = JSON.parse(fs.readFileSync(dir+'/fixture.json','utf8').replace(/^\uFEFF/,''));
    const meter = 'NEW-SAMPLE-METER';
    const dateName = value => new Date(value+'T00:00:00Z').toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric',timeZone:'UTC'});
    const login = async () => {
        await visit('/login', 'document.querySelector("#login")');
        await run('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value = "admin@example.com"; document.querySelector("#password").value = "password"; document.querySelector("#login").form.requestSubmit()');
        await waitFor('location.pathname === "/dashboard" && '+ready);
    };
    const splitPart = part => {
        const boundary = part.indexOf('\r\n\r\n');
        return { headers:part.slice(0,boundary), body:part.slice(boundary+4).trim() };
    };
    const decodeBody = ({headers,body}) => {
        if (/Content-Transfer-Encoding: base64/i.test(headers)) return Buffer.from(body,'base64');
        if (/Content-Transfer-Encoding: quoted-printable/i.test(headers))
            return Buffer.from(body.replace(/=\r?\n/g,'').replace(/=([0-9A-F]{2})/gi,(_, code) => String.fromCharCode(parseInt(code,16))),'latin1');
        return Buffer.from(body);
    };
    const received = async (number) => {
        const file = dir+'/received-'+number+'.eml';
        for (let i=0; i<100 && !fs.existsSync(file); i++) await delay(250);
        const raw = fs.readFileSync(file,'utf8');
        const root = splitPart(raw);
        const boundary = root.headers.match(/boundary="?([^";\r\n]+)"?/i)?.[1]?.trim();
        const parts = boundary ? root.body.split('--'+boundary).slice(1,-1).map(part => splitPart(part.trim())) : [root];
        const html = decodeBody(parts.find(part => /Content-Type: text\/html/i.test(part.headers))).toString('utf8');
        const pdfPart = parts.find(part => /Content-Type: application\/pdf/i.test(part.headers));
        return { raw, html, pdf:pdfPart ? decodeBody(pdfPart) : null };
    };
    const openMailLink = async (mail) => {
        const preview = 'data:text/html;charset=utf-8;base64,'+Buffer.from(mail.html).toString('base64');
        await send('Page.navigate', { url: preview });
        const anchor = 'Array.from(document.links).find(link => link.textContent.trim() === "Request for Reschedule")';
        await waitFor('location.protocol === "data:" && ('+anchor+')');
        const actualLink = await run(anchor+'.href');
        assert.match(actualLink,new RegExp('^http://127\\.0\\.0\\.1:8027/installation/reschedule/loan/'+fixture.loan_id+'\\?signature='));
        await run('(() => {const link = '+anchor+'; link.target="_self"; link.click();})()');
        await waitFor('location.href === '+JSON.stringify(actualLink)+' && '+ready+' && document.querySelector("[data-reschedule-form]")');
        return actualLink;
    };

    await send('Page.enable');
    await send('Network.enable');
    await login();
    const loanPage = '/loans?loan='+fixture.loan_id;
    const modal = '#combinedLoanSchedule'+fixture.loan_id;
    await visit(loanPage, 'document.querySelector(\'[data-bs-target="'+modal+'"]\')');
    await run('document.querySelector(\'[data-bs-target="'+modal+'"]\').click()');
    await waitFor('document.activeElement === document.querySelector("'+modal+'.show")');
    await run('(() => { const form = document.querySelector("'+modal+' form"); form.elements.meter_number.value = '+JSON.stringify(meter)+'; form.elements.installation_date.value = '+JSON.stringify(fixture.initial_date)+'; form.elements.installation_time.value = "14:30"; form.elements.installation_notes.value = "Call at the gate"; form.requestSubmit(); })()');
    await waitFor('!document.querySelector("'+modal+'.show") && document.querySelector("[data-installation-complete-trigger]") && '+ready);
    const first = await received(1);
    assert.match(first.raw,/Subject: .*Loan Installation Scheduled/i);
    assert.ok(first.raw.includes(fixture.email));
    for (const text of ['Your water service account is active.', 'Loan Installation Sample','VIAFLOW-LOAN-SAMPLE',
        'New Assigned Meter Number:</strong> '+meter,'Username:</strong> '+meter,
        'Registered Email Address:</strong> '+fixture.email,dateName(fixture.initial_date),'2:30 PM (Philippine Time)',
        'installment schedule are attached']) assert.ok(first.html.includes(text),text);
    assert.doesNotMatch(first.html,/password|LocalSampleLogin2026/i);
    assert.ok(first.pdf && first.pdf.subarray(0,5).toString() === '%PDF-');
    fs.writeFileSync(dir+'/received-agreement.pdf', first.pdf);
    console.log('The local SMTP inbox received the activation email with the assigned meter, username, Philippine schedule, and PDF.');
    await send('Network.clearBrowserCookies');
    await openMailLink(first);
    const initial = dateName(fixture.initial_date)+' at 2:30 PM (Philippine Time)';
    assert.ok((await run('document.body.innerText')).includes(initial));
    assert.doesNotMatch(await run('document.body.innerText'),/no currently scheduled installation/i);
    assert.deepEqual(await run('Array.from(document.querySelectorAll("[data-reschedule-form] textarea,[data-reschedule-form] input:not([type=hidden])"), el => el.name)'), ['reason']);
    const reason = 'Sample request from the received email';
    await run('(() => { const form = document.querySelector("[data-reschedule-form]"); form.elements.reason.value = '+JSON.stringify(reason)+'; form.requestSubmit(); })()');
    await waitFor('document.body.innerText.includes("Reschedule Request Pending Administrator Review") && '+ready);
    console.log('A signed link clicked from the received email showed the saved schedule and accepted the reason-only request.');

    await login();
    await visit('/loans', 'document.querySelector(\'a[href*="tab=pending"]\')');
    await run('document.querySelector(\'a[href*="tab=pending"]\').click()');
    await waitFor('new URLSearchParams(location.search).get("tab") === "pending" && '+ready);
    await run('document.querySelector(\'a[href*="pending=reschedule"]\').click()');
    await waitFor('document.querySelector("#loan-reschedules-title") && '+ready);
    assert.ok((await run('document.body.innerText')).includes(reason));
    assert.ok((await run('document.body.innerText')).includes(dateName(fixture.initial_date)+' at 2:30 PM'));
    const assigned = 'document.querySelector(\'section[aria-labelledby="loan-reschedules-title"] form\')';
    await run('(() => { const form = '+assigned+'; form.elements.new_installation_date.value = '+JSON.stringify(fixture.updated_date)+'; form.elements.new_installation_time.value = "16:15"; form.elements.admin_notes.value = "Confirmed sample updated appointment"; form.requestSubmit(); })()');
    await waitFor('document.body.innerText.includes("No installation rescheduling requests are pending") && '+ready);
    const second = await received(2);
    assert.match(second.raw,/Subject: .*New Installation Schedule Assigned/i);
    assert.ok(second.raw.includes(fixture.email));
    const expectedUpdated = dateName(fixture.updated_date)+' at 4:15 PM (Philippine Time)';
    assert.ok(second.html.includes(expectedUpdated));
    assert.ok(second.html.includes('Confirmed sample updated appointment'));
    await send('Network.clearBrowserCookies');
    await openMailLink(second);
    assert.ok((await run('document.body.innerText')).includes(expectedUpdated));
    assert.doesNotMatch(await run('document.body.innerText'),/no currently scheduled installation/i);
    console.log('Admin assigned the new appointment from the Pending tab. The second SMTP email linked to the updated saved schedule.');
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
