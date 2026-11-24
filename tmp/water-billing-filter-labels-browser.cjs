
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
    const form = 'document.querySelector("form.billing-filter.record-filter-form")';
    const inspect = () => fromBrowser(() => {
        const form = document.querySelector('form.billing-filter.record-filter-form');
        return {
            search: form.elements.search.value,
            selections: ['year','month','status'].map(name=>form.elements.namedItem(name).selectedOptions[0].textContent.trim()),
            options: Array.from(form.elements.status.options,node=>node.textContent.trim()),
            clear: Array.from(form.querySelectorAll('.record-filter-clear'),node=>node.textContent.trim()),
            accounts: Array.from(document.querySelectorAll('.billing-accounts-table tbody tr'),row=>row.querySelector('th[scope="row"]')?.textContent.trim()).filter(Boolean)
        };
    });
    const apply = async (name,value,event) => {
        const old = await run('performance.timeOrigin');
        await fromBrowser((name,value,event)=>{
            const field=document.getElementById('billing_filter_'+name);
            field.value=value;
            field.dispatchEvent(new Event(event,{bubbles:true}));
        },name,value,event);
        await waitFor(ready+' && performance.timeOrigin !== '+old+' && new URLSearchParams(location.search).get('+JSON.stringify(name)+') === '+JSON.stringify(value));
    };
    await visit('/billing',form);
    const defaults = await inspect();
    assert.equal(defaults.search,'');
    assert.deepEqual(defaults.selections,['All Years','All Months','All Status']);
    assert.deepEqual(defaults.clear,['Clear']);
    assert.equal(defaults.options[0],'All Status');
    assert.ok(defaults.accounts.includes('Payment Filter Browser'));
    await apply('search','PAY-FILTER-QA','input');
    await apply('year','2026','change');
    await apply('month','3','change');
    await apply('status','Overdue','change');
    const filtered = await inspect();
    assert.equal(filtered.search,'PAY-FILTER-QA');
    assert.deepEqual(filtered.selections,['2026','March','Overdue']);
    assert.deepEqual(filtered.options,defaults.options);
    const old = await run('performance.timeOrigin');
    await run(form+'.querySelector(".record-filter-clear").click()');
    await waitFor(ready+' && performance.timeOrigin !== '+old+' && location.search === ""');
    assert.deepEqual(await inspect(),defaults);
    console.log('Chrome verified All Status, Clear, and restoration of all four Water Billing filter defaults and account rows.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
