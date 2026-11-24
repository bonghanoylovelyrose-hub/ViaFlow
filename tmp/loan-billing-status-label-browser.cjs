
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
    await login('admin@example.com', 'password');
    const field = 'document.getElementById("loan-billing-status")';
    const state = () => fromBrowser(() => {
        const select = document.getElementById('loan-billing-status');
        const label = select.parentElement.querySelector('.status-filter-value');
        return {
            placeholder: label.innerText.trim(),
            placeholderVisible: !label.hidden && label.getBoundingClientRect().width > 0,
            statuses: Array.from(select.options, option => option.textContent.trim()),
            status: select.value,
            search: select.form.elements.search.value,
        };
    });
    await visit('/billing/loan?search=FILTER-TARGET',field);
    const defaults = {
        placeholder:'All Status',placeholderVisible:true,statuses:['Unpaid','Paid with Balance','Paid'],
        status:'',search:'FILTER-TARGET',
    };
    assert.deepEqual(await state(), defaults);
    const selectedAt = await run('performance.timeOrigin');
    await fromBrowser(() => {
        const select = document.getElementById('loan-billing-status');
        select.value = 'Paid with Balance';
        select.dispatchEvent(new Event('change',{bubbles:true}));
    });
    await waitFor(ready+' && performance.timeOrigin !== '+selectedAt+' && new URLSearchParams(location.search).get("status") === "Paid with Balance"');
    const selected = await state();
    assert.equal(selected.status,'Paid with Balance');
    assert.equal(selected.placeholderVisible,false);
    assert.equal(selected.search,'FILTER-TARGET');
    const clearedAt = await run('performance.timeOrigin');
    await run(field+'.parentElement.querySelector(\'button[aria-label="Clear status filter"]\').click()');
    await waitFor(ready+' && performance.timeOrigin !== '+clearedAt+' && '+field);
    assert.deepEqual(await state(),defaults);
    console.log('Chrome verified the rendered All Status label, all three existing options, applying a status, and clearing it while preserving Search.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
