
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
    await login('admin@example.com', 'password');
    await visit('/consumers?account_category=active&search=CLEAR-QA', 'document.getElementById("account-filters")');
    for (const width of [1920,1440,1366,1340,1280,1024,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});
        await run('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        const data = await fromBrowser(() => {
            const table = document.getElementById('consumer-accounts-table');
            const form = document.getElementById('account-filters');
            const viewport = table.parentElement;
            const offset = form.getBoundingClientRect().left;
            const heads = Array.from(table.tHead.rows[0].cells).filter(cell=>/membership|installation details/i.test(cell.textContent));
            return {parentWidth:form.parentElement.clientWidth,viewport:[viewport.className, viewport.clientWidth,viewport.scrollWidth],
                headerLeft:heads.map(cell=>cell.getBoundingClientRect().left - offset + parseFloat(getComputedStyle(cell).paddingLeft))};
        });
        console.log(JSON.stringify({width,...data}));
    }
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
