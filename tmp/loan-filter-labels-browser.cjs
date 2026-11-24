
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
    const form = 'document.querySelector("#loan-application-filters")';
    const accounts = () => fromBrowser(() => Array.from(document.querySelectorAll('#loan-management-title ~ .table-responsive tbody tr'))
        .filter(row => row.cells.length > 1).map(row => row.cells[0].textContent.trim()).sort());
    const inspect = () => fromBrowser(() => {
        const form = document.querySelector('#loan-application-filters');
        return {
            labels: Array.from(form.querySelector('#application-status').options, option => option.textContent.trim()),
            clearControls: Array.from(form.querySelectorAll('a'), anchor => anchor.textContent.trim()),
            search: form.elements.application_search.value,
            loanStatus: form.elements.application_status.value,
            installationStatus: form.elements.installation_status.value
        };
    });
    const change = async (name, value, event) => {
        const time = await run('performance.timeOrigin');
        await fromBrowser((name, value, event) => {
            const field = document.querySelector('#loan-application-filters').elements.namedItem(name);
            field.value = value;
            field.dispatchEvent(new Event(event, {bubbles: true}));
        }, name, value, event);
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && new URLSearchParams(location.search).get('+JSON.stringify(name)+') === '+JSON.stringify(value));
    };
    await visit('/loans', form);
    const defaults = {
        labels: ['All Status', 'Approved', 'Rejected'], clearControls: ['Clear'],
        search: '', loanStatus: 'all', installationStatus: 'all'
    };
    assert.deepEqual(await inspect(), defaults);
    const initialRows = await accounts();
    assert.equal(initialRows.length, 5);
    await change('application_search', 'FILTER-TARGET', 'input');
    assert.equal((await accounts()).length, 4);
    await change('application_status', 'approved', 'change');
    assert.equal((await accounts()).length, 3);
    await change('installation_status', 'completed', 'change');
    assert.ok((await accounts()).length < 3);
    assert.deepEqual(await inspect(), {
        ...defaults, search: 'FILTER-TARGET', loanStatus: 'approved', installationStatus: 'completed'
    });
    const time = await run('performance.timeOrigin');
    await run(form+'.querySelector("a").click()');
    await waitFor(ready+' && performance.timeOrigin !== '+time+' && location.search === "?tab=all"');
    assert.deepEqual(await inspect(), defaults);
    assert.deepEqual(await accounts(), initialRows);
    console.log('Chrome verified both labels, all existing Loan Status choices, and that Clear restores all five rows and resets Search, Loan Status, and Installation Status.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
