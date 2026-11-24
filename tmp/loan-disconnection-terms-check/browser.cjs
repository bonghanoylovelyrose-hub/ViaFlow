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
        const reply = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
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
    const sessions = require('./sessions.json');
    const expected = 'I understand that if I fail to fully pay my outstanding water meter loan balance by the end of my selected repayment term of 3, 6, or 12 months, I will receive a Disconnection Notice. I will be given three (3) days from the issuance of the notice to settle my outstanding balance. Failure to pay within this period may result in the disconnection of my water service, subject to confirmation by the VVWD Administrator.';
    async function visit(role, pathname, condition) {
        const {name, value} = sessions[role];
        assert.ok((await send('Network.setCookie', {name, value, url:`${origin}/`, httpOnly:true})).success);
        await send('Page.navigate', {url:`${origin}${pathname}`});
        await waitFor(condition);
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('DOM.enable');
        await send('Network.clearBrowserCookies');
        await visit('consumer', '/loans', `document.querySelector('[data-bs-target="#loanModal"]') && document.querySelector('#loanNextButton')`);
        await run(`document.querySelector('[data-bs-target="#loanModal"]').click()`);
        await waitFor(`document.querySelector('#loanModal').classList.contains('show')`);
        const {root} = await send('DOM.getDocument');
        const {nodeId} = await send('DOM.querySelector', {nodeId:root.nodeId, selector:'#proof_of_income'});
        await send('DOM.setFileInputFiles', {nodeId, files:[path.join(__dirname,'proof.pdf')]});
        await run(`document.getElementById('purpose').value = 'Development verification of updated loan terms'`);
        for (const term of [3, 12, 6]) {
            await run(`{ const control=document.getElementById('term_months'); control.value='${term}'; control.dispatchEvent(new Event('change',{bubbles:true})); document.getElementById('loanNextButton').click(); }`);
            assert.equal(await run(`document.getElementById('loanTermsStep').hidden`), false);
            const contents = await run(`(() => {
                const terms = document.getElementById('loanTermsStep');
                const bold = [...terms.querySelectorAll('.loan-agreement-text p > strong')];
                return { policy:bold[1].textContent, before:bold[1].parentElement.previousElementSibling.innerText, weight:Number(getComputedStyle(bold[1]).fontWeight), text:terms.innerText, consent:document.getElementById('loan_terms_accepted').required, disabled:document.getElementById('loanTermsSubmitButton').disabled, selected:document.getElementById('loan_selected_agreement_term').innerText };
            })()`);
            assert.equal(contents.policy, expected);
            assert.ok(contents.before.includes('ineligible to apply for another loan meter'));
            assert.ok(contents.weight >= 600);
            assert.match(contents.text, /issues this notice automatically.*Ready for Disconnection/s);
            assert.ok(contents.consent && contents.disabled);
            assert.equal(contents.selected, term + ' months');
            if (term !== 6) await run(`document.getElementById('loanTermsBackButton').click()`);
        }
        console.log('Actual Apply for Loan modal shows the exact bold policy and requires consent for all three repayment terms.');
        await run(`document.getElementById('loan_terms_accepted').click(); document.getElementById('loanTermsSubmitButton').click(); document.getElementById('loanConfirmButton').click()`);
        await waitFor(`document.body.innerText.includes('Your loan application was received')`);

        await visit('admin', '/loans?tab=pending&pending=income', `document.querySelector('[data-income-verification]')`);
        const loanId = await run(`document.querySelector('[data-income-verification]').dataset.incomeVerification`);
        const approval = await run(`(async () => {
            const form = document.getElementById('income-verification-template-${loanId}').content.querySelector('form');
            const token = form.querySelector('[name=_token]').value;
            const verification = await fetch(form.action,{method:'POST',body:new URLSearchParams({_token:token, identity_confirmed:'1', document_authentic:'1', income_source_verified:'1', verified_monthly_income:'6200'})});
            const approvalPage = await verification.text();
            if (!approvalPage.includes('DSS approved the application')) return false;
            const release = await fetch('/loans/${loanId}/release-installment-schedule',{method:'POST',body:new URLSearchParams({_token:token})});
            return release.ok;
        })()`);
        assert.equal(approval, true);
        await visit('consumer', '/loans', `document.querySelector('a[href*="/loans/${loanId}/agreement"][href*="download"]')`);
        const result = await run(`(async () => {
            const url = document.querySelector('a[href*="/loans/${loanId}/agreement"][href*="download"]').href;
            const response = await fetch(url);
            const bytes = new Uint8Array(await response.arrayBuffer());
            let binary = '';
            for (const byte of bytes) binary += String.fromCharCode(byte);
            return {status:response.status, type:response.headers.get('content-type'), bytes:btoa(binary)};
        })()`);
        assert.equal(result.status, 200);
        assert.equal(result.type, 'application/pdf');
        const pdf = Buffer.from(result.bytes, 'base64');
        assert.ok(pdf.subarray(0,5).equals(Buffer.from('%PDF-')));
        fs.writeFileSync(path.join(__dirname,'approved-loan.pdf'), pdf);
        console.log('Submitted and approved the six-month test application, generated its agreement, and downloaded it through My Loans.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
