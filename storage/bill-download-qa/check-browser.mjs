import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { setTimeout as pause } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

const base = 'http://127.0.0.1:8016';
const directory = fileURLToPath(new URL('.', import.meta.url));
const profile = path.join(directory, 'chrome-profile-' + process.pid);
const downloads = path.join(directory, 'downloads-' + process.pid);
await mkdir(downloads);
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--disable-background-networking', '--remote-debugging-port=0',
    '--user-data-dir=' + profile, '--window-size=1440,1100', 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
let socket;

async function until(operation, description) {
    const end = Date.now() + 27000;
    while (Date.now() < end) {
        const result = await operation().catch(() => null);
        if (result) return result;
        if (chrome.exitCode !== null) throw Error('Chrome exited');
        await pause(200);
    }
    throw Error('Timed out waiting for ' + description);
}

try {
    const pages = await until(async () => {
        const portFile = await readFile(profile + '/DevToolsActivePort', 'utf8');
        return (await fetch('http://127.0.0.1:' + portFile.split(/\r?\n/)[0] + '/json/list')).json();
    }, 'Chrome DevTools');
    socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    let id = 0;
    const pending = new Map();
    socket.addEventListener('message', ({data}) => {
        const message = JSON.parse(data);
        pending.get(message.id)?.(message);
    });
    const command = (method, params = {}) => new Promise((resolve, reject) => {
        const requestId = ++id;
        const timeout = setTimeout(() => reject(Error(method + ' timed out')), 15000);
        pending.set(requestId, response => {
            clearTimeout(timeout);
            pending.delete(requestId);
            if (response.error) reject(Error(response.error.message));
            else resolve(response.result);
        });
        socket.send(JSON.stringify({ id: requestId, method, params }));
    });
    const evaluate = async expression => {
        const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
        return result.result.value;
    };
    const navigate = path => command('Page.navigate', { url: base + path });
    await navigate('/login');
    const results = {};
    for (const [role, identifier, width] of [['admin', 'admin@example.com', 1440], ['consumer', 'QA-WATER-METER', 360]]) {
        if (role === 'consumer') await evaluate("document.querySelector('form[action$=\"/logout\"]').requestSubmit()");
        await until(() => evaluate("Boolean(document.querySelector('input[name=login]'))"), role + ' login');
        await evaluate(`(() => {
            const input = document.querySelector('input[name=login]');
            input.value = ${JSON.stringify(identifier)};
            input.form.querySelector('input[name=password]').value = 'password';
            input.form.requestSubmit();
        })()`);
        await until(() => evaluate("location.pathname === '/dashboard'"), role + ' signed in');
        await command('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        const roleFolder = path.join(downloads, role);
        await mkdir(roleFolder);
        await command('Browser.setDownloadBehavior', { behavior: 'allow', downloadPath: roleFolder });

        await navigate('/billing');
        const view = "[...document.querySelectorAll('a.billing-icon-button, a.cons-view-btn')].find(link => new URL(link.href).pathname === '/billing/2')";
        const noBillPrint = "!(/Print (?:Water Bill|Billing Statement|Bill)/i.test(document.body.innerText) || document.querySelector('.bi-printer, [onclick*=\"window.print\"]'))";
        await until(() => evaluate('document.readyState === "complete" && Boolean(' + view + ')'), role + ' View Bill action');
        assert.equal(await evaluate(noBillPrint), true);
        await evaluate(view + '.click()');
        await until(() => evaluate("location.pathname === '/billing/2' && document.readyState === 'complete' && Boolean(document.getElementById('billing-statement'))"), role + ' water bill');
        assert.equal(await evaluate(noBillPrint), true);
        const statement = await evaluate(`(() => {
            const heading = document.querySelector('.stmt-page .page-heading');
            const [download] = heading.querySelectorAll('.btn-primary');
            const article = document.getElementById('billing-statement');
            return {
                primary: [...heading.querySelectorAll('.btn-primary')].map(action => action.textContent.trim()),
                downloadIcon: Boolean(download.querySelector('.bi-download')),
                actionFits: download.getBoundingClientRect().right <= document.documentElement.clientWidth,
                back: Boolean(heading.querySelector('a[href*="/billing"]')),
                title: article.querySelector('#stmt-heading').textContent,
                values: article.innerText,
                readings: [...article.querySelectorAll('.stmt-bill th')].filter(cell => /^(Present Reading|Previous Reading|Current Water Consumption)$/.test(cell.textContent.trim()))
                    .map(cell => cell.nextElementSibling.textContent.trim()),
            };
        })()`);
        assert.deepEqual(statement.primary, ['Download Bill']);
        assert.ok(statement.downloadIcon && statement.actionFits && statement.back);
        assert.equal(statement.title, 'Water Bill');
        assert.deepEqual(statement.readings, ['130', '-120', '10']);
        for (const value of ['Water Bill Verification Consumer', 'P 350.00', 'P 125.00', 'P 565.00', 'P 90.00', 'P 200.00', 'P 365.00', 'PRN-WATER-QA']) {
            assert.ok(statement.values.includes(value), role + ' statement displays ' + value);
        }
        await pause(350);
        const click = await evaluate(`(() => {
            const download = document.querySelector('a[href$="/billing/2/pdf"]');
            download.scrollIntoView({ block: 'nearest', behavior: 'instant' });
            const rect = download.getBoundingClientRect();
            return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
        })()`);
        await command('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, ...click });
        await command('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, ...click });
        const pdfPath = path.join(roleFolder, 'billing-statement-prn-water-qa.pdf');
        const pdf = await until(async () => {
            const bytes = await readFile(pdfPath);
            return bytes.subarray(0, 5).toString() === '%PDF-' && bytes.subarray(-150).toString().includes('%%EOF') && bytes.toString('latin1');
        }, role + ' water bill PDF');
        const [, widthPt, heightPt] = pdf.match(/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+([\d.]+)\s*\]/);
        assert.ok(Math.abs(Number(widthPt) * 25.4 / 72 - 210) < 0.01 && Math.abs(Number(heightPt) * 25.4 / 72 - 297) < 0.01);
        results[role] = { pdfPath, pages: [...pdf.matchAll(/\/Type\s*\/Page\b/g)].length };
    }
    console.log(JSON.stringify(results, null, 2));
} finally {
    socket?.close();
    chrome.kill();
}
