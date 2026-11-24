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
    const sessions = require('./sessions.json');
    const page = `${origin}/incidents`;
    const table = `document.querySelector('.incident-table')`;
    const rows = `document.querySelectorAll('.incident-table tbody tr')`;
    const empty = `document.querySelector('.incident-empty')`;
    const search = `document.getElementById('incident_filter_search')`;
    const category = `document.getElementById('incident_filter_category')`;
    const statuses = `document.querySelector('nav[aria-label="Filter incident reports by status"]')`;
    const query = `new URLSearchParams(location.search)`;
    async function visit(scenario) {
        const {name, value} = sessions[scenario];
        assert.ok((await send('Network.setCookie',{name,value,url:`${origin}/`,httpOnly:true})).success);
        await send('Page.navigate',{url:page});
        await waitFor(scenario==='empty' ? `${empty} && ${empty}.textContent.trim()==='No incident reports yet'` : `${rows}.length===10`);
    }
    async function snap(name) {
        const {data} = await send('Page.captureScreenshot');
        fs.writeFileSync(path.join(__dirname,name+'.png'),Buffer.from(data,'base64'));
    }
    async function geometry() {
        return run(`(() => {
            const box = target => target?.getBoundingClientRect().toJSON();
            const cards = [...document.querySelectorAll('.incident-panels > section')].map(box);
            const search = ${search};
            const category = ${category};
            const clear = category.parentElement.querySelector('button');
            const empty = ${empty};
            return {cards, search:box(search), category:box(category), clear:box(clear), empty:box(empty), centered:empty && getComputedStyle(empty).textAlign==='center',
                viewport:innerWidth, rootWidth:document.documentElement.scrollWidth, visibleMobileLabel:document.querySelector('.incident-cell-label')?.getClientRects().length,
                table:box(${table}), frame:box(document.querySelector('.incident-table-frame')), firstRow:box(${rows}[0]),
                tabs:[...${statuses}.querySelectorAll('a')].map(link=>link.innerText.trim()), selected:${statuses}.querySelector('[aria-current=page]').innerText.trim()};
        })()`);
    }
    async function checkControls(layout, mobile) {
        const [snapshot, reports] = layout.cards;
        assert.ok(Math.abs(snapshot.left-reports.left)<1 && Math.abs(snapshot.right-reports.right)<1);
        assert.ok(reports.top-snapshot.bottom>=12 && reports.top-snapshot.bottom<=24);
        assert.ok(snapshot.height<145 && layout.rootWidth<=layout.viewport+1);
        assert.deepEqual(layout.tabs,['All','Pending','Resolved']);
        assert.equal(layout.selected,'All');
        assert.ok(layout.clear.left-layout.category.right<12 && layout.clear.left>=layout.category.right);
        assert.ok(Math.abs(layout.category.height-layout.clear.height)<2 && Math.abs(layout.search.height-layout.category.height)<2);
        assert.ok(mobile ? layout.search.bottom<layout.category.top : Math.abs(layout.search.bottom-layout.category.bottom)<2);
    }

    try {
        await send('Page.enable');
        await send('Network.enable');
        await send('Network.clearBrowserCookies');
        for (const [width,mobile] of [[1440,false],[820,true],[320,true],[390,true]]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile});
            await visit('empty');
            await waitFor(`Math.abs(window.innerWidth - ${width}) <= 1`);
            const blank = await geometry();
            await checkControls(blank,width<576);
            assert.ok(blank.centered && blank.empty.height<75 && !blank.table);
            await snap(`${width}-empty`);

            await visit('populated');
            const full = await geometry();
            await checkControls(full,width<576);
            assert.ok(full.cards[1].height>blank.cards[1].height);
            assert.ok(full.table.right <= full.frame.right+1);
            assert.ok(await run("(" + rows + ")[0].querySelector('.btn').scrollWidth <= (" + rows + ")[0].querySelector('.btn').clientWidth"));
            assert.equal(Boolean(full.visibleMobileLabel),width<768);
            assert.ok(full.firstRow.height < (width<=320?310:width<768?240:120), `${width}px row height ${full.firstRow.height}`);
            await snap(`${width}-reports`);
            console.log(`${width}px: aligned cards, compact empty state, aligned controls, and 10 readable report rows.`);
        }

        await run(`document.querySelector('.incident-panels a[rel=next], .incident-panels a[href*="page=2"]').click()`);
        await waitFor(`${query}.get('page')==='2' && ${rows}.length===2`);
        assert.ok(await run(`document.body.innerText.includes('Showing 11 to 12 of 12 incident reports')`));
        await run(`${statuses}.querySelector('[href*="status=Resolved"]').click()`);
        await waitFor(`${query}.get('status')==='Resolved' && ${rows}.length===6`);
        await run(`{const select=${category}; select.value='Water Leak'; select.dispatchEvent(new Event('change',{bubbles:true}));}`);
        await waitFor(`${query}.get('category')==='Water Leak' && ${rows}.length===3`);
        await run(`{const input=${search}; input.value='Via Vida Lane 8'; input.dispatchEvent(new Event('input',{bubbles:true}));}`);
        await waitFor(`${query}.get('search')==='Via Vida Lane 8' && ${empty}?.textContent.includes('No incident reports match your filters')`);
        await run(`document.querySelector('[aria-label="Clear category filter"]').click()`);
        await waitFor(`${query}.get('status')==='Resolved' && ${query}.get('search')==='Via Vida Lane 8' && ${query}.getAll('category').at(-1)==='' && ${category}.selectedIndex===-1 && ${rows}.length===1`);
        await run(`${rows}[0].querySelector('[data-bs-target="#incidentStatusModal"]').click()`);
        await waitFor(`document.activeElement===document.querySelector('#incidentStatusModal.show')`);
        assert.match(await run(`document.getElementById('incidentStatusModal').innerText`),/Via Vida Lane 8/);
        await run(`document.querySelector('#incidentStatusModal .modal-header .btn-close').click()`);
        await waitFor(`!document.querySelector('#incidentStatusModal.show') && !document.querySelector('.modal-backdrop')`);
        await run(`document.querySelector('.incident-filters > a').click()`);
        await waitFor(`!location.search && ${rows}.length===10`);
        await run(`document.querySelector('[data-bs-target="#incidentModal"]').click()`);
        await waitFor(`document.activeElement===document.querySelector('#incidentModal.show')`);
        assert.ok(await run(`document.getElementById('incidentCreateForm').querySelector('textarea[name=description]')`));
        console.log('Pagination, status, category, Search, both Clear actions, View, and Submit New Report verified.');
    } finally {
        await send('Network.clearBrowserCookies');
        ws.close();
    }
}

verify().catch(error => {
    console.error(error);
    process.exitCode = 1;
});





