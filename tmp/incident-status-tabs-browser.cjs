
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
    const nav = 'document.querySelector(\'nav[aria-label="Filter incident reports by status"]\')';
    const snapshot = () => fromBrowser(() => {
        const nav = document.querySelector('nav[aria-label="Filter incident reports by status"]');
        const header = nav.previousElementSibling.getBoundingClientRect();
        const filters = nav.parentElement.nextElementSibling.getBoundingClientRect();
        const links = Array.from(nav.querySelectorAll('a'), link => {
            const rect = link.getBoundingClientRect();
            return {
                label: link.textContent.trim(),
                current: link.getAttribute('aria-current'),
                primary: link.classList.contains('btn-primary'),
                background: getComputedStyle(link).backgroundColor,
                left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom
            };
        });
        return {headerLeft: header.left, filtersTop: filters.top, links};
    });
    const checkLayout = async selected => {
        const {headerLeft, filtersTop, links} = await snapshot();
        assert.deepEqual(links.map(link => link.label), ['All', 'Pending', 'Resolved']);
        assert.deepEqual(links.filter(link => link.current === 'page' && link.primary).map(link => link.label), [selected]);
        assert.ok(Math.abs(links[0].left - headerLeft) < 1);
        assert.ok(links.every(link => Math.abs(links[0].top - link.top) < 1 && link.bottom <= filtersTop));
        assert.ok(links[1].left > links[0].right);
        assert.ok(Math.abs((links[1].left - links[0].right) - (links[2].left - links[1].right)) < 1);
        const selectedLink = links.find(link => link.label === selected);
        assert.ok(links.filter(link => link.label !== selected).every(link => link.background !== selectedLink.background));
    };
    const rows = () => fromBrowser(() => Array.from(document.querySelectorAll('[data-priority-url]'), row => row.dataset.incidentStatus));
    const click = async (label, status) => {
        const oldTime = await run('performance.timeOrigin');
        await fromBrowser(label => Array.from(document.querySelectorAll('nav[aria-label="Filter incident reports by status"] a')).find(link => link.textContent.trim() === label).click(), label);
        await waitFor(ready+' && performance.timeOrigin !== '+oldTime+' && new URLSearchParams(location.search).get("status") === '+JSON.stringify(status));
        await checkLayout(label);
    };
    await visit('/incidents', nav);
    await waitFor(nav+'.querySelector("a").getBoundingClientRect().right < '+nav+'.querySelector("a + a").getBoundingClientRect().left');
    await checkLayout('All');
    assert.deepEqual(Array.from(new Set(await rows())).sort(), ['Pending', 'Resolved']);
    await click('Pending', 'Pending');
    assert.deepEqual(Array.from(new Set(await rows())), ['Pending']);
    await click('Resolved', 'Resolved');
    assert.deepEqual(Array.from(new Set(await rows())), ['Resolved']);
    await click('All', null);
    await visit('/incidents?search=QA-&category=Others&priority=Urgent', nav);
    await click('Pending', 'Pending');
    assert.deepEqual(await fromBrowser(() => Object.fromEntries(new URLSearchParams(location.search))), {status: 'Pending', search: 'QA-', category: 'Others', priority: 'Urgent'});
    await click('All', null);
    assert.deepEqual(await fromBrowser(() => Object.fromEntries(new URLSearchParams(location.search))), {search: 'QA-', category: 'Others', priority: 'Urgent'});
    assert.equal((await rows()).length, 1);
    await send('Emulation.setDeviceMetricsOverride', {width: 375, height: 820, deviceScaleFactor: 1, mobile: true});
    await refresh();
    await checkLayout('All');
    console.log('Chrome verified left alignment and equal spacing above the filters at desktop and mobile widths, All selected by default, each status filter, and preserved search/category/priority selections.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
