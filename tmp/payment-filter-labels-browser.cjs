
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
    const form = 'document.querySelector(".record-filter-form")';
    const inspect = () => fromBrowser(() => {
        const form = document.querySelector('.record-filter-form');
        return {
            search: form.elements.search.value,
            selection: ['year', 'month', 'status'].map(name => form.elements.namedItem(name).selectedOptions[0].textContent.trim()),
            statusOptions: Array.from(form.elements.status.options, option => option.textContent.trim()),
            clearLabel: form.querySelector('a.record-filter-clear').innerText.trim(),
            count: document.querySelector('.payments-panel-badge').innerText.trim()
        };
    });
    const change = async (name, value, event) => {
        const time = await run('performance.timeOrigin');
        await fromBrowser((name, value, event) => {
            const input = document.querySelector('.record-filter-form').elements.namedItem(name);
            input.value = value;
            input.dispatchEvent(new Event(event, {bubbles: true}));
        }, name, value, event);
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && new URLSearchParams(location.search).get('+JSON.stringify(name)+') === '+JSON.stringify(value));
    };
    const defaults = {
        search: '', selection: ['All Years', 'All Months', 'All Status'],
        statusOptions: ['All Status', 'Paid', 'Pending Payment', 'Rejected'], clearLabel: 'Clear', count: '2 records'
    };
    for (const view of ['water-payments', 'loan-payments']) {
        await visit('/payments?view='+view, form);
        assert.deepEqual(await inspect(), defaults);
        await change('search', 'PAY-FILTER-QA', 'input');
        await change('year', '2026', 'change');
        await change('month', '3', 'change');
        await change('status', 'Paid', 'change');
        assert.deepEqual(await inspect(), {...defaults, search: 'PAY-FILTER-QA', selection: ['2026', 'March', 'Paid'], count: '1 records'});
        const time = await run('performance.timeOrigin');
        await run(form+'.querySelector("a.record-filter-clear").click()');
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && location.search === '+JSON.stringify('?view='+view));
        assert.deepEqual(await inspect(), defaults);
    }
    await visit('/payments?view=review&search=PAY-FILTER-QA', 'document.querySelector(".payments-filter-form")');
    assert.equal(await run('document.querySelector(".payments-filter-actions a").innerText.trim()'), 'Clear filters');
    console.log('Chrome verified the new labels and four-filter reset in Water Payments and Loan Payments. Needs Review retained its existing control.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
