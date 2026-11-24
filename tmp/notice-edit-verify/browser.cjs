const assert = require('node:assert/strict');

async function verify() {
    const origin = 'http://127.0.0.1:8032';
    const tab = (await (await fetch('http://127.0.0.1:9228/json/list')).json()).find(item => item.type === 'page');
    const ws = new WebSocket(tab.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', reject, { once: true });
    });
    const calls = new Map();
    let nextId = 0;
    ws.addEventListener('message', ({ data }) => {
        const reply = JSON.parse(data);
        const request = calls.get(reply.id);
        if (!request) return;
        calls.delete(reply.id);
        reply.error ? request.reject(new Error(reply.error.message)) : request.resolve(reply.result);
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        calls.set(++nextId, { resolve, reject });
        ws.send(JSON.stringify({ id: nextId, method, params }));
    });
    const run = async expression => {
        const reply = await send('Runtime.evaluate', { expression, returnByValue: true });
        if (reply.exceptionDetails) throw new Error(reply.result.description);
        return reply.result.value;
    };
    const waitFor = async condition => {
        for (let attempt = 0; attempt < 100; attempt++) {
            try {
                if (await run(`Boolean(document.readyState==='complete' && (${condition}))`)) return;
            } catch (error) {
                if (!/context|navigat/i.test(error.message)) throw error;
            }
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        throw new Error(`Expected consumer filter state at ${await run('location.href')}`);
    };
    const noticeUrl = `${origin}/consumers?account_category=active&notice_search=MT-DETAILS-QA`;
    const edit = `document.querySelector('#disconnection-records [data-notice-edit-url]')`;
    const open = `document.querySelector('#consumerEditModal.show')`;
    const submit = `document.querySelector('[form=consumerEditForm][type=submit]')`;
    const dismiss = `document.querySelector('#consumerEditModal .modal-footer [data-bs-dismiss=modal]')`;
    const hasFocus = `document.activeElement===${open}`;
    async function checkForm(mobile) {
        const state = await run(`(() => {
            const form = document.getElementById('consumerEditForm');
            const status = document.getElementById('edit_status');
            const number = form.querySelector('#edit_account_number').getBoundingClientRect().toJSON();
            const meter = form.querySelector('#edit_meter_number').getBoundingClientRect().toJSON();
            return {url:form.action, hasStatus:new FormData(form).has('status'), statusVisible:status.getClientRects().length!==0, number, meter, name:form.querySelector('#edit_full_name').value, membership:form.querySelector('#edit_membership_type').value, contact:form.querySelector('#edit_contact_number').value};
        })()`);
        assert.match(state.url,/\/disconnection-notice\/profile$/);
        assert.ok(!state.hasStatus && !state.statusVisible);
        assert.equal(state.membership,'Member');
        assert.ok(state.name.length>0);
        assert.ok(Math.abs(state.number.width-state.meter.width)<2);
        assert.ok(mobile ? state.number.top<state.meter.top : Math.abs(state.number.top-state.meter.top)<2);
        return state;
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        const {name, value} = require('./session.json');
        assert.ok((await send('Network.setCookie', {name, value, url:`${origin}/`, httpOnly:true})).success);
        await send('Emulation.setDeviceMetricsOverride', {width:1366, height:900, deviceScaleFactor:1, mobile:false});
        await send('Page.navigate', {url:noticeUrl});
        await waitFor(`${edit} && window.bootstrap?.Modal`);
        await run(`${edit}.click()`);
        await waitFor(hasFocus);
        const initial = await checkForm(false);
        await run(`document.getElementById('edit_full_name').value='N'.repeat(260); ${submit}.click();`);
        await waitFor(`${hasFocus} && document.body.innerText.includes('must not be greater than 255 characters')`);
        assert.equal(await run('location.href'), noticeUrl);
        await checkForm(false);
        await run(`document.getElementById('edit_full_name').value=${JSON.stringify(initial.name)}; document.getElementById('edit_contact_number').value='09171234567'; ${submit}.click();`);
        await waitFor(`document.body.innerText.includes('Consumer details updated successfully.') && !${open}`);
        assert.equal(await run('location.href'), noticeUrl);
        console.log('Desktop: status is absent from the notice edit submission, validation reopens the same form, and the updated contact saves.');

        await send('Emulation.setDeviceMetricsOverride', {width:390, height:820, deviceScaleFactor:1, mobile:true});
        await waitFor('window.visualViewport.width <= 400');
        await run(`${edit}.click()`);
        await waitFor(hasFocus);
        const saved = await checkForm(true);
        assert.equal(saved.contact, '09171234567');
        await run(`${dismiss}.click()`);
        await waitFor(`!${open} && !document.querySelector('.modal-backdrop')`);
        const details = await run(`(() => {const row=${edit}.closest('tr'); const button=row.querySelector('[data-bs-target^="#disconnectionNoticeDetails-"]'); button.click(); return button.dataset.bsTarget;})()`);
        await waitFor(`document.activeElement===document.querySelector('${details}.show')`);
        assert.match(await run(`document.querySelector('${details}').innerText`), /Disconnection Notice Details/);
        console.log('Mobile: other fields still display and retain their saved values. View Details still opens its own modal.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
