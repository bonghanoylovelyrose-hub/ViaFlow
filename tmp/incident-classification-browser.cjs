
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
    await send('Network.clearBrowserCookies');
    await login('INCIDENT-UI-METER', 'IncidentUi2026');
    await visit('/incidents', 'document.querySelector("#incidentCreateForm")');
    const samples = [
        ['Others', 'QA-Critical', 'A major pipe burst is flooding homes.'],
        ['No Water Supply', 'QA-Urgent', 'Several households are without water.'],
        ['Water Quality Issue', 'QA-Review', 'Tap problem. Please check.'],
        ['Meter Concern', 'QA-Normal', 'No exposed wires. Please run a routine meter inspection.']
    ];
    for (const [category, location, description] of samples) {
        await run('document.querySelector("[data-bs-target=\'#incidentModal\']").click()');
        await waitFor('document.querySelector("#incidentModal.show")');
        assert.equal(await fromBrowser((category, location, description) => {
            const form = document.querySelector('#incidentCreateForm');
            form.elements.category.value = category;
            form.elements.category.dispatchEvent(new Event('change', {bubbles: true}));
            const type = form.elements.custom_incident_type;
            if (category === 'Others' && (!type.required || !type.validity.valueMissing || type.parentElement.hidden)) {
                return 'The custom type was not required and visible';
            }
            if (category === 'Others') type.value = 'Water Leak';
            form.elements.location.value = location;
            form.elements.purok.value = 'Purok 1-C';
            form.elements.description.value = description;
            if (!form.checkValidity()) return Array.from(form.elements).filter(field => !field.validity.valid).map(field => field.name).join(', ');
            form.requestSubmit();
            return 'submitted';
        }, category, location, description), 'submitted');
        await waitFor(ready+' && Array.from(document.querySelectorAll("table tbody tr")).some(row => row.textContent.includes('+JSON.stringify(location)+'))');
    }
    console.log('Consumer UI required the Others incident type and submitted all four sample reports.');
    await send('Network.clearBrowserCookies');
    await login('admin@example.com', 'password');
    await visit('/incidents', 'document.querySelector("#incident_filter_priority")');
    const badges = () => fromBrowser(() => Array.from(document.querySelectorAll('table tbody tr')).filter(row => row.querySelector('[data-priority-url]')).map(row => {
        const badge = row.cells[3].querySelector('span');
        return [badge.textContent.trim(), getComputedStyle(badge).backgroundColor];
    }));
    assert.deepEqual(await badges(), [
        ['Critical', 'rgb(254, 226, 226)'],
        ['Urgent', 'rgb(255, 237, 213)'],
        ['Needs Review', 'rgb(229, 231, 235)'],
        ['Normal', 'rgb(219, 234, 254)']
    ]);
    const filter = async (id, key, value, event) => {
        await fromBrowser((id, value, event) => {
            const field = document.getElementById(id);
            field.value = value;
            field.dispatchEvent(new Event(event, {bubbles: true}));
        }, id, value, event);
        await waitFor('new URLSearchParams(location.search).get('+JSON.stringify(key)+') === '+JSON.stringify(value)+' && '+ready);
    };
    await filter('incident_filter_priority', 'priority', 'Urgent', 'change');
    assert.deepEqual((await badges()).map(([value]) => value), ['Urgent']);
    const openReview = async location => {
        await fromBrowser(location => Array.from(document.querySelectorAll('[data-priority-url]')).find(button => button.dataset.incidentLocation === location).click(), location);
        await waitFor('document.querySelector("#incidentStatusModal.show")');
    };
    await openReview('QA-Urgent');
    assert.equal(await run('document.querySelector("#incident_automatic_priority").textContent'), 'Urgent');
    assert.match(await run('document.querySelector("#incident_classification_reason").textContent'), /several households/i);
    assert.equal(await run('document.querySelector("#incident_priority_reviewed").textContent'), 'Awaiting admin review');
    await run('document.querySelector("#incidentPriorityForm").requestSubmit()');
    await waitFor(ready+' && document.body.innerText.includes("Incident priority reviewed.")');
    await openReview('QA-Urgent');
    assert.match(await run('document.querySelector("#incident_priority_reviewed").textContent'), /^Reviewed .+ by /);
    const note = 'Verified immediate health risks in the field.';
    assert.equal(await fromBrowser(note => {
        const priority = document.querySelector('#incident_review_priority');
        priority.value = 'Critical';
        priority.dispatchEvent(new Event('change', {bubbles: true}));
        const input = document.querySelector('#incident_priority_review_note');
        if (!input.required || !input.validity.valueMissing) return false;
        input.value = note;
        document.querySelector('#incidentPriorityForm').requestSubmit();
        return true;
    }, note), true);
    await waitFor(ready+' && document.body.innerText.includes("Incident priority reviewed.") && !document.querySelector("[data-priority-url]")');
    await filter('incident_filter_priority', 'priority', 'Critical', 'change');
    assert.deepEqual((await badges()).map(([value]) => value), ['Critical', 'Critical']);
    await filter('incident_filter_search', 'search', 'QA-Urgent', 'input');
    assert.deepEqual((await badges()).map(([value]) => value), ['Critical']);
    await refresh();
    await openReview('QA-Urgent');
    assert.equal(await run('document.querySelector("#incident_automatic_priority").textContent'), 'Urgent');
    assert.equal(await run('document.querySelector("#incident_review_priority").value'), 'Critical');
    assert.equal(await run('document.querySelector("#incident_priority_review_note").value'), note);
    console.log('Admin UI verified badge colors and order, combined filters, confirmation, and persistent override after refresh.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
