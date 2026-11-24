
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
    await send('Network.enable');
    const message = 'This incident has been resolved. Priority classification can no longer be modified.';
    const report = (location, extra = '') => 'Array.from(document.querySelectorAll("[data-incident-location]'+extra+'")).find(button => button.dataset.incidentLocation === '+JSON.stringify(location)+')';
    const open = async (location, extra) => {
        await waitFor(report(location, extra));
        await run(report(location, extra)+'.click()');
        await waitFor('document.activeElement === document.querySelector("#incidentStatusModal.show")');
    };
    const close = async () => {
        await run('document.querySelector("#incidentStatusModal .modal-footer [data-bs-dismiss]").click()');
        await waitFor('!document.querySelector("#incidentStatusModal.show, .modal-backdrop")');
    };
    const controlState = () => fromBrowser(() => {
        return Array.from(document.querySelectorAll('#incident_priority_fields select, #incident_priority_fields textarea, #incident_priority_fields button'), control => control.matches(':disabled'));
    });
    const checkOpen = async () => {
        assert.deepEqual(await controlState(), [false, false, false]);
        assert.equal(await run('document.querySelector("#incident_priority_locked_message").hidden'), true);
    };
    const checkLocked = async priority => {
        assert.deepEqual(await controlState(), [true, true, true]);
        assert.equal(await run('document.querySelector("#incident_priority_locked_message").innerText.trim()'), message);
        assert.equal(await run('document.querySelector("#incident_review_priority_label").innerText'), 'Final priority');
        assert.equal(await run('document.querySelector("#incident_review_priority").value'), priority);
    };
    const updateStatus = async (location, status, notes = '') => {
        await fromBrowser(location => {
            const review = Array.from(document.querySelectorAll('[data-priority-url]')).find(button => button.dataset.incidentLocation === location);
            review.parentElement.querySelector('[data-bs-target="#incidentResolveModal"]').click();
        }, location);
        await waitFor('document.activeElement === document.querySelector("#incidentResolveModal.show")');
        const time = await run('performance.timeOrigin');
        await fromBrowser((status, notes) => {
            document.querySelector('#incident_resolve_status').value = status;
            document.querySelector('#incident_resolve_notes').value = notes;
            document.querySelector('#incidentResolveForm').requestSubmit();
        }, status, notes);
        await waitFor('performance.timeOrigin !== '+time+' && '+ready+' && document.body.innerText.includes("Incident report updated successfully.")');
    };
    await send('Network.clearBrowserCookies');
    await login('admin@example.com', 'password');
    await visit('/incidents', report('QA-Urgent','[data-priority-url]'));
    await open('QA-Urgent','[data-priority-url]');
    await checkOpen();
    await close();
    await updateStatus('QA-Urgent','Ongoing');
    await visit('/incidents', report('QA-Urgent','[data-priority-url]'));
    await open('QA-Urgent','[data-priority-url]');
    await checkOpen();
    await close();
    await updateStatus('QA-Urgent','Resolved','Field staff repaired the leak.');
    await open('QA-Urgent','[data-priority-url]');
    await checkLocked('Critical');
    assert.equal(await run('document.querySelector("#incident_priority_review_note").value'), 'Verified immediate health risks in the field.');
    assert.match(await run('document.querySelector("#incident_priority_reviewed").innerText'), /^Reviewed .* by .+/);
    const response = await fromBrowser(async () => {
        const form = document.querySelector('#incidentPriorityForm');
        const result = await fetch(form.action, {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
            body: JSON.stringify({priority: 'Normal', priority_review_note: 'Test override.'}),
        });
        return [result.status, (await result.json()).message];
    });
    assert.deepEqual(response, [409, message]);
    await refresh();
    await open('QA-Urgent','[data-priority-url]');
    await checkLocked('Critical');
    console.log('Chrome verified editable Pending and Ongoing, locked admin resolution, visible audit history, and rejection of a direct update.');
    await send('Network.clearBrowserCookies');
    await login('INCIDENT-UI-METER','IncidentUi2026');
    await visit('/incidents', report('QA-Normal','[data-incident-resolve-url]'));
    await open('QA-Normal','[data-incident-resolve-url]');
    await run('document.querySelector("#incidentResolveForm button[type=submit]").click()');
    await waitFor('document.querySelector("#incident_review_status").textContent.trim() === "Resolved"');
    await send('Network.clearBrowserCookies');
    await login('admin@example.com','password');
    await visit('/incidents?status=Resolved', report('QA-Normal','[data-priority-url]'));
    await open('QA-Normal','[data-priority-url]');
    await checkLocked('Normal');
    assert.equal(await run('document.querySelector("#incident_priority_reviewed").innerText'), 'No admin priority review recorded.');
    assert.match(await run('document.querySelector("#incident_review_confirmed_at").innerText'), /[0-9]/);
    await close();
    await visit('/incidents', report('QA-Critical','[data-priority-url]'));
    await open('QA-Critical','[data-priority-url]');
    await checkOpen();
    console.log('Chrome verified the consumer resolution path and that the controls re-enable for another open report.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
