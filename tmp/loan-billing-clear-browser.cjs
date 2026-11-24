
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
    await send('Emulation.setDeviceMetricsOverride', {width:1366,height:900,deviceScaleFactor:1,mobile:false});
    await login('admin@example.com', 'password');
    const status = 'document.getElementById("loan-billing-status")';
    const state = () => fromBrowser(() => {
        const status = document.getElementById('loan-billing-status');
        const form = status.form;
        const controls = Array.from(form.querySelectorAll('button, a'), element => element.textContent.trim());
        const clear = form.querySelector('[aria-label="Clear Loan Billing filters"]');
        const target = status.getBoundingClientRect();
        const right = clear.getBoundingClientRect();
        return {
            search: form.elements.search.value, year: form.elements.year.value,
            month: form.elements.month.value, status: status.value,
            label: status.parentElement.querySelector('.status-filter-value').textContent,
            statusOptions: Array.from(status.options, option => option.textContent.trim()),
            controls, beside: Math.abs(right.left-target.right-8)<1 && Math.abs((target.top+target.bottom)-(right.top+right.bottom))<2,
        };
    });
    const set = async (name,value,event) => {
        const loadTime = await run('performance.timeOrigin');
        await fromBrowser((name,value,event) => {
            const form = document.getElementById('loan-billing-status').form;
            const field = form.elements.namedItem(name);
            field.value = value;
            field.dispatchEvent(new Event(event,{bubbles:true}));
        },name,value,event);
        await waitFor(ready+' && performance.timeOrigin !== '+loadTime+' && new URLSearchParams(location.search).get('+JSON.stringify(name)+') === '+JSON.stringify(value));
    };
    const defaults = {
        search:'',year:'',month:'',status:'',label:'All Status',statusOptions:['Unpaid','Paid with Balance','Paid'],controls:['Clear'],beside:true
    };
    await visit('/billing/loan',status);
    assert.deepEqual(await state(),defaults);
    assert.ok(await run('document.querySelector("#loan-billing-year option[value=\'2026\']") !== null'));
    await set('search','BROWSER-VERIFY','input');
    await set('year','2026','change');
    await set('month','12','change');
    await set('status','Unpaid','change');
    assert.deepEqual(await state(),{...defaults,search:'BROWSER-VERIFY',year:'2026',month:'12',status:'Unpaid'});
    const clearTime = await run('performance.timeOrigin');
    await run('document.querySelector(\'[aria-label="Clear Loan Billing filters"]\').click()');
    await waitFor(ready+' && performance.timeOrigin !== '+clearTime+' && location.search === ""');
    assert.deepEqual(await state(),defaults);
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await refresh();
    assert.deepEqual(await state(),defaults);
    console.log('Chrome verified one adjacent Clear control, all four filter defaults restored in one click, unchanged status options, and desktop/mobile spacing.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
