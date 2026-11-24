
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
    const form = 'document.querySelector("#account-filters.active-account-filters")';
    const path = '/consumers?account_category=active&search=CLEAR-QA&membership=member&installation_status=pending';
    const setWidth = async width => {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 900, deviceScaleFactor: 1, mobile: width < 768});
        await waitFor('window.innerWidth === '+width);
        await visit(path, form);
        await run('document.fonts.ready.then(() => true)');
        await run('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    };
    const snapshot = () => fromBrowser(() => {
        const form = document.getElementById('account-filters');
        const table = document.getElementById('consumer-accounts-table');
        const rect = element => {
            const {left,right,top,bottom,width,height} = element.getBoundingClientRect();
            return {left,right,top,bottom,width,height};
        };
        const pair = id => {
            const select = form.querySelector('#'+id);
            const group = select.closest('.account-filter-field');
            const button = group.querySelector('button');
            const label = group.querySelector('label');
            const heading = table.querySelector('[data-account-filter-column="'+id+'"]');
            const headingText = document.createRange();
            headingText.selectNodeContents(heading);
            const styles = getComputedStyle(select);
            const canvas = document.createElement('canvas').getContext('2d');
            canvas.font = [styles.fontWeight,styles.fontSize,styles.fontFamily].join(' ');
            const room = select.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight);
            return {
                select:rect(select),button:rect(button),group:rect(group),label:rect(label),
                headingText:rect(headingText),textRoom:Array.from(select.options).map(option=>Math.round(room-canvas.measureText(option.textContent.trim()).width))
            };
        };
        return {form:rect(form),viewport:rect(table.parentElement),scroll:table.parentElement.scrollLeft,
            aligned:form.classList.contains('is-column-aligned'),search:rect(form.querySelector('#search')),
            membership:pair('membership'),installation:pair('installation_status')
        };
    });
    const confirm = async (width, expected) => {
        const state = await snapshot();
        const {membership,installation} = state;
        assert.equal(state.aligned,expected);
        const offsets = [membership,installation].map(({headingText,label})=>Math.round((label.left-headingText.left)*100)/100);
        console.log(JSON.stringify({width,aligned:state.aligned,offsets,scroll:state.scroll,selectWidths:[membership.select.width,installation.select.width]}));
        for (const pair of [membership, installation]) {
            assert.ok(Math.abs(pair.select.top-pair.button.top)<1 && Math.abs(pair.select.height-pair.button.height)<1);
            assert.ok(Math.abs(pair.button.left-pair.select.right-8)<1);
            assert.ok(Math.abs(pair.label.left-pair.select.left)<1);
            assert.ok(pair.group.left >= state.form.left-1 && pair.button.right <= state.form.right+1);
            assert.ok(pair.textRoom.every(room=>room>=0));
        }
        if (expected) {
            assert.ok(offsets.every(offset=>Math.abs(offset)<1));
            assert.ok(Math.abs(membership.select.width-installation.select.width)<25);
            assert.ok(Math.abs(state.search.top-membership.select.top)<1 && Math.abs(state.search.top-installation.select.top)<1);
            assert.ok(state.search.right < membership.select.left && membership.button.right < installation.select.left);
        }
        if (width <= 390) assert.ok(state.search.top < membership.select.top && membership.select.top < installation.select.top);
    };
    for (const width of [1920,1440,1366,1280,1024,390,320]) {
        await setWidth(width);
        await confirm(width,width>=1366);
        if (width === 1366) {
            const before = await snapshot();
            await fromBrowser(() => {
                const viewport = document.getElementById('consumer-accounts-table').parentElement;
                viewport.scrollLeft = viewport.scrollWidth;
            });
            await run('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
            assert.ok((await snapshot()).scroll > before.scroll);
            await confirm(width,true);
        }
    }
    await setWidth(1366);
    const crop = await fromBrowser(() => {
        const form = document.getElementById('account-filters').getBoundingClientRect();
        const header = document.getElementById('consumer-accounts-table').tHead.getBoundingClientRect();
        return {x:form.left+scrollX-8,y:form.top+scrollY-10,width:form.width+16,height:header.bottom-form.top+20,scale:1};
    });
    const capture = await send('Page.captureScreenshot',{clip:crop,captureBeyondViewport:true});
    require('node:fs').writeFileSync('tmp/active-account-column-alignment.png',Buffer.from(capture.data,'base64'));
    const clear = async (id, expected) => {
        const moment = await run('performance.timeOrigin');
        await fromBrowser(id=>document.querySelector('#account-filters button[name="'+id+'"]').click(),id);
        await waitFor(ready+' && performance.timeOrigin !== '+moment+' && '+form);
        const values = await fromBrowser(()=>['#search','#membership','#installation_status'].map(id=>document.querySelector('#account-filters '+id).value));
        assert.deepEqual(values,expected);
    };
    await clear('installation_status',['CLEAR-QA','member','all']);
    await visit(path,form);
    await clear('membership',['CLEAR-QA','','pending']);
    console.log('Chrome verified header alignment, horizontal scroll synchronization, narrow-screen fallback, and independent Clear actions.');
    await send('Browser.close').catch(() => {});
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
