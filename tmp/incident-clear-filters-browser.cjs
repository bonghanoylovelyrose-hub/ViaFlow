
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
    await send('Emulation.setDeviceMetricsOverride', {width: 1366, height: 900, deviceScaleFactor: 1, mobile: false});
    await login('admin@example.com', 'password');
    const search = 'QA-';
    const start = '/incidents?status=Pending&search=QA-&category=Others&priority=Needs+Review';
    const filter = 'document.getElementById("incident_filter_priority")';
    const state = () => fromBrowser(() => {
        const label = 'nav[aria-label="Filter incident reports by status"] [aria-current="page"]';
        const category = document.getElementById('incident_filter_category');
        const priority = document.getElementById('incident_filter_priority');
        return {
            tab: document.querySelector(label).textContent.trim(),
            search: document.getElementById('incident_filter_search').value,
            category: category.value,
            categoryLabel: category.selectedOptions[0].textContent.trim(),
            priority: priority.value,
            priorityLabel: priority.selectedOptions[0].textContent.trim(),
            records: Array.from(document.querySelectorAll('[data-priority-url]'), button => button.dataset.incidentLocation).sort()
        };
    });
    const check = async (category, priority, records, expectedSearch = search) => {
        assert.deepEqual(await state(), {
            tab: 'Pending', search: expectedSearch, category, categoryLabel: category || 'All Categories',
            priority, priorityLabel: priority || 'All Priorities', records
        });
    };
    const clear = async name => {
        const time = await run('performance.timeOrigin');
        await fromBrowser(name => document.querySelector('button[aria-label="Clear '+name+' filter"]').click(), name);
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && '+filter);
    };
    const change = async (name, value) => {
        const time = await run('performance.timeOrigin');
        await fromBrowser((name, value) => {
            const select = document.getElementById('incident_filter_'+name);
            select.value = value;
            select.dispatchEvent(new Event('change', {bubbles: true}));
        }, name, value);
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && '+filter);
    };
    const checkButtons = async () => {
        const actual = await fromBrowser(() => {
            const form = document.getElementById('incident_filter_priority').form;
            return {
                clearButtons: Array.from(form.querySelectorAll('button[aria-label^="Clear "]'), button => {
                    const buttonRect = button.getBoundingClientRect();
                    const select = button.previousElementSibling;
                    const selectRect = select.getBoundingClientRect();
                    return [button.getAttribute('aria-label'), button.innerText.trim(),
                        selectRect.right < buttonRect.left && Math.abs(buttonRect.top - selectRect.top) < 2];
                }),
                clearFilters: Array.from(form.querySelectorAll('a, button')).some(control => control.textContent.trim() === 'Clear filters')
            };
        });
        assert.deepEqual(actual, {clearButtons: [['Clear category filter', 'Clear', true], ['Clear priority filter', 'Clear', true]], clearFilters: false});
    };
    await visit(start, filter);
    await checkButtons();
    await check('Others', 'Needs Review', []);
    await clear('priority');
    await check('Others', '', ['QA-Critical']);
    await change('priority', 'Needs Review');
    await check('Others', 'Needs Review', []);
    await clear('category');
    await check('', 'Needs Review', ['QA-Review']);
    await clear('priority');
    await check('', '', ['QA-Critical', 'QA-Review']);
    await change('category', 'Others');
    await change('priority', 'Urgent');
    await check('Others', 'Urgent', ['QA-Critical']);
    const time = await run('performance.timeOrigin');
    await fromBrowser(() => {
        const input = document.getElementById('incident_filter_search');
        input.value = 'QA-Critical';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        document.querySelector('button[aria-label="Clear priority filter"]').click();
    });
    await waitFor(ready+' && performance.timeOrigin !== '+time+' && '+filter);
    await check('Others', '', ['QA-Critical'], 'QA-Critical');
    await send('Emulation.setDeviceMetricsOverride', {width: 375, height: 820, deviceScaleFactor: 1, mobile: true});
    await refresh();
    await checkButtons();
    await check('Others', '', ['QA-Critical'], 'QA-Critical');
    console.log('Chrome verified both Clear buttons reset only their dropdown, preserve Search and the status tab, and update the displayed incidents. Layout verified on desktop and mobile.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
