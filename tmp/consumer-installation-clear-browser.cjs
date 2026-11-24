
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
    await send('Emulation.setDeviceMetricsOverride',{width:1366,height:900,deviceScaleFactor:1,mobile:false});
    if (await run('location.pathname') !== '/dashboard') await login('admin@example.com', 'password');
    const form = 'document.querySelector("#account-filters")';
    const active = form+'?.querySelector("#installation_status")';
    const base = '/consumers?account_category=active&search=CLEAR-QA&membership=member&installation_status=completed&notice_search=Keep+notice';
    const inspect = () => fromBrowser(() => {
        const form = document.getElementById('account-filters');
        const membership = form.querySelector('#membership');
        const installation = form.querySelector('#installation_status');
        return {
            membership: membership.value,
            installation: installation.value,
            label: installation.selectedOptions[0].textContent.trim(),
            noticeSearch: form.elements.notice_search.value,
            search: form.elements.search.value,
            accountCategory: form.elements.account_category.value,
            rows: Array.from(document.querySelectorAll('#consumer-accounts-table tbody tr')).filter(row => row.cells.length > 1).map(row => row.cells[1].textContent.trim()).sort()
        };
    });
    const check = async (membership, installation, rows, search = 'CLEAR-QA') => {
        assert.deepEqual(await inspect(), {
            membership, installation, label: installation === 'all' ? 'All Installations' : 'Completed',
            noticeSearch: 'Keep notice', search, accountCategory: 'active', rows
        });
    };
    const checkButtons = async () => {
        const snapshot = await fromBrowser(() => {
            const form = document.getElementById('account-filters');
            return Array.from(form.querySelectorAll('button[aria-label^="Clear"],a'), button => {
                const select = button.parentElement.querySelector("select");
                const buttonBox = button.getBoundingClientRect();
                const selectBox = select?.getBoundingClientRect();
                return {
                    label: button.innerText.trim(), icon: !!button.querySelector('i'),
                    beside: !!selectBox && selectBox.right < buttonBox.left && Math.abs(buttonBox.top - selectBox.top) < 2
                };
            });
        });
        assert.deepEqual(snapshot, [{label:'Clear',icon:false,beside:true},{label:'Clear',icon:false,beside:true}]);
    };
    const clear = async label => {
        const time = await run('performance.timeOrigin');
        await fromBrowser(label => document.querySelector('#account-filters button[aria-label="'+label+'"]').click(), label);
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && '+active);
    };
    const complete = 'CLEAR-QA-MEMBER-COMPLETE';
    const pending = 'CLEAR-QA-MEMBER-PENDING';
    const other = 'CLEAR-QA-NONMEMBER-COMPLETE';
    await visit(base, active);
    await checkButtons();
    await check('member', 'completed', [complete]);
    await clear('Clear installation status filter');
    await check('member', 'all', [complete, pending]);
    await visit(base, active);
    await clear('Clear membership filter');
    await check('', 'completed', [complete, other]);
    await clear('Clear installation status filter');
    await check('', 'all', [complete, pending, other]);
    await visit(base, active);
    const time = await run('performance.timeOrigin');
    await fromBrowser(() => {
        const search = document.querySelector('#account-filters input[name="search"]');
        search.value = 'CLEAR-QA-MEMBER';
        search.dispatchEvent(new Event('input',{bubbles:true}));
        document.querySelector('button[aria-label="Clear installation status filter"]').click();
    });
    await waitFor(ready+' && performance.timeOrigin !== '+time+' && '+active);
    await check('member', 'all', [complete, pending], 'CLEAR-QA-MEMBER');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await refresh();
    await checkButtons();
    await check('member', 'all', [complete, pending], 'CLEAR-QA-MEMBER');
    await visit('/consumers?account_category=inactive&search=CLEAR-QA', form);
    assert.equal(await run(form+'.querySelector("a").innerText.trim()'), 'Clear filters');
    console.log('Chrome verified both icon-free Clear buttons beside their dropdowns, independent resets that preserve Search and notice filters, the updated rows, and mobile alignment.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
