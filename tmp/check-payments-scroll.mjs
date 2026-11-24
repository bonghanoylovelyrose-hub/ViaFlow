const pages = await (await fetch('http://127.0.0.1:9241/json/list')).json();
const page = pages.find(target => target.type === 'page');
const socket = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
let nextId = 0;
const pending = new Map();
const errors = [];
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
    if (message.id && pending.has(message.id)) {
        const callbacks = pending.get(message.id);
        pending.delete(message.id);
        message.error ? callbacks.reject(new Error(JSON.stringify(message.error))) : callbacks.resolve(message.result);
    }
});
function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++nextId;
        pending.set(id, { resolve, reject });
        socket.send(JSON.stringify({ id, method, params }));
    });
}
async function evaluate(expression) {
    const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
}
await command('Page.enable');
await command('Runtime.enable');
await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
const results = [];
for (const [name, query] of [['all', ''], ['open', '?view=open_bills'], ['history', '?view=payment_history']]) {
    await command('Page.navigate', { url: `file:///C:/xampp/htdocs/waterbillingsystems/tmp/payments-scroll-${name}.html` });
    await new Promise(resolve => setTimeout(resolve, 1800));
    const before = await evaluate(`(() => {
        const area = document.querySelector('.admin-records-area');
        const topbar = document.querySelector('.topbar');
        const title = document.querySelector('.page-heading');
        const panelTitle = document.querySelector('#bills-and-payments-title')?.closest('[data-admin-freeze]');
        const filters = document.querySelector('.payments-filter-form');
        const header = document.querySelector('.payments-records-table thead');
        return {
            rootExists: !!area,
            areaScrollHeight: area?.scrollHeight,
            areaClientHeight: area?.clientHeight,
            areaOverflowY: area && getComputedStyle(area).overflowY,
            bodyOverflowY: getComputedStyle(document.body).overflowY,
            topbarPosition: topbar && getComputedStyle(topbar).position,
            titleTop: title?.getBoundingClientRect().top,
            panelTitleTop: panelTitle?.getBoundingClientRect().top,
            filtersTop: filters?.getBoundingClientRect().top,
            headerTop: header?.getBoundingClientRect().top,
            rows: document.querySelectorAll('.payments-records-table tbody tr[data-record-kind]').length,
            selectedView: document.querySelector('#payment_filter_view')?.value,
            tables: document.querySelectorAll('.payments-records-table').length,
        };
    })()`);
    if (!before.rootExists || before.areaScrollHeight <= before.areaClientHeight || before.areaOverflowY !== 'auto' || before.topbarPosition !== 'fixed' || before.tables !== 1) {
        throw new Error(`${name} initial scroll layout invalid: ${JSON.stringify(before)}`);
    }
    const after = await evaluate(`(async () => {
        const area = document.querySelector('.admin-records-area');
        const title = document.querySelector('.page-heading');
        const panelTitle = document.querySelector('#bills-and-payments-title')?.closest('[data-admin-freeze]');
        const filters = document.querySelector('.payments-filter-form');
        const header = document.querySelector('.payments-records-table thead');
        const firstRow = document.querySelector('.payments-records-table tbody tr[data-record-kind]');
        const initial = { title: title.getBoundingClientRect().top, row: firstRow.getBoundingClientRect().top };
        area.scrollTop = 500;
        area.dispatchEvent(new Event('scroll'));
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        return {
            scrollTop: area.scrollTop,
            maxScroll: area.scrollHeight - area.clientHeight,
            titleTop: title.getBoundingClientRect().top,
            rowTop: firstRow.getBoundingClientRect().top,
            panelTitle: panelTitle.getBoundingClientRect().toJSON(),
            filters: filters.getBoundingClientRect().toJSON(),
            header: header.getBoundingClientRect().toJSON(),
            bodyScrollTop: document.documentElement.scrollTop,
            recordMoved: firstRow.getBoundingClientRect().top < initial.row,
            titleStayed: Math.abs(title.getBoundingClientRect().top - initial.title) < 1,
        };
    })()`);
    if (after.scrollTop <= 0 || !after.recordMoved || !after.titleStayed || after.bodyScrollTop !== 0) {
        throw new Error(`${name} scroll interaction failed: ${JSON.stringify(after)}`);
    }
    if (after.filters.top + after.filters.height > after.header.top + 1) {
        throw new Error(`${name} sticky filters/table headers overlap: ${JSON.stringify(after)}`);
    }
    results.push({ name, before, after });
}
await command('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
await command('Page.navigate', { url: 'file:///C:/xampp/htdocs/waterbillingsystems/tmp/payments-scroll-all.html' });
await new Promise(resolve => setTimeout(resolve, 1800));
const mobile = await evaluate(`(async () => {
    const area = document.querySelector('.admin-records-area');
    const title = document.querySelector('.page-heading');
    const tableWrap = document.querySelector('.payments-table-wrap');
    const initialTitle = title.getBoundingClientRect().top;
    area.scrollTop = Math.min(350, area.scrollHeight - area.clientHeight);
    area.dispatchEvent(new Event('scroll'));
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    return {
        areaOverflowY: getComputedStyle(area).overflowY,
        areaScrollTop: area.scrollTop,
        titleStayed: Math.abs(title.getBoundingClientRect().top - initialTitle) < 1,
        tableCanScrollHorizontally: tableWrap.scrollWidth > tableWrap.clientWidth,
        documentScrollTop: document.documentElement.scrollTop,
    };
})()`);
if (mobile.areaOverflowY !== 'auto' || mobile.areaScrollTop <= 0 || !mobile.titleStayed || !mobile.tableCanScrollHorizontally || mobile.documentScrollTop !== 0) {
    throw new Error(`Mobile payment scrolling failed: ${JSON.stringify(mobile)}`);
}
if (errors.length) throw new Error(`Browser JS errors: ${JSON.stringify(errors)}`);
console.log(JSON.stringify({ results, mobile, errors }, null, 2));
socket.close();
