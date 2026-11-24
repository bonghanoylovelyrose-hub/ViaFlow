
const assert = require('node:assert/strict');
let origin = 'http://127.0.0.1:8027';
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

    const fromBrowser = (callback, ...args) => run('('+callback.toString()+')('+args.map(arg => JSON.stringify(arg)).join(',')+')');
    const login = async (login, password) => {
        await visit('/login', 'document.querySelector("#login")');
        await fromBrowser((login, password) => {
            document.querySelector('.login-welcome')?.click();
            document.querySelector('#login').value = login;
            document.querySelector('#password').value = password;
            document.querySelector('#login').form.requestSubmit();
        }, login, password);
        await waitFor('location.pathname === "/dashboard" && '+ready);
    };





    await send('Page.enable');
    await send('Network.enable');
    await send('Network.clearBrowserCookies');
    const subject='LOAN OVERDUE \u2014 DISCONNECTION NOTICE';
    const noticeRow = 'Array.from(document.querySelectorAll("#disconnection-records tbody tr[data-record-status]")).find(row=>row.innerText.includes("MT-2121"))';
    const noticePage='/consumers?account_category=active&notice_search=MT-2121&search=MT-2121';
    const consumerRow='Array.from(document.querySelectorAll("#consumer-accounts-table tbody tr")).find(row=>row.innerText.includes("MT-2121"))';

    await send('Page.navigate',{url:'file:///C:/xampp/htdocs/waterbillingsystems/tmp/mt-2121-loan-overdue-email.html'});
    await waitFor(ready+' && document.querySelector("h1")?.textContent.includes("LOAN OVERDUE")');
    assert.equal(await run('document.title'),subject);
    assert.equal(await run('getComputedStyle(document.querySelector("h1").parentElement).backgroundColor'),'rgb(153, 27, 27)');
    const mail=await run('document.body.innerText');
    for(const key of ['PHP 439.00','Notice issued','Payment deadline','within three days','Philippine time']) assert.ok(mail.includes(key));
    console.log('Captured MT-2121 mail rendered with the red header, PHP 439.00, and the three-day deadline.');

    await login('MT-2121','OverdueFixture2121');
    await visit('/connection','document.body.innerText.includes("Payment deadline")');
    const banner=await run('Array.from(document.querySelectorAll(".alert-danger")).find(el=>el.innerText.includes("LOAN OVERDUE")).innerText');
    assert.ok(banner.includes('PHP 439.00')&&banner.includes('Philippine time'));
    await send('Network.clearBrowserCookies');
    await login('admin@example.com','password');
    await visit(noticePage,noticeRow);
    assert.equal(await run('('+noticeRow+').dataset.recordStatus'),'Within Grace Period');
    const grace=await run('('+noticeRow+').innerText');
    for (const value of ['Meter Loan Overdue','439.00','Account: Active','Philippine time']) assert.ok(grace.includes(value));
    assert.ok(await run('Array.from(('+noticeRow+').querySelectorAll("button")).some(button=>button.disabled&&button.innerText.includes("Confirm Disconnection"))'));

    await visit('/payments?view=review&search=QA-MT-2121-FULL','document.querySelector("button[data-payment-id][data-bs-target=\'#paymentReviewModal\']")');
    await run('document.querySelector("button[data-payment-id][data-bs-target=\'#paymentReviewModal\']").click()');
    await waitFor('document.activeElement === document.querySelector("#paymentReviewModal.show")');
    await run('document.querySelector("#paymentVerifyForm button[type=submit]").click()');
    await waitFor(ready+' && document.body.innerText.includes("Payment verified successfully")');
    await visit(noticePage,consumerRow);
    assert.equal(await run('Boolean('+noticeRow+')'),false);
    assert.ok(await run('('+consumerRow+').innerText.includes("Active")'));
    console.log('Pending payment left the notice active. Admin verification of the complete balance cleared it and kept the account active.');

    origin='http://127.0.0.1:8028';
    await send('Network.clearBrowserCookies');
    await login('admin@example.com','password');
    await visit(noticePage,noticeRow);
    assert.equal(await run('('+noticeRow+').dataset.recordStatus'),'Ready for Disconnection');
    assert.ok(await run('('+noticeRow+').innerText.includes("Account: Active")'));
    await run('('+noticeRow+').querySelector("[data-disconnection-url]").click()');
    await waitFor('document.activeElement === document.querySelector("#consumerDisconnectionModal.show")');
    assert.equal(await run('document.querySelector("[data-disconnection-field=meter]").innerText'),'MT-2121');
    assert.equal(await run('document.querySelector("[data-disconnection-field=balance]").innerText'),'439.00');
    await run('document.getElementById("consumerDisconnectionReason").value="Development fixture: physical disconnection confirmed"; document.querySelector("#consumerDisconnectionForm button[type=submit]").click()');
    await waitFor(ready+' && new URLSearchParams(location.search).get("account_category")==="inactive" && document.body.innerText.includes("Physical disconnection confirmed")');
    await visit('/consumers?account_category=inactive&search=MT-2121',consumerRow);
    assert.ok(await run('('+consumerRow+').innerText.includes("Disconnected")'));
    console.log('Expired notice showed Ready while connected. Admin confirmation moved the cloned MT-2121 account to Inactive Accounts.');
    await send('Browser.close').catch(()=>{});
    ws.close();
}
main().catch(error=>{console.error(error);process.exit(1);});
