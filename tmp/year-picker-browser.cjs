
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
    await send('Network.enable');
    await send('Network.clearBrowserCookies');
    await send('Emulation.setDeviceMetricsOverride',{width:1366,height:900,deviceScaleFactor:1,mobile:false});
    await login('admin@example.com', 'password');
    const future = '2029';
    const select = id => 'document.getElementById('+JSON.stringify(id)+')';
    const dialog = id => 'document.getElementById('+JSON.stringify(id+'-dialog')+')';
    const cases = [
        {area:'Admin Water Billing',id:'billing_filter_year',path:'/billing',filters:{search:'YEAR-PICKER-QA',month:'3',status:'Unpaid'}},
        {area:'Admin Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{search:'YEAR-PICKER-QA',month:'3',status:'Unpaid'}},
        {area:'Admin Water Payments',id:'payment_filter_year',path:'/payments',filters:{view:'water-payments',search:'YEAR-PICKER-QA',month:'3',status:'Pending Payment'}},
        {area:'Admin Loan Payments',id:'payment_filter_year',path:'/payments',filters:{view:'loan-payments',search:'YEAR-PICKER-QA',month:'3',status:'Pending Payment'}},
        {area:'Admin Reports',id:'year',path:'/reports',filters:{report:'billing',month:'3',year:'',status:'Unpaid'}},
        {area:'Admin Loan DSS',id:'loan-cash-flow-year',path:'/dss/loans',filters:{year:'2025'},all:'all',initial:'2025'},
        {area:'Consumer Water Billing',id:'consumer-billing-year',path:'/billing',filters:{month:'3',prn:'QA-YEAR-WATER'},water:true},
        {area:'Consumer Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{status:'Unpaid',search:'YEAR-PICKER-QA'},loan:true},
    ];
    const assertFilters = async (id,year,filters) => {
        assert.equal(await run(select(id)+'.value'),year);
        const params = await fromBrowser(() => Object.fromEntries(new URLSearchParams(location.search)));
        for (const [key,value] of Object.entries(filters)) if (key !== 'year') assert.equal(params[key],value,key);
    };
    const open = async (id,year) => {
        await fromBrowser(id=>document.getElementById(id+'-trigger').click(),id);
        await waitFor(dialog(id)+'.open');
        const picked = await fromBrowser(id => {
            const input=document.getElementById(id+'-dialog').querySelector('input:checked');
            return [input.value,getComputedStyle(input.closest('label')).fontWeight,document.activeElement === input];
        },id);
        assert.deepEqual(picked,[year,'700',true]);
    };
    const choose = async (id,year) => {
        await fromBrowser((id,year)=>{
            const options=Array.from(document.getElementById(id+'-dialog').querySelectorAll('input[type=radio]'));
            options.find(option=>option.value===year).click();
        },id,year);
        assert.equal(await run(dialog(id)+'.querySelector("input:checked").value'),year);
    };
    const action = async(id,command)=>fromBrowser((id,command)=>document.getElementById(id+'-dialog').querySelector('[data-year-picker-'+command+']').click(),id,command);
    const apply = async(id,year,filters)=>{
        const time = await run('performance.timeOrigin');
        await action(id,'apply');
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && new URLSearchParams(location.search).get("year") === '+JSON.stringify(year));
        await assertFilters(id,year,filters);
    };
    for (const page of cases) {
        const {area,id,path,filters,all='',initial='',water,loan}=page;
        if (water) {
            await send('Network.clearBrowserCookies');
            await login('YEAR-PICKER-METER','YearPicker2026');
        }
        console.log('Checking '+area);
        await visit(path+'?'+new URLSearchParams(filters),select(id));
        await assertFilters(id,initial,filters);
        const time = await run('performance.timeOrigin');
        await open(id,initial);
        assert.ok(await fromBrowser((id,year)=>Array.from(document.getElementById(id+'-dialog').querySelectorAll('input')).some(input=>input.value===year),id,future));
        await choose(id,future);
        await delay(525);
        assert.equal(await run('performance.timeOrigin'),time);
        assert.equal(await run(select(id)+'.value'),initial);
        await action(id,'cancel');
        await waitFor('!'+dialog(id)+'.open');
        assert.equal(await run('performance.timeOrigin'),time);
        await open(id,initial);
        await choose(id,future);
        await apply(id,future,filters);
        if(water) {
            const bills=await fromBrowser(()=>Array.from(document.querySelectorAll('.cons-bill-prn'),node=>node.textContent.trim()));
            assert.deepEqual(bills,['PRN: QA-YEAR-WATER-2029']);
        }
        if(loan) {
            const periods=await fromBrowser(()=>Array.from(document.querySelectorAll('table tbody tr'),node=>node.textContent));
            assert.ok(periods.some(row=>row.includes('Mar 2029')));
            assert.ok(periods.every(row=>!row.includes('2025')));
        }
        await open(id,future);
        await choose(id,all);
        await apply(id,all,filters);
        if(water) {
            const bills=await fromBrowser(()=>Array.from(document.querySelectorAll('.cons-bill-prn'),node=>node.textContent.trim()));
            assert.deepEqual(bills,['PRN: QA-YEAR-WATER-2037','PRN: QA-YEAR-WATER-2029','PRN: QA-YEAR-WATER-2025']);
        }
        if(id==='year') {
            await send('Emulation.setDeviceMetricsOverride',{width:360,height:500,deviceScaleFactor:1,mobile:true});
            await refresh();
            await open(id,all);
            const responsive=await fromBrowser(id=>{
                const element=document.getElementById(id+'-dialog');
                const box=element.getBoundingClientRect();
                const list=element.querySelector('.year-picker-options');
                return {fits:box.left>=0 && box.right<=innerWidth && box.top>=0 && box.bottom<=innerHeight,scrolls:list.scrollHeight>list.clientHeight};
            },id);
            assert.deepEqual(responsive,{fits:true,scrolls:true});
            await action(id,'cancel');
            await send('Emulation.setDeviceMetricsOverride',{width:1366,height:900,deviceScaleFactor:1,mobile:false});
        }
        console.log('Verified '+area+' with Cancel, Apply, All Years, and combined filters.');
    }
    await send('Browser.close').catch(()=>{});
    ws.close();
}
main().catch(error => { console.error(error);process.exit(1); });
