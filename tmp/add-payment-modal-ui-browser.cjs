
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

    const fs = require('node:fs');
    const path = require('node:path');
    const root = path.resolve('tmp/add-payment-modal-ui-verify');
    fs.mkdirSync(root,{recursive:true});
    const downloads = path.join(root,'downloads-final');
    fs.mkdirSync(downloads,{recursive:true});
    const sampleImage = path.resolve('public/images/vvthoa-logo.png');
    const samplePdf = path.resolve('tmp/loan-installation-reschedule-verify/received-agreement.pdf');
    const viewport = async (width,height) => {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await delay(270);
    };
    const screenshot = async name => {
        const shot = await send('Page.captureScreenshot',{format:'png',fromSurface:true});
        fs.writeFileSync(path.join(root,name+'.png'),Buffer.from(shot.data,'base64'));
    };
    const geometry = () => run('(() => {const form=document.querySelector(".pp-payment-grid"); const [left,right]=form.children; const q=document.querySelector(".pp-organization-qr"); const prev=document.querySelector("#receipt_preview"); const modal=document.querySelector("#paymentModal .modal-body"); const get=node=>{const r=node.getBoundingClientRect(); return {width:r.width,height:r.height,top:r.top, bottom:r.bottom};}; return {left:get(left),right:get(right),qr:get(q),preview:get(prev),modalWidth:modal.clientWidth,modalScrollWidth:modal.scrollWidth,gridScrollWidth:form.scrollWidth,gridWidth:form.clientWidth};})()');
    const upload = async file => {
        const {root} = await send('DOM.getDocument');
        const {nodeId} = await send('DOM.querySelector',{nodeId:root.nodeId,selector:'#proof'});
        await send('DOM.setFileInputFiles',{nodeId,files:[file]});
    };
    await send('Page.enable');
    await send('DOM.enable');
    await send('Network.enable');
    await send('Network.clearBrowserCookies');
    await send('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:downloads});
    await viewport(1440,900);
    await visit('/login','document.querySelector("#login")');
    await run('document.querySelector(".login-welcome")?.click(); document.querySelector("#login").value="PAYMENT-UI-METER"; document.querySelector("#password").value="PaymentSample2026"; document.querySelector("#login").form.requestSubmit()');
    await waitFor('location.pathname === "/dashboard" && '+ready);
    await visit('/payments','document.querySelector("[data-bs-target=\'#paymentModal\']")');
    await run('document.querySelector("[data-bs-target=\'#paymentModal\']").click()');
    await waitFor('document.activeElement === document.querySelector("#paymentModal.show")');
    await waitFor('document.querySelector(".pp-qr-image-window img").naturalWidth === 405');
    assert.equal(await run('document.getElementById("receipt_download").disabled && document.getElementById("receipt_share").disabled'),true);
    for (const [width,height] of [[1440,900],[1024,800],[768,900],[390,820],[320,700]]) {
        await viewport(width,height);
        const box = await geometry();
        assert.ok(Math.abs(box.left.width-box.right.width)<=1,JSON.stringify(box));
        assert.equal(box.modalWidth,box.modalScrollWidth,JSON.stringify(box));
        assert.ok(box.gridScrollWidth<=box.gridWidth+1,JSON.stringify(box));
        assert.ok(box.preview.height<=115,JSON.stringify(box));
        if (width>=1024) {
            assert.ok(Math.abs(box.left.top-box.right.top)<=1,JSON.stringify(box));
            assert.ok(box.qr.width>=280 && box.qr.width<=320,JSON.stringify(box));
        } else {
            assert.ok(box.right.top>=box.left.bottom,JSON.stringify(box));
            assert.ok(box.qr.width<=box.right.width,JSON.stringify(box));
        }
        if (width===1440) {
            assert.equal(await run('document.querySelector("#submit_payment_button").getBoundingClientRect().bottom <= document.querySelector("#paymentModal .modal-body").getBoundingClientRect().bottom'),true);
            await screenshot('desktop');
        }
        if (width===390) {
            await screenshot('mobile-fields');
            await run('document.querySelector(".pp-organization-qr").scrollIntoView({block:"end"})');
            await delay(220);
            await screenshot('mobile-preview-qr');
        }
        console.log(width+'px viewport, columns '+Math.round(box.left.width)+'px each, QR frame '+Math.round(box.qr.width)+'px.');
    }
    await viewport(1440,900);
    await run(fs.readFileSync(path.resolve('tmp/add-payment-ui-jsQR.js'),'utf8'));
    const verified = await run('(() => {const img=document.querySelector(".pp-qr-image-window img"), view=img.parentElement, frame=view.parentElement; const a=img.getBoundingClientRect(), b=view.getBoundingClientRect(), q=frame.getBoundingClientRect(); const raw=document.createElement("canvas"); raw.width=img.naturalWidth;raw.height=img.naturalHeight;const rawCtx=raw.getContext("2d");rawCtx.drawImage(img,0,0);const first=jsQR(rawCtx.getImageData(0,0,raw.width,raw.height).data,raw.width,raw.height);const rendered=document.createElement("canvas");rendered.width=Math.round(q.width);rendered.height=Math.round(q.height);const ctx=rendered.getContext("2d");ctx.fillStyle="#fff";ctx.fillRect(0,0,rendered.width,rendered.height);const scale=img.naturalWidth/a.width;ctx.drawImage(img,(b.left-a.left)*scale,(b.top-a.top)*scale,b.width*scale,b.height*scale,b.left-q.left,b.top-q.top,b.width,b.height);const second=jsQR(ctx.getImageData(0,0,rendered.width,rendered.height).data,rendered.width,rendered.height);return Boolean(first?.data && second?.data===first.data);})()');
    assert.equal(verified,true);
    console.log('The QR displayed in the modal decodes to the same payload as the supplied original.');

    await upload(sampleImage);
    await waitFor('!document.querySelector("#receipt_preview_image").hidden && document.querySelector("#receipt_preview_image").naturalWidth > 0');
    assert.equal(await run('document.getElementById("receipt_preview_placeholder").hidden && document.getElementById("receipt_preview_pdf").hidden'),true);
    assert.equal(await run('getComputedStyle(document.getElementById("receipt_preview_image")).objectFit'),'contain');
    await run('document.querySelector("#receipt_download").click()');
    const saved = path.join(downloads,path.basename(sampleImage));
    for (let i=0;!fs.existsSync(saved) && i<60;i++) await delay(250);
    assert.deepEqual(fs.readFileSync(saved),fs.readFileSync(sampleImage));
    await run('Object.defineProperty(navigator,"share",{configurable:true,value:undefined}); document.querySelector("#receipt_share").click()');
    assert.match(await run('document.querySelector("#receipt_action_status").textContent'),/Download the receipt/);
    await run('Object.defineProperty(navigator,"canShare",{configurable:true,value:data=>data.files[0]===document.querySelector("#proof").files[0]}); Object.defineProperty(navigator,"share",{configurable:true,value:async data=>{window.sharedReceiptName=data.files[0].name;}}); document.querySelector("#receipt_share").click()');
    assert.equal(await run('window.sharedReceiptName'),path.basename(sampleImage));
    await waitFor('!document.querySelector("#receipt_parse_status").innerText.includes("Analyzing")');
    await upload(samplePdf);
    await waitFor('!document.querySelector("#receipt_preview_pdf").hidden && document.querySelector("#receipt_preview_pdf").src.startsWith("blob:")');
    assert.equal(await run('document.getElementById("receipt_preview_image").hidden'),true);
    await waitFor('!document.querySelector("#receipt_parse_status").innerText.includes("Analyzing")');
    await run('(() => {const form=document.getElementById("consumerPaymentForm");form.elements.gateway_reference.value="UI-CHECK-REF";form.elements.amount.value="100.00";form.requestSubmit();})()');
    await waitFor('document.activeElement === document.querySelector("#paymentSubmitConfirmModal.show")');
    await run('document.querySelector("#cancel_submit_payment_button").click()');
    await waitFor('!document.querySelector("#paymentSubmitConfirmModal.show") && document.querySelector("#paymentModal.show")');
    console.log('Image and PDF previews worked, Download returned identical bytes, Share actions behaved correctly, and valid details opened payment confirmation.');
    ws.close();
}
main().catch(error => { console.error(error); process.exit(1); });
