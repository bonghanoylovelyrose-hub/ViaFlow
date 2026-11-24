<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-registration-draft]');
    if (!form) return;
    const key = 'viaflow-application-' + form.dataset.registrationDraft;
    let tabId = sessionStorage.getItem('viaflow-application-tab');
    if (!tabId) { tabId = window.crypto?.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(36).slice(2); sessionStorage.setItem('viaflow-application-tab', tabId); }
    const fileKey = tabId + ':' + form.dataset.registrationDraft;
    let restoring = true;
    let databasePromise;
    function database() {
        if (!databasePromise) databasePromise = new Promise((resolve, reject) => {
            const request = indexedDB.open('viaflow-application-drafts', 1);
            request.onupgradeneeded = () => request.result.createObjectStore('files');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        return databasePromise;
    }
    async function saveFiles(files) {
        const db = await database();
        await new Promise((resolve, reject) => {
            const tx = db.transaction('files', 'readwrite');
            tx.objectStore('files').put(files, fileKey);
            tx.oncomplete = resolve;
            tx.onerror = () => reject(tx.error);
        });
    }
    async function loadFiles() {
        const db = await database();
        return new Promise((resolve, reject) => {
            const request = db.transaction('files').objectStore('files').get(fileKey);
            request.onsuccess = () => resolve(request.result || {});
            request.onerror = () => reject(request.error);
        });
    }
    async function saveDraft() {
        if (restoring) return;
        const fields = [], files = {};
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach(input => {
            if (input.name === '_token' || input.readOnly || input.disabled) return;
            if (input.type === 'file') { files[input.name] = Array.from(input.files || []); return; }
            fields.push({name: input.name, type: input.type, value: input.value, checked: input.checked});
        });
        try { sessionStorage.setItem(key, JSON.stringify(fields)); } catch (_) {}
        try { await saveFiles(files); } catch (_) {}
    }
    window.saveRegistrationDraft = saveDraft;
    form.addEventListener('input', () => { if (!restoring) saveDraft(); });
    form.addEventListener('change', () => { if (!restoring) saveDraft(); });
    form.addEventListener('registration-files-changed', () => { if (!restoring) saveDraft(); });
    form.querySelectorAll('[data-registration-back]').forEach(link => link.addEventListener('click', async event => {
        event.preventDefault();
        await saveDraft();
        location.href = link.href;
    }));
    (async () => {
        let fields = [];
        try { fields = JSON.parse(sessionStorage.getItem(key) || '[]'); } catch (_) {}
        const controls = Array.from(form.querySelectorAll('input[name], select[name], textarea[name]'));
        fields.forEach(field => {
            const input = controls.find(item => item.name === field.name && item.type === field.type && (item.type !== 'radio' && item.type !== 'checkbox' || item.value === field.value));
            if (!input || input.readOnly || input.disabled) return;
            if (input.type === 'radio' || input.type === 'checkbox') input.checked = field.checked;
            else input.value = field.value;
        });
        form.querySelectorAll('input[name="membership_document_type"]').forEach(input => { if (input.checked) input.dispatchEvent(new Event('change', {bubbles: true})); });
        restoring = false;
        try {
            const files = await loadFiles();
            controls.filter(input => input.type === 'file' && !input.files?.length && files[input.name]?.length).forEach(input => {
                const transfer = new DataTransfer();
                files[input.name].forEach(file => transfer.items.add(file));
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', {bubbles: true}));
            });
        } catch (_) {}
    })();
});
</script>
