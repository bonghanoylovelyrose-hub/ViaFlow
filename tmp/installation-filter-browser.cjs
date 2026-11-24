
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
    const form = 'document.querySelector("#loan-application-filters")';
    const loanRows = 'Array.from(document.querySelectorAll("#loan-management-title ~ .table-responsive tbody tr")).filter(row => row.cells.length > 1)';
    const accounts = () => run(loanRows+'.map(row => row.cells[0].textContent.trim()).sort()');
    const pendingTrigger = 'document.querySelector("#loan-management-title ~ .table-responsive [data-installation-complete-trigger]")';
    const select = async (id, name, value) => {
        await run('(() => { const field = document.getElementById('+JSON.stringify(id)+'); field.value = '+JSON.stringify(value)+'; field.dispatchEvent(new Event("change", { bubbles: true })); })()');
        await waitFor('new URLSearchParams(location.search).get('+JSON.stringify(name)+') === '+JSON.stringify(value)+' && '+ready);
    };
    await send('Page.enable');
    await visit('/login', 'document.querySelector("#login")');
    await run('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value = "admin@example.com"; document.querySelector("#password").value = "password"; document.querySelector("#login").form.requestSubmit()');
    await waitFor('location.pathname === "/dashboard"');
    await visit('/loans', form);
    assert.deepEqual(await run('Array.from(document.querySelector("#installation_status").options, option => option.textContent.trim())'), ['All Installations', 'Pending Installation', 'Completed']);
    assert.equal(await run(form+'.querySelectorAll("a").length'), 1);
    assert.equal(await run(form+'.querySelector("a").textContent.trim()'), 'Clear filters');
    assert.equal((await accounts()).length, 5);
    assert.match(await run('document.querySelector("#loan-management-title ~ .table-responsive").innerText'), /FILTER-TARGET-UNSCHEDULED[\s\S]*?Not Scheduled/);
    await run('(() => { const search = document.getElementById("application-search"); search.value = "FILTER-TARGET"; search.dispatchEvent(new Event("input", { bubbles: true })); })()');
    await waitFor('new URLSearchParams(location.search).get("application_search") === "FILTER-TARGET" && '+ready);
    assert.equal((await accounts()).length, 4);
    await select('application-status', 'application_status', 'approved');
    assert.equal((await accounts()).length, 3);
    await select('installation_status', 'installation_status', 'pending');
    assert.deepEqual(await accounts(), ['FILTER-TARGET']);
    assert.match(await run('document.querySelector("#loan-management-title ~ .table-responsive .consumer-installation-details").innerText'), /^Pending Installation\nScheduled Date: .+\nMark Complete$/);
    const loanPending = await run('location.pathname + location.search');
    console.log('The three filters narrowed five loans to the one matching pending loan.');
    const active = '/consumers?account_category=active&search=FILTER-TARGET&installation_status=pending';
    const consumerDetails = 'Array.from(document.querySelectorAll("#consumer-accounts-table tbody tr")).find(row => Array.from(row.cells).some(cell => cell.textContent.trim() === "FILTER-TARGET"))?.querySelector(".consumer-installation-details")?.innerText';
    await visit(active, consumerDetails);
    const consumerBefore = await run(consumerDetails);
    assert.match(consumerBefore, /^Pending Installation\nScheduled Date: .+\nMark Complete$/);
    await visit(loanPending, pendingTrigger);
    assert.notEqual(consumerBefore, await run(pendingTrigger+'.closest(".consumer-installation-details").innerText'));
    await run(pendingTrigger+'.click()');
    await waitFor('document.activeElement === document.querySelector("#installationCompletionModal.show")');
    await run('document.querySelector("#installationCompletionConfirm").click()');
    await waitFor('document.querySelector("#loan-management-title ~ .table-responsive tbody").innerText.includes("No applications in this category.") && '+ready);
    assert.deepEqual(await accounts(), []);
    await select('installation_status', 'installation_status', 'completed');
    assert.deepEqual(await accounts(), ['FILTER-TARGET', 'FILTER-TARGET-HISTORY']);
    const completedLoan = loanRows+'.find(row => row.cells[0].textContent.trim() === "FILTER-TARGET").querySelector(".consumer-installation-details").innerText';
    assert.match(await run(completedLoan), /^Completed\nCompleted Date: .+$/);
    await refresh();
    assert.deepEqual(await accounts(), ['FILTER-TARGET', 'FILTER-TARGET-HISTORY']);
    assert.match(await run(completedLoan), /^Completed\nCompleted Date: .+$/);
    const loanCompleted = await run('location.pathname + location.search');
    await visit(active, consumerDetails);
    assert.equal(await run(consumerDetails), consumerBefore);
    await refresh();
    assert.equal(await run(consumerDetails), consumerBefore);
    console.log('Confirmed loan moved into Completed. Active Accounts kept its own pending status after refresh.');
    await visit(loanCompleted, form);
    await run(form+'.querySelector("a").click()');
    await waitFor('new URLSearchParams(location.search).size === 1 && '+ready);
    assert.equal(await run('document.querySelector("#application-search").value'), '');
    assert.equal(await run('document.querySelector("#application-status").value'), 'all');
    assert.equal(await run('document.querySelector("#installation_status").value'), 'all');
    assert.equal((await accounts()).length, 5);
    console.log('The single Clear filters button reset Search, Loan Status, and Installation Status.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });

