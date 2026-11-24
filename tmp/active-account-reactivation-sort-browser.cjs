
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

    const table = 'document.querySelector("#active-accounts #consumer-accounts-table")';
    const target = 'SORT-DEMO-TARGET';
    const prior = 'SORT-DEMO-PRIOR';
    const legacy = 'SORT-DEMO-LEGACY';
    const ordinary = Array.from({length:11}, (_, index) => 'SORT-DEMO-REGULAR-'+(index+1));
    const accounts = () => run('Array.from('+table+'.tBodies[0].rows).filter(row => row.cells.length > 1).map(row => row.cells[1].textContent.trim())');
    const active = '/consumers?account_category=active';
    const filtered = active+'&search=SORT-DEMO&membership=non_member&installation_status=pending';
    await send('Page.enable');
    await visit('/login', 'document.querySelector("#login")');
    await run('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value = "admin@example.com"; document.querySelector("#password").value = "password"; document.querySelector("#login").form.requestSubmit()');
    await waitFor('location.pathname === "/dashboard"');
    await visit(filtered, table);
    assert.deepEqual(await accounts(), [prior, legacy, ...ordinary].slice(0, 10));
    await visit('/consumers?account_category=inactive&search='+target, 'document.querySelector("#consumer-accounts-table [data-bs-target=\'#consumerReconnectModal\']")');
    await run('document.querySelector("#consumer-accounts-table [data-bs-target=\'#consumerReconnectModal\']").click()');
    await waitFor('document.activeElement === document.querySelector("#consumerReconnectModal.show")');
    assert.equal(await run('document.querySelector("#reconnectAccountNumber").textContent.trim()'), target);
    await run('(() => {const field = document.querySelector("#reconnectSchedule"); const nextHour = new Date(Date.parse(field.min+"Z") + 3600000); field.value = nextHour.toISOString().slice(0,19); document.querySelector("#reconnectPaymentConfirmed").click(); document.querySelector("#consumerReconnectForm button[type=submit]").click(); })()');
    await waitFor('new URLSearchParams(location.search).get("account_category") === "active" && '+ready+' && '+table);
    assert.equal((await accounts())[0], target);
    assert.match(await run(table+'.tBodies[0].rows[0].innerText'), /Pending Reconnection/);
    await refresh();
    assert.equal((await accounts())[0], target);
    console.log('Confirmed reactivation moved the account to the first row and it stayed first after browser refresh.');
    await visit(filtered, table);
    const expected = [target, prior, legacy, ...ordinary];
    assert.deepEqual(await accounts(), expected.slice(0, 10));
    await run('Array.from(document.querySelectorAll("#consumerAccountsPagination a")).find(link => new URL(link.href).searchParams.get("page") === "2").click()');
    await waitFor('new URLSearchParams(location.search).get("page") === "2" && '+ready+' && '+table);
    assert.deepEqual(await accounts(), expected.slice(10));
    assert.equal(await run('document.querySelector("#account-filters #search").value'), 'SORT-DEMO');
    assert.equal(await run('document.querySelector("#account-filters #membership").value'), 'non_member');
    assert.equal(await run('document.querySelector("#account-filters #installation_status").value'), 'pending');
    await visit(filtered, table);
    await refresh();
    assert.deepEqual(await accounts(), expected.slice(0, 10));
    console.log('Search, Membership and Installation Status retained the correct order across both pages.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
