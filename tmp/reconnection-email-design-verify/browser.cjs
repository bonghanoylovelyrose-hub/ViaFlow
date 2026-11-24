
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
    const root = 'tmp/reconnection-email-design-verify';
    const fixture = JSON.parse(fs.readFileSync(root+'/fixture.json','utf8'));
    const adminPortal = '/consumers?account_category=inactive&search=RECONNECT-EMAIL-SAMPLE';
    const pending = '/consumers?account_category=pending&pending_type=reschedules';
    const sampleHost = 'http://reconnect.example.test:8027';
    const reason = 'Sample request to move the reconnection appointment.';
    const preview = async html => {
        await send('Page.navigate',{url:'data:text/html;charset=utf-8;base64,'+Buffer.from(html).toString('base64')});
        await waitFor('location.protocol === "data:" && document.querySelector("h1") && '+ready);
    };
    const viewport = async (width,height) => {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await delay(160);
    };
    const metrics = () => run('(() => { const card = document.querySelector("body > div"); const box = card.querySelector(":scope > div"); const details = card.querySelector("table"); const get = (node, props) => Object.fromEntries(props.map(key=>[key,getComputedStyle(node)[key]])); const find = text => Array.from(card.querySelectorAll("a")).find(link=>link.textContent.trim()===text); return {body:get(document.body,["padding","backgroundColor","fontFamily","color","lineHeight"]),card:get(card,["width","maxWidth","padding","borderRadius","borderColor","backgroundColor"]),title:get(card.querySelector("h1"),["marginTop","fontSize","fontWeight"]),schedule:get(box,["padding","marginTop","marginBottom","borderRadius","borderColor","backgroundColor"]),table:get(details,["width","tableLayout","borderCollapse","marginBottom"]),accountRow:get(details.rows[0].cells[0],["padding","borderBottomColor","borderBottomWidth"]),reschedule:get(find("Request for Reschedule"),["padding","borderRadius","fontSize","fontWeight","color","borderColor","backgroundColor"]),login:get(find("Login Now"),["padding","borderRadius","fontSize","fontWeight","color","backgroundColor"]),footer:get(card.lastElementChild,["marginBottom","fontSize"])}; })()');
    const getEmail = async () => {
        const file = root+'/received-1.eml';
        for (let i=0; !fs.existsSync(file) && i<80; i++) await delay(250);
        const raw = fs.readFileSync(file,'utf8');
        const split = raw.indexOf('\r\n\r\n');
        const headers = raw.slice(0,split);
        const body = raw.slice(split+4);
        const html = /Content-Transfer-Encoding: quoted-printable/i.test(headers)
            ? Buffer.from(body.replace(/=\r?\n/g,'').replace(/=([0-9A-F]{2})/gi,(_,code)=>String.fromCharCode(parseInt(code,16))),'latin1').toString('utf8')
            : /Content-Transfer-Encoding: base64/i.test(headers) ? Buffer.from(body,'base64').toString('utf8') : body;
        return {headers,html};
    };
    await send('Page.enable');
    await visit('/login','document.querySelector("#login")');
    await run('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value="admin@example.com"; document.querySelector("#password").value="password"; document.querySelector("#login").form.requestSubmit()');
    await waitFor('location.pathname === "/dashboard" && '+ready);
    await visit(adminPortal,'document.querySelector("#consumer-accounts-table [data-bs-target=\'#consumerReconnectModal\']")');
    await run('document.querySelector("#consumer-accounts-table [data-bs-target=\'#consumerReconnectModal\']").click()');
    await waitFor('document.activeElement === document.querySelector("#consumerReconnectModal.show")');
    assert.equal(await run('document.querySelector("#reconnectAccountNumber").textContent.trim()'),'RECONNECT-EMAIL-SAMPLE');
    await run('(() => {const form=document.querySelector("#consumerReconnectForm");form.elements.installation_scheduled_at.value='+JSON.stringify(fixture.date+'T09:30:00')+';form.elements.confirm_full_payment.click();form.requestSubmit();})()');
    await waitFor('new URLSearchParams(location.search).get("account_category")==="active" && '+ready);
    const {headers,html}=await getEmail();
    assert.match(headers,/Subject: Water Service Reconnection Schedule Confirmation/i);
    assert.ok(headers.includes(fixture.email));
    for (const value of [
        'Your water service reconnection has been scheduled', 'Hello Reconnection Email Sample', 'Active / Pending Reconnection',
        'Please wait for confirmation that your water service has been restored', fixture.readable_date,
        fixture.time, 'Scheduled Reconnection Time (Philippine Time)', 'authorized representative is available',
        'RECONNECT-EMAIL-SAMPLE', 'RECONNECT-SAMPLE-METER', fixture.email, 'Registered Email Address',
        'Set / Reset Password Securely', 'Request for Reschedule', 'Login Now'
    ]) assert.ok(html.includes(value),value);
    assert.ok(html.indexOf('Scheduled Reconnection Date') < html.indexOf('Your account details'));
    assert.doesNotMatch(html,/Your connection request has been approved|Scheduled Installation Date/);
    const reference=fs.readFileSync(root+'/approved-reference.html','utf8');
    for (const [width,height] of [[1280,1000],[390,844]]) {
        await viewport(width,height);
        await preview(reference);
        const approved=await metrics();
        await preview(html);
        const reconnected=await metrics();
        assert.deepEqual(reconnected,approved);
        assert.ok(await run('document.documentElement.scrollWidth<=innerWidth'));
        console.log('The received reconnection email matched the approved email card, typography, schedule box, account table and button styling at '+width+'px.');
    }
    const anchor='Array.from(document.links).find(link=>link.textContent.trim()==="Request for Reschedule")';
    const exactLink=await run(anchor+'.href');
    assert.ok(exactLink.startsWith(sampleHost+'/reconnection/'));
    assert.ok(new URL(exactLink).searchParams.has('signature'));
    await run('(() => {const link='+anchor+';link.target="_self";link.click();})()');
    await waitFor('location.href==='+JSON.stringify(exactLink)+' && document.querySelector("#preferred-date") && '+ready);
    const schedule=await run('document.body.innerText');
    assert.ok(schedule.includes(fixture.readable_date+' '+fixture.time));
    assert.match(schedule,/0 of 3 used/);
    assert.doesNotMatch(schedule,/no scheduled|no longer open for rescheduling/i);
    await run('(() => {const form=document.querySelector("#preferred-date").form;form.elements.preferred_date.value='+JSON.stringify(fixture.preferred_date)+';form.elements.preferred_time.value="15:00";form.elements.reason.value='+JSON.stringify(reason)+';form.requestSubmit();})()');
    await waitFor('document.body.innerText.includes("Your reschedule request has been submitted for admin approval") && '+ready);
    assert.ok((await run('document.body.innerText')).includes(fixture.readable_date+' '+fixture.time));
    await viewport(1280,1000);
    await visit(pending,'document.querySelector("#consumer-accounts-table [data-reschedule]")');
    await run('document.querySelector("#consumer-accounts-table [data-reschedule]").click()');
    await waitFor('document.activeElement === document.querySelector("#adminReviewRescheduleModal.show")');
    assert.equal(await run('document.querySelector("#arr_account").textContent.trim()'),'RECONNECT-EMAIL-SAMPLE');
    assert.equal(await run('document.querySelector("#arr_reason").textContent.trim()'),reason);
    assert.match(await run('document.querySelector("#arr_type_badge").textContent'),/Reconnection/i);
    console.log('The exact signed link opened the saved reconnection schedule and the submitted request appeared in Admin Pending Accounts ? Reschedule Requests.');
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
