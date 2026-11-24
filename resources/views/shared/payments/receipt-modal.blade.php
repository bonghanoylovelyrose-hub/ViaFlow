<div class="modal fade receipt-preview-modal" id="receiptPreviewModal" tabindex="-1" aria-labelledby="receiptPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="receiptPreviewTitle">Digital Receipt</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close receipt"></button>
            </div>
            <div class="modal-body p-0">
                <iframe class="receipt-preview-frame" title="Digital receipt" src="about:blank"></iframe>
            </div>
        </div>
    </div>
</div>
@push('styles')
<style>
    body:has(#receiptPreviewModal.show) .modal-backdrop.show { background-color: #062b80; opacity: .48; backdrop-filter: blur(4px); }
    #receiptPreviewModal { --bs-modal-width: 32rem; }
    #receiptPreviewModal .modal-dialog {
        max-width: min(var(--bs-modal-width), calc(100% - 2 * var(--bs-modal-margin)));
        margin-inline: auto;
    }
    #receiptPreviewModal .modal-content { overflow: hidden; }
    #receiptPreviewModal .modal-body { overflow: hidden; }
    #receiptPreviewModal .receipt-preview-frame { display: block; width: 100%; height: min(76vh, 850px); border: 0; background: #fff; }
    @media (max-width: 575.98px) { #receiptPreviewModal .receipt-preview-frame { height: 76dvh; } }
</style>
@endpush
@push('scripts')
<script>
    (() => {
        const modal = document.getElementById('receiptPreviewModal');
        const frame = modal?.querySelector('.receipt-preview-frame');
        if (!modal || !frame) return;
        modal.addEventListener('show.bs.modal', (event) => {
            const link = event.relatedTarget;
            if (!link?.href) { event.preventDefault(); return; }
            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) { event.preventDefault(); return; }
            url.searchParams.set('embed', '1');
            frame.src = url.href;
        });
        frame.addEventListener('load', () => {
            try {
                frame.contentDocument?.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.classList.contains('show')) {
                        event.preventDefault();
                        bootstrap.Modal.getInstance(modal)?.hide();
                    }
                });
            } catch (_) { /* The frame may have navigated away from this application. */ }
        });
        modal.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
    })();
</script>
@endpush
