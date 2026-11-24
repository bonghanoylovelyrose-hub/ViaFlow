const assert = require('node:assert/strict');

async function verify() {
    const origin = 'http://127.0.0.1:8031';
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
    const sessions = require('./sessions.json');
    const reports = [
        ['Water Leak', 'Nagtulo ra ang gripo.', 'Normal'],
        ['Water Leak', 'Pumutok ang pangunahing tubo.', 'Critical'],
        ['No Water Supply', 'Walang tubig sa tatlong bahay.', 'Urgent'],
        ['No Water Supply', 'No water. Please check.', 'Needs Review'],
        ['Water Quality Issue', 'Ang tubig is hugaw kaayo.', 'Urgent'],
        ['Meter Concern', 'Sayop ang basa sa among metro.', 'Normal'],
        ['Others', 'Pakipuntahan ang kanto.', 'Critical', 'Pumutok ang pangunahing tubo'],
        ['Others', 'Pakitingnan po.', 'Needs Review', 'Mahinang agas'],
    ];
    const success = 'Incident report submitted successfully.';
    async function visit(role) {
        const {name, value} = sessions[role];
        assert.ok((await send('Network.setCookie', {name, value, url:`${origin}/`, httpOnly:true})).success);
        await send('Page.navigate', {url:`${origin}/incidents`});
        await waitFor(role === 'consumer' ? `document.querySelector('#incident-description-hint')` : `document.querySelector('#incident_priority_fields')`);
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        await visit('consumer');
        assert.deepEqual(await run(`Array.from(document.getElementById('category').options, option=>option.value)`), ['Water Leak','No Water Supply','Water Quality Issue','Meter Concern','Others']);
        for (const [category, description, priority, customType = ''] of reports) {
            const details = await run(`(() => {
                const picker = document.getElementById('category');
                const type = document.getElementById('custom_incident_type');
                const description = document.getElementById('description');
                picker.value = ${JSON.stringify(category)};
                picker.dispatchEvent(new Event('change', {bubbles:true}));
                type.value = '';
                const details = {typeRequired:type.required, typeHidden:type.closest('#custom_incident_type_wrapper').hidden, canSkipType:type.checkValidity(), freeText:description.tagName==='TEXTAREA' && !description.readOnly};
                type.value = ${JSON.stringify(customType)};
                description.value = ${JSON.stringify(description)};
                document.getElementById('location').value = 'Via Vida';
                return details;
            })()`);
            assert.equal(details.typeRequired, category === 'Others');
            assert.equal(details.typeHidden, category !== 'Others');
            assert.equal(details.canSkipType, category !== 'Others');
            assert.ok(details.freeText);
            await run(`window.qaSubmitted = true; document.querySelector('button[form=incidentCreateForm]').click()`);
            await waitFor(`!window.qaSubmitted && document.body.innerText.includes(${JSON.stringify(success)})`);
        }
        await visit('admin');
        const shown = await run(`Array.from(document.querySelectorAll('tr button[data-classification-reason]'), button=>({category:button.dataset.incidentCategory, description:button.dataset.incidentDescription, priority:button.dataset.priority, automatic:button.dataset.automaticPriority, reason:button.dataset.classificationReason, badge:button.closest('tr').querySelector('[class*=incident-priority-]').className}))`);
        for (const [category, description, priority] of reports) {
            const row = shown.find(item=>item.description === description);
            assert.ok(row, description);
            assert.equal(row.category, category);
            assert.equal(row.priority, priority);
            assert.equal(row.automatic, priority);
            assert.ok(row.reason.length > 20);
            assert.ok(row.badge.includes('incident-priority-'+priority.toLowerCase().replace(' ', '-')));
            console.log(`${category} | ${priority} | ${description}`);
        }
        assert.ok(shown.find(row=>row.description==='Ang tubig is hugaw kaayo.').reason.includes('hugaw kaayo'));
        console.log('Consumer form and Admin list verified for all five categories.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
