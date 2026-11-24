
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

    const cases = [
        {name:'Admin Water Billing',id:'billing_filter_year',path:'/billing',filters:{search:'YEAR-PICKER-QA',month:'3',status:'Unpaid'},future:['2029','2037']},
        {name:'Admin Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{search:'YEAR-PICKER-QA',month:'3',status:'Unpaid'},future:['2029']},
        {name:'Admin Water Payments',id:'payment_filter_year',path:'/payments',filters:{view:'water-payments',search:'YEAR-PICKER-QA',month:'3',status:'Pending Payment'},future:['2029','2037']},
        {name:'Admin Loan Payments',id:'payment_filter_year',path:'/payments',filters:{view:'loan-payments',search:'YEAR-PICKER-QA',month:'3',status:'Pending Payment'},future:['2029','2037']},
        {name:'Admin Reports',id:'year',path:'/reports',filters:{report:'billing',month:'3',status:'Unpaid'},future:['2029','2037']},
        {name:'Admin Loan DSS',id:'loan-cash-flow-year',path:'/dss/loans',filters:{},future:['2029'],all:'all'},
        {name:'Consumer Water Billing',id:'consumer-billing-year',path:'/billing',filters:{month:'3',prn:'QA-YEAR-WATER'},future:['2029','2037'],mobile:true},
        {name:'Consumer Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{status:'Unpaid',search:'YEAR-PICKER-QA'},future:['2029'],mobile:true},
    ];
    const byId = id => 'document.getElementById('+JSON.stringify(id)+')';
    const modal = id => byId(id+'-dialog');
    const point = element => run('(() => {const b=('+element+').getBoundingClientRect(); return {x:(b.left+b.right)/2,y:(b.top+b.bottom)/2};})()');
    const click = async element => {
        const coords = await point(element);
        await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[coords]});
        await delay(100);
        await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
    };
    const action = (id,action) => click(modal(id)+'.querySelector("[data-year-picker-'+action+']")');
    const option = (id,value) => modal(id)+'.querySelector('+JSON.stringify('input[value="'+value+'"]')+').closest("label")';
    const draft = id => modal(id)+'.querySelector("input:checked")';
    const list = id => modal(id)+'.querySelector(".year-picker-options")';
    const scrollToEdge = async(id,bottom)=>{
        const edge=bottom?list(id)+'.scrollTop >= '+list(id)+'.scrollHeight - '+list(id)+'.clientHeight - 2':list(id)+'.scrollTop < 2';
        for(let attempt=0;attempt<12;attempt++){
            if(await run(edge)) return;
            const rect=await run(list(id)+'.getBoundingClientRect().toJSON()');
            const x=rect.left+rect.width/2;
            const start=rect.top+rect.height*(bottom?.85:.15);
            const distance=rect.height*(bottom?-.7:.7);
            await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y:start}]});
            for(let step=1;step<=6;step++){
                await send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x,y:start+distance*step/6}]});
                await delay(20);
            }
            await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
            await delay(330);
        }
        assert.ok(await run(edge),'Could not swipe to edge');
    };
    const open = async(id,selected)=>{
        await fromBrowser(id=>document.getElementById(id+'-trigger').scrollIntoView({block:'center',behavior:'instant'}),id);
        await run('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        await delay(375);
        await click(byId(id+'-trigger'));
        await waitFor(modal(id)+'.open');
        assert.equal(await run(draft(id)+'.value'),selected);
        assert.equal(await run('getComputedStyle('+draft(id)+'.closest("label")).fontWeight'),'700');
        assert.equal(await run('document.activeElement==='+draft(id)),true);
        assert.equal(await run(modal(id)+'.scrollTop'),0);
    };
    const choose = async(id,year)=>{
        await click(option(id,year));
        assert.equal(await run(draft(id)+'.value'),year);
        assert.equal(await run('getComputedStyle('+draft(id)+'.closest("label")).fontWeight'),'700');
    };
    const apply = async(id,year,filters)=>{
        const time = await run('performance.timeOrigin');
        await action(id,'apply');
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && new URLSearchParams(location.search).get("year") === '+JSON.stringify(year));
        assert.equal(await run(byId(id)+'.value'),year);
        const params=await fromBrowser(()=>Object.fromEntries(new URLSearchParams(location.search)));
        for (const [key,value] of Object.entries(filters)) assert.equal(params[key],value,key);
    };
    for (const [index,{name,id,path,filters,future,all='',mobile}] of cases.entries()) {
        if (index < 6) continue;
        if(index===6) {
            await send('Network.clearBrowserCookies');
            await send('Emulation.setDeviceMetricsOverride',{width:390,height:680,deviceScaleFactor:1,mobile:true});
            await send('Page.bringToFront');
            await send('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});
            await login('YEAR-PICKER-METER','YearPicker2026');
        }
        console.log('Checking '+name);
        const query=new URLSearchParams(filters);
        await visit(path+(query.size?'?'+query:''),byId(id+'-trigger')+' && !'+byId(id+'-trigger')+'.hidden');
        assert.equal(await run(byId(id+'-trigger')+'.innerText'),'All Years');
        await open(id,all);
        const years = await fromBrowser(id=>Array.from(document.getElementById(id+'-dialog').querySelectorAll('input'),input=>input.value),id);
        assert.equal(years[0],all);
        const current = await run('new Date().getFullYear()');
        for(let year=2014;year<=current;year++) assert.ok(years.includes(String(year)),name+' missing '+year);
        for(const year of future) assert.ok(years.includes(year),name+' missing recorded '+year);
        if(current===2026) assert.ok(!years.includes('2027') && !years.includes('2028'));
        assert.ok(await run(list(id)+'.scrollHeight > '+list(id)+'.clientHeight'));
        if(mobile) {
            const fits=await fromBrowser(id=>{
                const {top,bottom,left,right}=document.getElementById(id+'-dialog').getBoundingClientRect();
                return top>=0&&bottom<=innerHeight&&left>=0&&right<=innerWidth;
            },id);
            assert.equal(fits,true);
        }
        const originalLoad=await run('performance.timeOrigin');
        await scrollToEdge(id,true);
        await choose(id,'2014');
        await delay(500);
        assert.equal(await run('performance.timeOrigin'),originalLoad);
        assert.equal(await run(byId(id)+'.value'),all);
        await action(id,'cancel');
        await waitFor('!'+modal(id)+'.open');
        assert.equal(await run(byId(id)+'.value'),all);
        await open(id,all);
        await scrollToEdge(id,true);
        await choose(id,'2014');
        await apply(id,'2014',filters);
        await open(id,'2014');
        assert.ok(await run(list(id)+'.scrollTop > 0'));
        await scrollToEdge(id,false);
        await choose(id,'2029');
        await apply(id,'2029',filters);
        await open(id,'2029');
        await scrollToEdge(id,false);
        await choose(id,all);
        await apply(id,all,filters);
        assert.equal(await run(byId(id+'-trigger')+'.innerText'),'All Years');
        console.log('Verified '+name+' using pointer clicks and scrolling, with Apply, Cancel, and other filters intact.');
    }
    await send('Browser.close').catch(()=>{});
    ws.close();
}
main().catch(error=>{console.error(error);process.exit(1);});
