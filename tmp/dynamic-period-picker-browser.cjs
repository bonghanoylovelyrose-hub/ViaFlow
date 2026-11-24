
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
    await send('Page.bringToFront');
    await send('Emulation.setDeviceMetricsOverride',{width:1366,height:900,deviceScaleFactor:1,mobile:false});
    await login('admin@example.com','password');

    const pages=[
      {name:'Admin Water Billing',id:'billing_filter_year',path:'/billing',filters:{search:'YEAR-PICKER-QA',status:'Unpaid'},record:2037},
      {name:'Admin Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{search:'YEAR-PICKER-QA',status:'Unpaid'},record:2029},
      {name:'Admin Water Payments',id:'payment_filter_year',path:'/payments',filters:{view:'water-payments',search:'YEAR-PICKER-QA',status:'Pending Payment'},record:2037},
      {name:'Admin Loan Payments',id:'payment_filter_year',path:'/payments',filters:{view:'loan-payments',search:'YEAR-PICKER-QA',status:'Pending Payment'},record:2037},
      {name:'Admin Reports',id:'year',path:'/reports',filters:{report:'billing',status:'Unpaid'},record:2037},
      {name:'Admin Loan DSS',id:'loan-cash-flow-year',path:'/dss/loans',filters:{},all:'all',record:2029},
      {name:'Admin Meter Readings',id:'meter_filter_month',path:'/meter-readings',filters:{search:'YEAR-PICKER-QA',status:'Encoded'},required:true,record:2029},
      {name:'Consumer Water Billing',id:'consumer-billing-year',path:'/billing',filters:{prn:'QA-YEAR-WATER'},mobile:true,record:2037,water:true},
      {name:'Consumer Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{search:'YEAR-PICKER-QA',status:'Unpaid'},mobile:true,record:2029},
    ];
    const node=id=>'document.getElementById('+JSON.stringify(id)+')';
    const panel=id=>node(id+'-picker');
    const trigger=id=>node(id+'-trigger');
    const choice=(id,part,value)=>panel(id)+'.querySelector('+JSON.stringify('[data-period-'+part+'][value="'+value+'"]')+')';
    const list=id=>panel(id)+'.querySelector("[data-period-years]")';
    let mobile=false;
    const activate=async expression=>{
        const coords=await run('(() => {const b=('+expression+').getBoundingClientRect();return {x:(b.left+b.right)/2,y:(b.top+b.bottom)/2};})()');
        if (mobile) {
            await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[coords]});
            await delay(100);
            await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
        } else {
            await send('Input.dispatchMouseEvent',{type:'mousePressed',...coords,button:'left',clickCount:1});
            await send('Input.dispatchMouseEvent',{type:'mouseReleased',...coords,button:'left',clickCount:1});
        }
    };
    const open=async(page,selectedYear,selectedMonth)=>{
        await fromBrowser(id=>document.getElementById(id+'-trigger').scrollIntoView({block:'center',behavior:'instant'}),page.id);
        if(mobile)await delay(375);
        await activate(trigger(page.id));
        await waitFor(panel(page.id)+'.matches(":popover-open")');
        const state=await fromBrowser(id=>{
            const panel=document.getElementById(id+'-picker');
            const list=panel.querySelector('[data-period-years]');
            const rect=panel.getBoundingClientRect();
            return {
                years:Array.from(list.querySelectorAll('button'),button=>Number(button.value)),
                months:Array.from(panel.querySelectorAll('[data-period-month]'),button=>button.value),
                selected:['year','month'].map(key=>panel.querySelector('[data-period-'+key+'][aria-pressed=true]').value),
                scrollTop:list.scrollTop,
                fits:rect.top>=0&&rect.left>=0&&rect.bottom<=innerHeight&&rect.right<=innerWidth,
                allYears:!!panel.querySelector('.period-picker-year-reset'),
                extra:Array.from(panel.querySelectorAll('button')).some(button=>!button.matches('[data-period-year],[data-period-month]')),
            };
        },page.id);
        const current=await run('new Date().getFullYear()');
        const latest=Math.max(current+3,page.record);
        assert.deepEqual(state.years,Array.from({length:latest-2014+1},(_,i)=>latest-i));
        const months=Array.from({length:12},(_,i)=>String(i+1));
        assert.deepEqual(state.months,page.required?months:['',...months]);
        assert.deepEqual(state.selected,[selectedYear,selectedMonth]);
        assert.equal(state.allYears,!page.required);
        assert.ok(state.scrollTop===0&&state.fits&&!state.extra);
    };
    const scrollOldest=async id=>{
        const exp=list(id);
        const edge=exp+'.scrollTop >= '+exp+'.scrollHeight - '+exp+'.clientHeight - 2';
        if(!mobile){
            const rect=await run(exp+'.getBoundingClientRect().toJSON()');
            await send('Input.dispatchMouseEvent',{type:'mouseWheel',x:rect.left+rect.width/2,y:rect.top+rect.height/2,deltaX:0,deltaY:2500});
        } else {
            for(let attempt=0;attempt<18&&!(await run(edge));attempt++){
                const rect=await run(exp+'.getBoundingClientRect().toJSON()');
                const x=rect.left+rect.width/2,y=rect.top+rect.height*.85;
                await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y}]});
                for(let step=1;step<=6;step++){
                    await send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x,y:y-rect.height*.7*step/6}]});
                    await delay(20);
                }
                await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
                await delay(300);
            }
        }
        await waitFor(edge);
    };
    const pick=async(page,part,value,year,month)=>{
        const time=await run('performance.timeOrigin');
        await activate(choice(page.id,part,value));
        await waitFor(ready+' && performance.timeOrigin !== '+time+
            ' && new URLSearchParams(location.search).get("year") === '+JSON.stringify(year)+
            ' && new URLSearchParams(location.search).get("month") === '+JSON.stringify(month));
        const params=await fromBrowser(()=>Object.fromEntries(new URLSearchParams(location.search)));
        for(const [key,val] of Object.entries(page.filters))assert.equal(params[key],val,key);
        const applied=await fromBrowser(id=>{
            const scope=document.getElementById(id).closest('[data-period-picker]');
            return ['year','month'].map(name=>scope.querySelector('[name='+name+']').value);
        },page.id);
        assert.deepEqual(applied,[year,month]);
    };
    for(const page of pages){
        if(page.mobile&&!mobile){
            mobile=true;
            await send('Network.clearBrowserCookies');
            await send('Emulation.setDeviceMetricsOverride',{width:390,height:680,deviceScaleFactor:1,mobile:true});
            await send('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});
            await login('YEAR-PICKER-METER','YearPicker2026');
        }
        console.log('Checking '+page.name);
        await visit(page.path+'?'+new URLSearchParams({...page.filters,year:'2029',month:'3'}),trigger(page.id)+' && !'+trigger(page.id)+'.hidden');
        assert.equal(await run(trigger(page.id)+'.textContent.trim()'),'March 2029');
        await open(page,'2029','3');
        await scrollOldest(page.id);
        await pick(page,'year','2014','2014','3');
        assert.equal(await run(trigger(page.id)+'.textContent.trim()'),'March 2014');
        await open(page,'2014','3');
        await pick(page,'month','11','2014','11');
        assert.equal(await run(trigger(page.id)+'.textContent.trim()'),'November 2014');
        if(!page.required){
            const all=page.all??'';
            await open(page,'2014','11');
            await pick(page,'year',all,all,'11');
            await open(page,all,'11');
            await pick(page,'month','',all,'');
            assert.equal(await run(trigger(page.id)+'.textContent.trim()'),'All Periods');
        }
        if(page.water){
            const rows=await fromBrowser(()=>Array.from(document.querySelectorAll('.cons-bill-prn'),part=>part.textContent.trim()));
            assert.deepEqual(rows,['PRN: QA-YEAR-WATER-2037','PRN: QA-YEAR-WATER-2029','PRN: QA-YEAR-WATER-2025']);
        }
        console.log('Verified '+page.name);
    }
    await send('Browser.close').catch(()=>{});
    ws.close();
}
main().catch(error=>{console.error(error);process.exit(1);});
