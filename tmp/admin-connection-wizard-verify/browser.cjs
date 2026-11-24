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
    const fs = require('node:fs');
    const path = require('node:path');
    const session = require('./session.json');
    const applicant = 'Office Wizard Sample';
    const email = 'viaflow.office.wizard.qa@gmail.com';
    const meter = 'MT-OFFICE-WIZARD-QA';
    const date = new Date(Date.now()+7*86400000).toISOString().slice(0,10);
    const pending = `${origin}/consumers?account_category=pending&pending_type=applications`;
    const create = `document.getElementById('consumerCreateForm')`;
    const step = index => `${create}.querySelectorAll('[data-application-step]')[${index-1}]`;
    const setFields = data => run(`{const values=${JSON.stringify(data)}; for(const [id,value] of Object.entries(values)) {const field=document.getElementById(id); field.value=value; field.dispatchEvent(new Event('change',{bubbles:true}));}}`);
    const adminCookie = () => send('Network.setCookie',{name:session.name,value:session.value,url:`${origin}/`,httpOnly:true});
    const readEmail = () => fs.readFileSync(path.join(__dirname,'received.eml'),'utf8').replace(/=\r?\n/g,'').replace(/=([A-F\d]{2})/g,(_,hex)=>String.fromCharCode(parseInt(hex,16)));
    const emailLink = (message, portion) => [...message.matchAll(/href="([^"]+)"/g)].map(x=>x[1].replace(/&amp;/g,'&')).find(url=>url.includes(portion));
    const attach = async selector => {
        const {root} = await send('DOM.getDocument');
        const {nodeId} = await send('DOM.querySelector',{nodeId:root.nodeId,selector});
        assert.ok(nodeId,selector);
        await send('DOM.setFileInputFiles',{nodeId,files:[path.join(__dirname,'document.png')]});
    };

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('DOM.enable');
        await send('Network.clearBrowserCookies');
        assert.ok((await adminCookie()).success);
        await send('Emulation.setDeviceMetricsOverride',{width:1366,height:900,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:`${origin}/consumers`});
        await waitFor(`${create} && window.bootstrap?.Modal`);
        await run(`document.querySelector('.page-heading [data-bs-target="#consumerModal"]').click()`);
        await waitFor(`document.activeElement===document.querySelector('#consumerModal.show')`);
        assert.match(await run(`${step(1)}.innerText`),/Are you a VVTHOA member\?/);
        assert.equal(await run(`${step(2)}.hidden`),true);
        await run(`document.getElementById('viohoa_member_yes').click(); ${create}.querySelector('[data-application-next]').click()`);
        await waitFor(`!${step(2)}.hidden`);
        await setFields({first_name:'Office Wizard',last_name:'Sample',email,service_purok:'Purok 1-C',address_block:'Block 5',address_street:'Test Street',contact_number:'09171234567',property_type:'Residential',ownership_status:'Owner'});
        await run(`for (const id of ['housing_rights_documented_yes','resident_of_community_yes','association_registered_yes']) document.getElementById(id).click(); ${create}.querySelector('[data-application-next]').click()`);
        await waitFor(`!${step(3)}.hidden`);
        assert.ok(await run(`${step(3)}.innerText.includes('VVTHOA Membership Document')`));
        await run(`document.getElementById('agreement_accepted').click(); ${create}.querySelector('[data-application-submit]').click()`);
        const applicantRow = `Array.from(document.querySelectorAll('#consumer-accounts-table tbody tr')).find(row=>row.innerText.includes('${applicant}'))`;
        await waitFor(`${applicantRow}?.innerText.includes('Pending Requirements') && location.search.includes('pending_type=applications')`);
        assert.equal(await run(`!!${applicantRow}.querySelector('[data-bs-target="#consumerActivateModal"]')`),false);
        await run(`document.querySelector('#pendingTypeFilters [data-pending-type=all]').click()`);
        await waitFor(`location.search.includes('pending_type=all') && ${applicantRow}?.innerText.includes('Pending Requirements')`);
        const uploadLink = emailLink(readEmail(),'/requirements?');
        assert.ok(uploadLink?.startsWith(origin));
        console.log('Admin wizard saved a member application as Pending Requirements in Connection Applications and All Pending. The local mailbox received its signed upload link.');

        await send('Network.clearBrowserCookies');
        await send('Page.navigate',{url:uploadLink});
        await waitFor(`document.querySelector('form input#valid_id_file') && document.getElementById('membership_document_photos')`);
        await setFields({valid_id_presented:'National ID'});
        await run(`document.getElementById('membership_document_residency').click()`);
        await attach('#valid_id_file');
        await attach('#membership_document_photos');
        await run(`document.querySelector('form[enctype="multipart/form-data"] button[type=submit]').click()`);
        await waitFor(`document.body.innerText.includes('Your requirements were submitted') && !document.querySelector('#valid_id_file')`);
        assert.ok((await adminCookie()).success);
        await send('Page.navigate',{url:pending});
        await waitFor(`${applicantRow}?.innerText.includes('Pending Verification')`);
        await run(`${applicantRow}.querySelector('[data-bs-target="#consumerActivateModal"]').click()`);
        await waitFor(`document.activeElement===document.querySelector('#consumerActivateModal.show')`);
        await run(`document.getElementById('reviewNext').click()`);
        assert.ok(await run(`document.querySelector('#activateInfoContent a[href*="requirements/valid-id"]')`));
        await run(`document.getElementById('reviewNext').click(); document.querySelector('[name=review_decision][value=approve]').click(); document.getElementById('reviewNext').click()`);
        await waitFor(`!document.getElementById('consumerActivateForm').hidden`);
        await setFields({activation_membership_type:'Member',activation_meter_number:meter,activation_installation_date:date});
        await run(`document.getElementById('activation_membership_verification_confirmed').click(); document.getElementById('reviewNext').click()`);
        await waitFor(`!document.getElementById('reviewConfirm').hidden`);
        assert.ok(await run(`document.getElementById('reviewSummary').innerText.includes('${meter}')`));
        await run(`document.getElementById('reviewConfirm').click()`);
        await waitFor(`document.body.innerText.includes('Connection request approved successfully')`);
        const approvalReceipt = readEmail();
        const dateInEmail = new Date(date+'T00:00:00Z').toLocaleDateString('en-US',{timeZone:'UTC',year:'numeric',month:'long',day:'numeric'});
        assert.ok(approvalReceipt.includes(meter) && approvalReceipt.includes(dateInEmail));
        const reschedule = emailLink(approvalReceipt,'/installation/reschedule/connection/');
        assert.ok(reschedule?.startsWith(origin));
        const envelopes = fs.readFileSync(path.join(__dirname,'recipients.txt'),'utf8').trim().split('\n');
        assert.deepEqual(envelopes,[`RCPT TO:<${email}>`,`RCPT TO:<${email}>`]);
        console.log('The applicant uploaded both missing documents, Admin verified and scheduled installation, and the local mailbox received the approval email with the meter, date, and reschedule link.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}
verify().catch(error=>{ console.error(error); process.exitCode=1; });
