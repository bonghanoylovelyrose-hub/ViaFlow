
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

    const fs = require('node:fs');
    const fixture = JSON.parse(fs.readFileSync('tmp/inactive-accounts-ui-fixture.log', 'utf16le').replace(/^\uFEFF/, '').trim());
    const tabs = 'document.querySelector(\'nav[aria-label="Consumer account views"]\')';
    const accountTable = 'document.getElementById("consumer-accounts-table")';
    const historyTable = 'document.getElementById("disconnection-history-table")';
    const currentCategory = 'new URLSearchParams(location.search).get("account_category")';
    const inactive = '/consumers?account_category=inactive';
    const history = '/consumers?account_category=disconnection_history';
    const rowFor = (table, value) => 'Array.from('+table+'.tBodies[0].rows).find(row => row.innerText.includes('+JSON.stringify(value)+'))';
    const inactiveRow = rowFor(accountTable, 'TABS-INACTIVE Consumer');
    const assertTabs = async title => {
        assert.deepEqual(await run('Array.from('+tabs+'.querySelectorAll("a"), tab => tab.textContent.trim())'), ['Inactive Accounts', 'Disconnection History']);
        assert.equal(await run(tabs+'.querySelector("[aria-current=page]").textContent.trim()'), title);
        assert.equal(await run('getComputedStyle('+tabs+'.querySelector("[aria-current=page]")).backgroundColor'), 'rgb(7, 84, 200)');
    };
    const viewport = async width => {
        await send('Emulation.setDeviceMetricsOverride', { width, height: width <= 390 ? 820 : 920, deviceScaleFactor: 1, mobile: false });
        await delay(375);
    };
    const geometry = async table => run('(() => { const table = '+table+'; const wrap = table.parentElement; const tabStrip = '+tabs+'; const tabs = Array.from(tabStrip.children); const card = table.tBodies[0].rows[0]; const cells = Array.from(card.cells); const actions = Array.from(cells.at(-1).querySelectorAll("button")); return { viewport:innerWidth, pageScroll:document.documentElement.scrollWidth, tableWidth: Math.round(table.getBoundingClientRect().width), wrapperWidth:wrap.clientWidth, tableDisplay:getComputedStyle(table).display, rowDisplay:getComputedStyle(card).display, tabRows:new Set(tabs.map(tab=>Math.round(tab.getBoundingClientRect().top))).size, badgeWidth:card.querySelector(".consumer-status-badge")?.clientWidth, bodyOverflow:cells.filter(cell=>cell.scrollWidth>cell.clientWidth+2).map(cell=>cell.innerText), buttonOutsideCell:actions.filter(button=>{const box=button.getBoundingClientRect();const cell=cells.at(-1).getBoundingClientRect();return box.left < cell.left-1 || box.right > cell.right+1;}).map(button=>button.textContent.trim())}; })()');
    const screenshot = async name => {
        const shot = await send('Page.captureScreenshot', { format: 'png', fromSurface: true });
        fs.writeFileSync('tmp/inactive-accounts-ui-'+name+'.png', Buffer.from(shot.data, 'base64'));
    };
    const checkLayout = async (table, name) => {
        for (const width of [1440, 1024, 390, 320]) {
            await viewport(width);
            const box = await geometry(table);
            console.log(name, width, JSON.stringify(box));
            assert.ok(box.pageScroll <= box.viewport+1);
            assert.ok(box.tableWidth <= box.wrapperWidth+2 && box.tableWidth >= box.wrapperWidth-2);
            assert.deepEqual(box.bodyOverflow, []);
            assert.deepEqual(box.buttonOutsideCell, []);
            assert.equal(box.tabRows, 1);
            if (width <= 390) assert.equal(box.rowDisplay, 'grid');
            if (width === 1440) assert.equal(box.tableDisplay, 'table');
            if ([1440,390].includes(width)) await screenshot(name+'-'+width);
        }
    };
    await send('Page.enable');
    await viewport(1440);
    if (!(await run('location.origin === "http://127.0.0.1:8027" && Boolean(document.querySelector("#sidebarMenu"))'))) {
    await visit('/login', 'document.querySelector("#login")');
    await run('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value = "admin@example.com"; document.querySelector("#password").value = "password"; document.querySelector("#login").form.requestSubmit()');
    await waitFor('location.pathname === "/dashboard"');
    }
    await visit(inactive, inactiveRow);
    await assertTabs('Inactive Accounts');
    assert.equal(await run('document.querySelector("#consumerSubmenu [data-consumer-category=active] span").textContent.trim()'), 'Active Accounts');
    const actionLabels = await run('Array.from('+inactiveRow+'.querySelectorAll(".consumer-actions button"), button => button.textContent.trim())');
    assert.deepEqual(actionLabels, ['Reconnect', 'Edit', 'View Details']);
    assert.match(await run(inactiveRow+'.querySelector(".consumer-status-badge").innerText'), /Inactive/);
    await checkLayout(accountTable, 'accounts');
    await viewport(390);
    for (const target of ['#consumerReconnectModal', '#consumerEditModal', '#consumerDetailsModal']) {
        await run(inactiveRow+'.querySelector(\'[data-bs-target="'+target+'"]\').click()');
        await waitFor('document.activeElement === document.querySelector("'+target+'.show")');
        await run('document.querySelector("'+target+' .modal-header .btn-close").click()');
        await waitFor('!document.querySelector("'+target+'.show") && !document.body.classList.contains("modal-open")');
    }
    console.log('Reconnect, Edit and View Details opened on mobile.');
    await viewport(1440);
    await run('(() => {const input = document.querySelector("#account-filters #search"); input.value = "TABS-INACTIVE"; input.dispatchEvent(new Event("input", { bubbles: true })); })()');
    await waitFor('new URLSearchParams(location.search).get("search") === "TABS-INACTIVE" && '+ready);
    await run('(() => {const field = document.querySelector("#account-filters #membership"); field.value = "non_member"; field.dispatchEvent(new Event("change", { bubbles: true })); })()');
    await waitFor('new URLSearchParams(location.search).get("membership") === "non_member" && '+ready);
    assert.match(await run(accountTable+'.tBodies[0].innerText'), /TABS-INACTIVE Consumer/);
    assert.doesNotMatch(await run(accountTable+'.tBodies[0].innerText'), /Alexandria/);
    await run(tabs+'.querySelector(\'a[href*=disconnection_history]\').click()');
    await waitFor(currentCategory+' === "disconnection_history" && '+ready);
    await assertTabs('Disconnection History');
    const returned = await run(rowFor(historyTable, 'TABS-RECONNECTED')+'.innerText');
    assert.ok(returned.includes(fixture.disconnected_date) && returned.includes(fixture.reconnected_date), returned);
    assert.match(await run(rowFor(historyTable, 'TABS-INACTIVE')+'.innerText'), /TABS-INACTIVE Consumer/);
    await checkLayout(historyTable, 'history');
    await viewport(390);
    await run(rowFor(historyTable, 'TABS-RECONNECTED')+'.querySelector("button").click()');
    await waitFor('document.activeElement === document.querySelector(".modal.show")');
    assert.ok((await run('document.querySelector(".modal.show").innerText')).includes(fixture.reconnected_date));
    await run('document.querySelector(".modal.show .btn-close").click()');
    await waitFor('!document.querySelector(".modal.show") && !document.body.classList.contains("modal-open")');
    await run(tabs+'.querySelector(\'a[href$=inactive]\').click()');
    await waitFor(currentCategory+' === "inactive" && '+ready);
    await assertTabs('Inactive Accounts');
    await visit('/consumers?account_category=active', 'document.querySelector("#active-accounts #installation_status")');
    assert.equal(await run('Boolean('+tabs+' || document.querySelector(".inactive-records"))'), false);
    console.log('Both tabs switch correctly, history retains both account states and saved dates, and Active Accounts has its original layout.');
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
