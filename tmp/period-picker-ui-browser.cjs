
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
      {name:'Admin Water Billing',id:'billing_filter_year',path:'/billing',filters:{search:'YEAR-PICKER-QA',status:'Unpaid'}},
      {name:'Admin Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{search:'YEAR-PICKER-QA',status:'Unpaid'}},
      {name:'Admin Water Payments',id:'payment_filter_year',path:'/payments',filters:{view:'water-payments',search:'YEAR-PICKER-QA',status:'Pending Payment'}},
      {name:'Admin Loan Payments',id:'payment_filter_year',path:'/payments',filters:{view:'loan-payments',search:'YEAR-PICKER-QA',status:'Pending Payment'}},
      {name:'Admin Reports',id:'year',path:'/reports',filters:{report:'billing',status:'Unpaid'},initial:{year:'',month:''}},
      {name:'Admin Loan DSS',id:'loan-cash-flow-year',path:'/dss/loans',filters:{},all:'all'},
      {name:'Admin Meter Readings',id:'meter_filter_month',path:'/meter-readings',filters:{search:'YEAR-PICKER-QA',status:'Encoded'},initial:{month:'2026-11'},required:true},
      {name:'Consumer Water Billing',id:'consumer-billing-year',path:'/billing',filters:{prn:'QA-YEAR-WATER'},mobile:true,water:true},
      {name:'Consumer Loan Billing',id:'loan-billing-year',path:'/billing/loan',filters:{search:'YEAR-PICKER-QA',status:'Unpaid'},mobile:true,loan:true},
    ];
    const node=id=>'document.getElementById('+JSON.stringify(id)+')';
    const panel=id=>node(id+'-picker');
    const trigger=id=>node(id+'-trigger');
    const option=(id,part,value)=>panel(id)+'.querySelector('+JSON.stringify('[data-period-'+part+'][value="'+value+'"]')+')';
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
    const opened=async(id,year,month)=>{
        await fromBrowser(id=>document.getElementById(id+'-trigger').scrollIntoView({block:'center',behavior:'instant'}),id);
        if(mobile) await delay(375);
        await activate(trigger(id));
        await waitFor(panel(id)+'.matches(":popover-open")');
        const options=await fromBrowser(id=>{
            const panel=document.getElementById(id+'-picker');
            const rect=panel.getBoundingClientRect();
            const colors=['year','month'].map(part=>getComputedStyle(panel.querySelector('[data-period-'+part+'][aria-pressed=true]')).backgroundColor);
            return {
                selected:['year','month'].map(part=>panel.querySelector('[data-period-'+part+'][aria-pressed=true]').value),
                colors,
                months:Array.from(panel.querySelectorAll('[data-period-month]'),month=>month.textContent.trim()),
                noDaysOrActions:Array.from(panel.querySelectorAll('button')).every(button=>button.matches('[data-period-year],[data-period-month]')),
                scrolls:panel.querySelector('[data-period-years]').scrollHeight>panel.querySelector('[data-period-years]').clientHeight,
                fits:rect.left>=0 && rect.right<=innerWidth && rect.top>=0 && rect.bottom<=innerHeight && rect.width<370
            };
        },id);
        assert.deepEqual(options.selected,[year,month]);
        assert.deepEqual(options.colors,['rgb(7, 84, 200)','rgb(7, 84, 200)']);
        const monthNames=['January','February','March','April','May','June','July','August','September','October','November','December'];
        assert.deepEqual(options.months,id.startsWith('meter_') ? monthNames : ['All Months',...monthNames]);
        assert.ok(options.scrolls && options.fits && options.noDaysOrActions);
    };
    const toEdge=async(id,bottom)=>{
        const expr=list(id);
        const edge=bottom ? expr+'.scrollTop >= '+expr+'.scrollHeight - '+expr+'.clientHeight - 2' : expr+'.scrollTop < 2';
        if(await run(edge)) return;
        if(!mobile) {
            const rect=await run(expr+'.getBoundingClientRect().toJSON()');
            await send('Input.dispatchMouseEvent',{type:'mouseWheel',x:rect.left+rect.width/2,y:rect.top+rect.height/2,deltaX:0,deltaY:bottom?1700:-1700});
        } else {
            for(let attempt=0; attempt<12 && !(await run(edge)); attempt++) {
                const rect=await run(expr+'.getBoundingClientRect().toJSON()');
                const x=rect.left+rect.width/2;
                const y=rect.top+rect.height*(bottom?.85:.15);
                const delta=rect.height*(bottom?-.7:.7);
                await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y}]});
                for(let step=1;step<=6;step++) {
                    await send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x,y:y+delta*step/6}]});
                    await delay(20);
                }
                await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
                await delay(330);
            }
        }
        await waitFor(edge);
    };
    const chooseYear=async(id,value)=>{
        const time=await run('performance.timeOrigin');
        await activate(option(id,'year',value));
        await waitFor(option(id,'year',value)+'.getAttribute("aria-pressed")==="true"');
        await delay(475);
        assert.equal(await run('performance.timeOrigin'),time);
        assert.ok(await run(panel(id)+'.matches(":popover-open")'));
    };
    const chooseMonth=async(id,year,month,filters)=>{
        const time=await run('performance.timeOrigin');
        await activate(option(id,'month',month));
        await waitFor(ready+' && performance.timeOrigin !== '+time+' && new URLSearchParams(location.search).get("year") === '+JSON.stringify(year)+' && new URLSearchParams(location.search).get("month") === '+JSON.stringify(month));
        assert.equal(await run(node(id)+'.value'),year);
        const saved=await fromBrowser(()=>Object.fromEntries(new URLSearchParams(location.search)));
        for(const [key,value] of Object.entries(filters)) assert.equal(saved[key],value,key);
    };
    for(const [index,page] of pages.entries()) {
        const {name,id,path,filters,all='',required,initial={},water,loan}=page;
        if(page.mobile && !mobile) {
            mobile=true;
            await send('Network.clearBrowserCookies');
            await send('Emulation.setDeviceMetricsOverride',{width:390,height:680,deviceScaleFactor:1,mobile:true});
            await send('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});
            await login('YEAR-PICKER-METER','YearPicker2026');
        }
        console.log('Checking '+name);
        const values=new URLSearchParams({...filters,...initial});
        await visit(path+(values.size?'?'+values:''),trigger(id)+' && !'+trigger(id)+'.hidden');
        assert.equal(await run(trigger(id)+'.textContent.trim()'),required?'November 2026':'All Periods');
        const firstYear=required?'2026':all;
        const firstMonth=required?'11':'';
        await opened(id,firstYear,firstMonth);
        const listYears=await fromBrowser(id=>Array.from(document.querySelectorAll('#'+id+'-picker [data-period-year]'),button=>button.value),id);
        assert.ok(listYears.includes('2014')&&listYears.includes('2029'));
        await toEdge(id,true);
        await chooseYear(id,'2014');

        if(index===0) {
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await waitFor('!'+panel(id)+'.matches(":popover-open")');
            assert.equal(await run(node(id)+'.value'),firstYear);
            await opened(id,firstYear,firstMonth);
            await toEdge(id,true);
            await chooseYear(id,'2014');
        }
        await chooseMonth(id,'2014','3',filters);
        assert.equal(await run(trigger(id)+'.textContent.trim()'),'March 2014');
        await opened(id,'2014','3');
        await toEdge(id,false);
        await chooseYear(id,'2029');
        await chooseMonth(id,'2029','3',filters);
        assert.equal(await run(trigger(id)+'.textContent.trim()'),'March 2029');
        if(water) {
            const matches=await fromBrowser(()=>Array.from(document.querySelectorAll('.cons-bill-prn'),part=>part.textContent.trim()));
            assert.deepEqual(matches,['PRN: QA-YEAR-WATER-2029']);
        }
        if(loan) {
            const rows=await fromBrowser(()=>Array.from(document.querySelectorAll('table tbody tr'),row=>row.textContent));
            assert.ok(rows.some(row=>row.includes('Mar 2029')));
            assert.ok(rows.every(row=>!row.includes('Apr 2029')&&!row.includes('2025')));
        }
        if(index===0) {
            await opened(id,'2029','3');
            const rect=await run(panel(id)+'.getBoundingClientRect().toJSON()');
            const scroll=await fromBrowser(()=>({x:scrollX,y:scrollY})); const clip={x:rect.left+scroll.x,y:rect.top+scroll.y,width:rect.width,height:rect.height,scale:1};
            const capture=await send('Page.captureScreenshot',{clip,captureBeyondViewport:true});
            require('node:fs').writeFileSync('tmp/period-picker-ui.png',Buffer.from(capture.data,'base64'));
            await toEdge(id,false);
            await chooseYear(id,all);
            await chooseMonth(id,all,'',filters);
        } else if(!required) {
            await opened(id,'2029','3');
            await toEdge(id,false);
            await chooseYear(id,all);
            await chooseMonth(id,all,'',filters);
        }
        console.log('Verified '+name);
    }
    await send('Browser.close').catch(()=>{});
    ws.close();
}
main().catch(error=>{console.error(error);process.exit(1);});
