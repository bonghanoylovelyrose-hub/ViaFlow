
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



    await send('Page.enable'); await send('Network.enable'); await send('Network.clearBrowserCookies');
    const form = 'document.querySelector("#account-filters.active-account-filters")';
    const setWidth = async width => {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 900, deviceScaleFactor: 1, mobile: width < 768});
        await waitFor('window.innerWidth === '+width);
        await run('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    };
    await setWidth(1366);
    {
        await login('admin@example.com', 'password');
    }
    const path = '/consumers?account_category=active&search=CLEAR-QA&membership=member&installation_status=pending';
    await visit(path, form);
    await run('document.fonts.ready.then(() => true)');
    const snapshot = () => fromBrowser(() => {
        const form = document.getElementById('account-filters');
        const get = key => form.querySelector(key);
        const bounds = element => {
            const {left, top, right, bottom, width, height} = element.getBoundingClientRect();
            return {left, top, right, bottom, width, height};
        };
        const controls = ['#search', '#membership', '#installation_status', 'button[name=membership]', 'button[name=installation_status]'].map(get);
        const sizes = controls.map(element => ({
            ...bounds(element), radius: getComputedStyle(element).borderTopLeftRadius,
            textFit: element.tagName === 'SELECT' ? (() => {
                const canvas = document.createElement('canvas').getContext('2d');
                const style = getComputedStyle(element);
                canvas.font = [style.fontWeight, style.fontSize, style.fontFamily].join(' ');
                const remaining = element.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
                return Array.from(element.options).map(option => Math.round(remaining - canvas.measureText(option.textContent.trim()).width));
            })() : null
        }));
        return {form: bounds(form), labels: Array.from(form.querySelectorAll("label"), bounds), controls: sizes, overflow: form.scrollWidth - form.clientWidth};
    });
    const widths = [1920,1366,1280,1173,1160,1024,768,390,320];
    for (const width of widths) {
        await setWidth(width);
        const state = await snapshot();
        const [search, membership, installation, memberClear, installClear] = state.controls;
        const aligned = Math.abs(search.top - membership.top) < 1 && Math.abs(search.top - installation.top) < 1;
        console.log(JSON.stringify({screen:width,form:Math.round(state.form.width),select:Math.round(installation.width),heights:state.controls.map(item=>item.height),radii:state.controls.map(item=>item.radius),textRoom:[membership.textFit,installation.textFit],aligned}));
        assert.ok(state.controls.every(item=>item.left >= state.form.left - 1 && item.right <= state.form.right + 1));
        assert.ok(state.overflow <= 1); assert.ok(state.labels.every(label => label.top >= state.form.top - 1 && label.left >= state.form.left - 1 && label.right <= state.form.right + 1)); assert.ok([...membership.textFit, ...installation.textFit].every(room => room >= 0));
        assert.ok(state.controls.every(item => Math.abs(item.height - search.height) < 1 && item.radius === search.radius));
        assert.ok(Math.abs(membership.width - installation.width) < 1);
        for (const [select, button] of [[membership,memberClear],[installation,installClear]]) {
            assert.ok(Math.abs(select.top - button.top) < 1);
            assert.ok(Math.abs(button.left - select.right - 8) < 1);
        }
        if (width >= 1280) assert.ok(aligned);
        if (width <= 390) assert.ok(search.top < membership.top && membership.top < installation.top);
    }
    await setWidth(1366);
    const clip = await fromBrowser(() => {
        const form = document.getElementById('account-filters');
        const rect = form.getBoundingClientRect();
        return {x: rect.left + scrollX - 8, y: rect.top + scrollY - 24, width: rect.width + 16, height: rect.height + 48, scale: 1};
    });
    const capture = await send('Page.captureScreenshot', {clip, captureBeyondViewport: true});
    require('node:fs').writeFileSync('tmp/active-account-filter-layout.png', Buffer.from(capture.data,'base64'));
    const clear = async (name, expected) => {
        const time = await run('performance.timeOrigin');
        await fromBrowser(name => document.querySelector('#account-filters button[name="'+name+'"]').click(), name);
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && '+form);
        const values = await fromBrowser(() => {
            const form = document.getElementById('account-filters');
            return [form.querySelector('#search').value, form.querySelector('#membership').value, form.querySelector('#installation_status').value];
        });
        assert.deepEqual(values, expected);
    };
    await clear('installation_status',['CLEAR-QA','member','all']);
    await visit(path, form);
    await clear('membership',['CLEAR-QA','','pending']);
    console.log('Chrome verified responsive filter geometry at nine widths and both independent Clear actions.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
