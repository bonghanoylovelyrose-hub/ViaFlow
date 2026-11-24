const targets = await (await fetch('http://127.0.0.1:9241/json/list')).json();
const target = targets.find(entry => entry.type === 'page');
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
let id = 0;
const pending = new Map();
const exceptions = [];
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
    if (message.id && pending.has(message.id)) {
        const request = pending.get(message.id);
        pending.delete(message.id);
        if (message.error) request.reject(new Error(JSON.stringify(message.error)));
        else request.resolve(message.result);
    }
});
function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const requestId = ++id;
        pending.set(requestId, { resolve, reject });
        socket.send(JSON.stringify({ id: requestId, method, params }));
    });
}
async function evaluate(expression) {
    const response = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (response.exceptionDetails) throw new Error(JSON.stringify(response.exceptionDetails));
    return response.result.value;
}
await command('Page.enable');
await command('Runtime.enable');
const output = [];
for (const category of ['all', 'pending', 'rejected', 'inactive']) {
    await command('Page.navigate', { url: `file:///C:/xampp/htdocs/waterbillingsystems/tmp/account-layout-${category}.html` });
    await new Promise(resolve => setTimeout(resolve, 1200));
    const state = await evaluate(`(() => {
        const table = document.querySelector('#consumer-accounts-table');
        const rows = [...(table?.tBodies[0]?.rows ?? [])].filter(row => !row.hasAttribute('data-inactive-details-row'));
        return {
            title: document.querySelector('h1')?.textContent.trim(),
            rows: rows.length,
            names: rows.map(row => row.cells[0]?.innerText.trim().split('\\n')[0]),
            category: document.querySelector('#account-filters input[name="account_category"]')?.value || 'all',
            consumerNavigation: [...document.querySelectorAll('a[href*="account_category"]')].map(a => ({ label: a.textContent.trim(), category: new URL(a.href).searchParams.get('account_category') || 'all' })),
            filters: [...document.querySelectorAll('#account-filters input, #account-filters select')].map(el => el.name),
            pagination: [...document.querySelectorAll('.pagination a')].map(a => a.href),
            columns: [...(table?.tHead.rows[0].cells ?? [])].map(cell => cell.textContent.trim()),
            actionCounts: {
                verify: document.querySelectorAll('tbody [data-bs-target="#consumerActivateModal"]').length,
                edit: document.querySelectorAll('tbody [data-bs-target="#consumerEditModal"]').length,
                meter: document.querySelectorAll('tbody [data-bs-target="#consumerMeterModal"]').length,
                delete: document.querySelectorAll('tbody form[method="POST"] button[type="submit"]').length,
                reconnect: document.querySelectorAll('tbody [data-bs-target="#consumerReconnectModal"]').length,
                resend: document.querySelectorAll('tbody form[action*="resend-correction-link"]').length,
                viewMore: document.querySelectorAll('[data-inactive-details-toggle]').length,
            },
            secondaryDetailsInitiallyVisible: [...document.querySelectorAll('[data-inactive-details-row]')].some(row => !row.classList.contains('d-none')),
            bootstrap: typeof bootstrap !== 'undefined',
        };
    })()`);
    if (!state.title?.includes('Consumer Management') || state.rows === 0 || state.columns.length !== (category === 'inactive' ? 9 : 6)) {
        throw new Error(`${category} page data/table invalid: ${JSON.stringify(state)}`);
    }
    if (category === 'inactive') {
        const details = await evaluate(`(() => [...document.querySelectorAll('[data-inactive-details-toggle]')].map(button => {
            const row = document.getElementById(button.getAttribute('aria-controls'));
            const initiallyHidden = row.classList.contains('d-none');
            button.click();
            const expanded = !row.classList.contains('d-none') && button.getAttribute('aria-expanded') === 'true';
            const hasAddress = row.textContent.includes('Via Vida');
            button.click();
            const collapsed = row.classList.contains('d-none') && button.getAttribute('aria-expanded') === 'false';
            return { initiallyHidden, expanded, hasAddress, collapsed };
        }))()`);
        if (details.length !== state.actionCounts.viewMore || details.some(row => Object.values(row).some(value => value !== true))) {
            throw new Error(`Inactive View More failed: ${JSON.stringify(details)}`);
        }
        state.viewMoreRowsVerified = details.length;
    }
    const edit = await evaluate(`(() => {
        const trigger = document.querySelector('tbody [data-bs-target="#consumerEditModal"]');
        if (!trigger || typeof bootstrap === 'undefined') return null;
        trigger.click();
        const modal = document.querySelector('#consumerEditModal');
        return { opened: modal.classList.contains('show'), selectedName: modal.querySelector('[name="full_name"]')?.value };
    })()`);
    await new Promise(resolve => setTimeout(resolve, 500));
    if (edit && !edit.opened) edit.opened = await evaluate(`document.querySelector('#consumerEditModal').classList.contains('show')`);
    if (edit && (!edit.opened || !edit.selectedName)) throw new Error(`${category} edit detail action failed: ${JSON.stringify(edit)}`);
    state.editModal = edit;
    if (edit?.opened) {
        await evaluate('document.querySelector("#consumerEditModal .btn-close")?.click()');
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    const meter = await evaluate(`(() => {
        const trigger = document.querySelector('tbody [data-bs-target="#consumerMeterModal"]');
        if (!trigger) return null;
        trigger.click();
        const modal = document.querySelector('#consumerMeterModal');
        return { opened: modal.classList.contains('show'), consumerName: modal.textContent.includes(trigger.dataset.consumer ? JSON.parse(atob(trigger.dataset.consumer)).full_name : '') };
    })()`);
    await new Promise(resolve => setTimeout(resolve, 500));
    if (meter && !meter.opened) meter.opened = await evaluate('document.querySelector("#consumerMeterModal").classList.contains("show")');
    if (meter && !meter.opened) throw new Error(`${category} Assign Meter modal failed: ${JSON.stringify(meter)}`);
    state.assignMeterModal = meter;
    if (meter?.opened) {
        await evaluate('document.querySelector("#consumerMeterModal .btn-close")?.click()');
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    if (category === 'inactive') {
        const reconnect = await evaluate(`(() => {
            const trigger = [...document.querySelectorAll('tbody [data-bs-target="#consumerReconnectModal"]')].find(button => !button.disabled);
            if (!trigger) return { enabled: false };
            trigger.click();
            const modal = document.querySelector('#consumerReconnectModal');
            return { enabled: true, opened: modal.classList.contains('show'), selectedConsumer: modal.querySelector('[name="reconnection_date"]') !== null };
        })()`);
        await new Promise(resolve => setTimeout(resolve, 500));
        if (reconnect.enabled && !reconnect.opened) reconnect.opened = await evaluate('document.querySelector("#consumerReconnectModal").classList.contains("show")');
        if (!reconnect.enabled || !reconnect.opened) throw new Error(`Inactive Reconnect modal failed: ${JSON.stringify(reconnect)}`);
        state.reconnectModal = reconnect;
        await evaluate('document.querySelector("#consumerReconnectModal .btn-close")?.click()');
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    if (category === 'pending') {
        const verify = await evaluate(`(() => {
            const trigger = document.querySelector('tbody [data-bs-target="#consumerActivateModal"]');
            if (!trigger) return null;
            trigger.click();
            const modal = document.querySelector('#consumerActivateModal');
            return { opened: modal.classList.contains('show'), selectedName: modal.querySelector('[name="meter_number"]')?.value };
        })()`);
        await new Promise(resolve => setTimeout(resolve, 500));
        if (verify && !verify.opened) verify.opened = await evaluate('document.querySelector("#consumerActivateModal").classList.contains("show")');
        if (!verify?.opened) throw new Error(`Pending Verify modal failed: ${JSON.stringify(verify)}`);
        state.verifyModal = verify;
        await evaluate('document.querySelector("#consumerActivateModal .btn-close")?.click()');
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    output.push({ category, ...state });
}
if (exceptions.length) throw new Error(`Browser errors: ${JSON.stringify(exceptions)}`);
console.log(JSON.stringify({ output, exceptions }, null, 2));
socket.close();
