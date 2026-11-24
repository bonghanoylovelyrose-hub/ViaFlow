
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

    const tabSelector = 'nav[aria-label="Consumer account views"]';
    const tabs = 'document.querySelector('+JSON.stringify(tabSelector)+')';
    const sideLinks = 'Array.from(document.querySelectorAll("#consumerSubmenu a[data-consumer-category]"))';
    const categoryTable = 'document.querySelector("#consumer-accounts-table tbody")';
    const currentCategory = 'new URLSearchParams(location.search).get("account_category")';
    const getCategories = () => run(sideLinks+'.map(link => link.dataset.consumerCategory)');
    const visitSidebar = async category => {
        await run(sideLinks+'.find(link => link.dataset.consumerCategory === '+JSON.stringify(category)+').click()');
        await waitFor('location.pathname === "/consumers" && '+currentCategory+' === '+JSON.stringify(category === 'all' ? null : category)+' && '+ready);
    };
    await send('Page.enable');
    await visit('/login', 'document.querySelector("#login")');
    await run('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value = "admin@example.com"; document.querySelector("#password").value = "password"; document.querySelector("#login").form.requestSubmit()');
    await waitFor('location.pathname === "/dashboard"');
    await visit('/consumers', categoryTable);
    assert.deepEqual(await getCategories(), ['all', 'pending', 'rejected', 'active', 'inactive']);
    for (const [category, account] of [['all', 'TABS-PENDING'], ['pending', 'TABS-PENDING'], ['rejected', 'TABS-REJECTED'], ['active', 'FILTER-TARGET']]) {
        if (category !== 'all') await visitSidebar(category);
        assert.equal(await run('Boolean('+tabs+')'), false, category);
        assert.match(await run(categoryTable+'.innerText'), new RegExp(account));
        assert.equal(await run('document.querySelector("#consumerSubmenu a[aria-current=page]").dataset.consumerCategory'), category);
        assert.deepEqual(await getCategories(), ['all', 'pending', 'rejected', 'active', 'inactive']);
        if (category === 'pending') {
            assert.equal(await run('Boolean(document.querySelector("#pendingTypeFilters"))'), true);
            await run('document.querySelector("#pendingTypeFilters [data-pending-type=applications]").click()');
            await waitFor('new URLSearchParams(location.search).get("pending_type") === "applications"');
            assert.equal(await run('Boolean('+tabs+')'), false);
        }
        if (category === 'active') {
            assert.equal(await run('Boolean(document.querySelector("#active-disconnection-notices") && document.querySelector("#active-accounts #installation_status"))'), true);
        }
    }
    await visitSidebar('inactive');
    assert.match(await run(categoryTable+'.innerText'), /TABS-INACTIVE/);
    assert.equal(await run('Boolean('+tabs+'?.getClientRects().length)'), true);
    assert.deepEqual(await run('Array.from('+tabs+'.querySelectorAll("a"), link => link.textContent.trim())'), ['Active Accounts', 'Inactive Accounts', 'Disconnection History']);
    assert.equal(await run(tabs+'.querySelector("[aria-current=page]").textContent.trim()'), 'Inactive Accounts');
    console.log('Sidebar destinations opened the correct tables. The three tabs appeared only in Inactive Accounts.');
    await run(tabs+'.querySelector("a[href*=disconnection_history]").click()');
    await waitFor(currentCategory+' === "disconnection_history" && '+ready);
    assert.equal(await run('Boolean('+tabs+')'), false);
    assert.match(await run('document.querySelector("#disconnection-history-table tbody").innerText'), /TABS-INACTIVE/);
    assert.equal(await run('Boolean(document.querySelector("#history-search") && document.querySelector("#history-status"))'), true);
    await run('document.querySelector("#disconnection-history-table button[data-bs-toggle=modal]").click()');
    await waitFor('document.querySelector(".modal.show")');
    assert.match(await run('document.querySelector(".modal.show h2").textContent'), /Disconnection Details/);
    console.log('Disconnection History opened from Inactive Accounts with its filters, record, and details modal intact.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
