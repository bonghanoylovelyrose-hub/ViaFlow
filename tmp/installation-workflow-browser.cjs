
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
        reply.error ? request.reject(reply.error) : request.resolve(reply.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        requests.set(++id, { resolve, reject });
        ws.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.result.description);
        return result.result.value;
    };
    const until = async expression => {
        for (let index = 0; index < 120; index++) {
            try {
                const result = await evaluate(expression);
                if (result) return result;
            } catch (error) {
                if (!/context|navigat/i.test(error.message)) throw error;
            }
            await delay(250);
        }
        throw new Error('Timed out on '+expression+' at '+await evaluate('location.href')+' '+await evaluate('document.body.innerText.slice(0, 350)'));
    };
    const visit = async (path, expression) => {
        await send('Page.navigate', { url: origin + path });
        await until('location.pathname === ' + JSON.stringify(path.split('?')[0]) + ' && (' + expression + ')');
    };
    const accountRow = '(account => Array.from(document.querySelectorAll("table tbody tr")).find(row => Array.from(row.cells).some(cell => cell.textContent.trim() === account)))';
    const row = account => accountRow+'('+JSON.stringify(account)+')';
    const scheduled = row('BROWSER-SCHEDULED');
    const unscheduled = row('BROWSER-UNSCHEDULED');
    const inspect = selector => evaluate('(() => {const details = '+selector+'.querySelector(".consumer-installation-details"); const badge = details.querySelector(".badge"); return {text: details.innerText, color: getComputedStyle(badge).backgroundColor, display: getComputedStyle(details).display, hasButton: Boolean(details.querySelector("[data-installation-complete-trigger]"))}})()');
    const refresh = async () => {
        const loadedAt = await evaluate('performance.timeOrigin');
        await send('Page.reload');
        await until('performance.timeOrigin !== '+loadedAt+' && document.readyState === "complete"');
    };
    await send('Page.enable');
    if (!(await evaluate('location.origin === '+JSON.stringify(origin)+' && location.pathname !== "/login"'))) {
        await visit('/login', 'document.querySelector("#login")');
        await evaluate('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value = "admin@example.com"; document.querySelector("#password").value = "password"; document.querySelector("#login").form.requestSubmit();');
        await until('location.pathname === "/dashboard"');
    }
    await visit('/loans', unscheduled);
    await until('Boolean(window.bootstrap)');
    const initial = await inspect(unscheduled);
    assert.equal(initial.text, 'Not Scheduled');
    assert.equal(initial.hasButton, false);
    console.log('Loan Management renders unscheduled records without completion buttons.');
    const date = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    const expectedSchedule = new Date(date+'T00:00:00Z').toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric', timeZone:'UTC' });
    if (!(await inspect(scheduled)).hasButton) {
        assert.deepEqual(await inspect(scheduled), initial);
        await evaluate(scheduled+'.querySelector(\'[data-bs-target^="#combinedLoanSchedule"]\').click()');
        await until('document.querySelector("#combinedLoanSchedule1.show")');
        await evaluate('document.querySelector("#combined-meter-1").value = "BROWSER-NEW-METER"; document.querySelector("#combined-date-1").value = '+JSON.stringify(date)+'; document.querySelector("#combined-time-1").value = "14:30"; document.querySelector("#combinedLoanSchedule1 form").requestSubmit()');
    }
    await until(scheduled+'?.querySelector("[data-installation-complete-trigger]")');
    const pending = await inspect(scheduled);
    assert.deepEqual(pending, { text: 'Pending Installation\nScheduled Date: '+expectedSchedule+'\nMark Complete', color: 'rgb(7, 84, 200)', display: 'flex', hasButton: true });
    const active = '/consumers?account_category=active';
    await visit(active, scheduled);
    await until('Boolean(window.bootstrap)');
    assert.deepEqual(await inspect(scheduled), pending);
    assert.deepEqual(await inspect(unscheduled), initial);
    console.log('Meter assignment and scheduling show the same blue badge, date, and button on both pages.');
    await visit('/loans', scheduled+'?.querySelector("[data-installation-complete-trigger]")');
    await until('Boolean(window.bootstrap)');
    const trigger = scheduled+'.querySelector("[data-installation-complete-trigger]")';
    await evaluate(trigger+'.click()');
    await until('document.activeElement === document.querySelector("#installationCompletionModal.show")');
    assert.match(await evaluate('document.querySelector("#installationCompletionPrompt").textContent'), /physically installed/);
    assert.equal(await evaluate('document.querySelector("#installationCompletionAccount").textContent'), 'BROWSER-SCHEDULED');
    assert.equal(await evaluate('document.querySelector("#installationCompletionDate").textContent'), expectedSchedule);
    await evaluate('document.querySelector("#installationCompletionCancel").click()');
    await until('!document.querySelector("#installationCompletionModal.show")');
    await refresh();
    await until(trigger);
    assert.deepEqual(await inspect(scheduled), pending);
    console.log('Cancel leaves the installation pending after refresh.');
    await until('Boolean(window.bootstrap)');
    await evaluate(trigger+'.click()');
    await until('document.activeElement === document.querySelector("#installationCompletionModal.show")');
    await evaluate('document.querySelector("#installationCompletionConfirm").click()');
    await until(scheduled+'?.querySelector(".badge.bg-success")');
    const completed = await inspect(scheduled);
    assert.match(completed.text, /^Completed\nCompleted Date: /);
    assert.equal(completed.hasButton, false);
    await refresh();
    await until(scheduled+'?.querySelector(".badge.bg-success")');
    assert.deepEqual(await inspect(scheduled), completed);
    await visit(active, scheduled+'?.querySelector(".badge.bg-success")');
    assert.deepEqual(await inspect(scheduled), completed);
    await refresh();
    await until(scheduled+'?.querySelector(".badge.bg-success")');
    assert.deepEqual(await inspect(scheduled), completed);
    console.log('Confirmation persisted on both pages after refresh: '+completed.text.replace('\n', ' / '));
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });

