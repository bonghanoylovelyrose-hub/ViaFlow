<?php if(auth()->user()?->isAdmin()): ?>
    <?php $__env->startPush('styles'); ?>
        <style>
            html.admin-one-scroll, html.admin-one-scroll body { height: 100%; overflow: hidden; }
            html.admin-one-scroll.admin-fallback-scroll { scrollbar-gutter: auto; }
            .admin-scroll-shell { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }
            .admin-scroll-shell > .topbar { flex-shrink: 0; }
            .admin-scroll-path { display: flex !important; flex-direction: column; flex: 1 1 auto; min-height: 0; min-width: 0; }
            .admin-scroll-path > :not(.admin-scroll-path):not(.admin-records-area) { flex-shrink: 0; }
            .admin-records-area { flex: 1 1 auto; min-height: 0; min-width: 0; overflow-y: auto; overflow-x: hidden; overscroll-behavior-y: contain; scrollbar-gutter: stable; }
            .admin-area-first { margin-top: var(--admin-first-top, 0px) !important; margin-left: 0 !important; margin-right: 0 !important; }
            .admin-area-item {
                margin-left: calc(var(--admin-item-original-left) - var(--admin-area-original-left)) !important;
                margin-right: calc(var(--admin-item-original-right) - var(--admin-area-original-right)) !important;
            }
            .admin-records-area .admin-natural-records { height: auto !important; max-height: none !important; overflow-y: visible !important; }
            .admin-records-area .admin-records-surface { padding: 1.5rem !important; border: 1px solid var(--wb-border); border-radius: 1rem; background: var(--wb-surface); min-width: 0; }
            .admin-records-area .admin-table-viewport { width: 100%; max-width: 100%; min-width: 0; overflow-x: auto; }
            .admin-records-area .admin-table-heading { position: relative; z-index: 5; background: var(--wb-surface); transform: translateY(var(--admin-table-header-shift, 0px)); }
            .admin-records-area .admin-table-heading th { position: static; }
            .admin-records-area [data-admin-freeze] { position: sticky; top: var(--admin-freeze-top, 0px); z-index: 10; isolation: isolate; }
            .admin-records-area .admin-primary-freeze { transform: translateY(var(--admin-extra-shift, 0px)); }
            .admin-records-area.has-scrolled [data-admin-freeze]::before {
                content: ''; position: absolute; z-index: -1; pointer-events: none;
                top: calc(-1 * var(--admin-freeze-cover-top, 0px));
                left: calc(-1 * var(--admin-freeze-cover-left, 0px));
                right: calc(-1 * var(--admin-freeze-cover-right, 0px));
                bottom: calc(-1 * var(--admin-freeze-cover-bottom, 0px));
                background: var(--admin-freeze-background, var(--wb-surface));
                border: var(--admin-freeze-border, 0);
                border-bottom: 0;
                border-radius: var(--admin-freeze-radius, 0) var(--admin-freeze-radius, 0) 0 0;
            }
            .admin-content-scroll .content-wrapper { display: block !important; flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; scrollbar-gutter: stable; }
            .admin-content-scroll > .topbar { margin-right: var(--admin-window-gutter, 0px); }
            .modal-open .admin-records-area, .modal-open .admin-content-scroll .content-wrapper { overflow-y: hidden; }
            .admin-content-scroll .content-wrapper .admin-scroll-path { display: var(--admin-path-display, block) !important; flex: initial; min-height: initial; }
            .admin-content-scroll .admin-records-area { display: contents; }
            .admin-content-scroll .admin-area-item { margin-left: var(--admin-item-original-left) !important; margin-right: var(--admin-item-original-right) !important; }
            .admin-content-scroll .admin-area-first {
                margin-top: var(--admin-original-top) !important;
                margin-left: var(--admin-original-left) !important;
                margin-right: var(--admin-original-right) !important;
            }
            .admin-content-scroll [data-admin-freeze] { position: static; }
            .admin-content-scroll .admin-primary-freeze { transform: none; }
            .admin-content-scroll .admin-table-heading { transform: none; }
            .admin-content-scroll .admin-record-part { clip-path: none !important; }
            .admin-content-scroll [data-admin-freeze]::before { display: none; }
            html.admin-consumer-page-scroll, html.admin-consumer-page-scroll body { height: 100%; min-height: 100%; overflow: hidden; }
            .admin-consumer-page-scroll .admin-scroll-shell { height: 100dvh; min-height: 0; display: flex; flex-direction: column; overflow: visible; }
            .admin-consumer-page-scroll .admin-scroll-shell > .topbar { position: fixed; top: 0 !important; left: var(--sidebar-width); right: 0; z-index: 1040; transform: none; }
            .admin-consumer-page-scroll .sidebar-collapsed .topbar { left: 0; }
            .admin-consumer-page-scroll .admin-scroll-path { display: flex !important; flex: 1 1 auto; min-height: 0; overflow: hidden; }
            .admin-consumer-page-scroll .content-wrapper { height: calc(100dvh - var(--consumer-topbar-height, 80px)); max-height: calc(100dvh - var(--consumer-topbar-height, 80px)); overflow: hidden; margin-top: var(--consumer-topbar-height, 80px); }
            .admin-consumer-page-scroll .admin-records-area { min-height: 0; overflow-y: auto; }
            .admin-consumer-page-scroll .admin-records-area [data-admin-freeze] { position: sticky; top: var(--admin-freeze-top, 0px); z-index: 10; isolation: isolate; }
            .admin-consumer-page-scroll .admin-records-area .admin-primary-freeze { transform: translateY(var(--admin-extra-shift, 0px)); }
            .admin-consumer-page-scroll .admin-records-area .admin-table-heading { position: relative; z-index: 5; background: var(--wb-surface); transform: translateY(var(--admin-table-header-shift, 0px)); }
            .admin-consumer-page-scroll .admin-records-area .admin-table-heading th { position: static; }
            .admin-consumer-page-scroll .admin-records-area.has-scrolled [data-admin-freeze]::before { content: ''; position: absolute; z-index: -1; pointer-events: none; inset: calc(-1 * var(--admin-freeze-cover-top, 0px)) calc(-1 * var(--admin-freeze-cover-right, 0px)) calc(-1 * var(--admin-freeze-cover-bottom, 0px)) calc(-1 * var(--admin-freeze-cover-left, 0px)); background: var(--admin-freeze-background, #fff); }
            @media (max-width: 991.98px) { .admin-consumer-page-scroll .admin-scroll-shell > .topbar { left: 0; } }
            @media print {
                html.admin-one-scroll, html.admin-one-scroll body { height: auto; overflow: visible; }
                .admin-scroll-shell { height: auto; display: block; overflow: visible; }
                .admin-scroll-path { display: var(--admin-path-display, block) !important; }
                .admin-records-area { display: contents; }
                .admin-area-item { margin-left: var(--admin-item-original-left) !important; margin-right: var(--admin-item-original-right) !important; }
                .admin-area-first { margin-top: var(--admin-original-top) !important; margin-left: var(--admin-original-left) !important; margin-right: var(--admin-original-right) !important; }
                .admin-records-area [data-admin-freeze] { position: static; }
                .admin-records-area .admin-primary-freeze { transform: none; }
                .admin-records-area .admin-table-heading { transform: none; }
                .admin-record-part { clip-path: none !important; }
                .admin-records-area [data-admin-freeze]::before { display: none; }
            }
        </style>
    <?php $__env->stopPush(); ?>

    <?php $__env->startPush('scripts'); ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const main = document.querySelector('.main-content');
                const content = main?.querySelector('.content-wrapper');
                if (!content) return;
                main.style.setProperty('--admin-window-gutter', (window.innerWidth - document.documentElement.clientWidth) + 'px');

                const consumerPage = content.querySelector('[data-admin-page-scroll]');
                const first = content.querySelector('[data-admin-records-root]')
                    || [...content.children].find(element => !element.matches('.page-heading, .modal, script, style'));
                if (!first) return;
                if (consumerPage) {
                    main.classList.add('admin-scroll-shell');
                    document.documentElement.classList.add('admin-consumer-page-scroll');
                    const topbar = main.querySelector('.topbar');
                    const setTopbarHeight = function () {
                        main.style.setProperty('--consumer-topbar-height', topbar.getBoundingClientRect().height + 'px');
                    };
                    setTopbarHeight();
                    new ResizeObserver(setTopbarHeight).observe(topbar);
                }
                const sectionScroll = first.hasAttribute('data-admin-section-scroll') || Boolean(consumerPage);

                const parent = first.parentElement;
                const margins = window.getComputedStyle(first);
                const area = document.createElement('div');
                area.className = 'admin-records-area';
                area.setAttribute('role', 'region');
                area.setAttribute('aria-label', 'Admin records and page content');
                area.tabIndex = 0;
                ['Top', 'Left', 'Right'].forEach(function (side) {
                    const value = margins['margin' + side];
                    first.style.setProperty('--admin-original-' + side.toLowerCase(), value);
                    if (side === 'Top' && parseFloat(value) < 0) {
                        first.style.setProperty('--admin-first-top', value);
                        area.style.marginTop = '0px';
                    } else {
                        area.style['margin' + side] = value;
                    }
                });
                area.style.setProperty('--admin-area-original-left', margins.marginLeft);
                area.style.setProperty('--admin-area-original-right', margins.marginRight);
                parent.insertBefore(area, first);
                while (area.nextSibling) area.appendChild(area.nextSibling);
                [...area.children].filter(element => !element.matches('.modal, script, style')).forEach(function (element) {
                    const styles = window.getComputedStyle(element);
                    element.style.setProperty('--admin-item-original-left', styles.marginLeft);
                    element.style.setProperty('--admin-item-original-right', styles.marginRight);
                    element.classList.add('admin-area-item');
                });
                first.classList.add('admin-area-first');

                for (let ancestor = parent; ancestor && ancestor !== main; ancestor = ancestor.parentElement) {
                    ancestor.style.setProperty('--admin-path-display', window.getComputedStyle(ancestor).display);
                    ancestor.classList.add('admin-scroll-path');
                }
                main.classList.add('admin-scroll-shell');
                document.documentElement.classList.add('admin-one-scroll');

                const tableHeadings = [];
                area.querySelectorAll('table').forEach(function (table) {
                    if (table.closest('.modal')) return;
                    table.closest('.panel-card, .billing-card')?.classList.add('admin-records-surface');
                    if (table.tHead) {
                        table.tHead.classList.add('admin-table-heading');
                        if (! (consumerPage && table.closest('#active-disconnection-notices'))) tableHeadings.push(table.tHead);
                    }
                    const wrapper = table.parentElement;
                    const styles = window.getComputedStyle(wrapper);
                    if (['auto', 'scroll'].includes(styles.overflowX) || ['auto', 'scroll'].includes(styles.overflowY)) {
                        if (wrapper.scrollHeight > wrapper.clientHeight + 1) wrapper.style.scrollbarGutter = 'stable';
                        wrapper.classList.add('admin-natural-records', 'admin-table-viewport');
                        wrapper.style.removeProperty('max-height');
                        wrapper.style.removeProperty('height');
                    }
                });
                area.querySelectorAll('*').forEach(function (element) {
                    if (element.matches('textarea, select') || element.closest('.modal, .dropdown-menu')) return;
                    const styles = window.getComputedStyle(element);
                    if (['auto', 'scroll'].includes(styles.overflowY) && styles.maxHeight !== 'none') {
                        element.classList.add('admin-natural-records');
                    }
                });

                const frozen = [...area.querySelectorAll('[data-admin-freeze]')];
                const recordParts = [];
                const collectRecords = function (element) {
                    if (element.matches('[data-admin-freeze], .modal, script, style')) return;
                    if (element.querySelector('[data-admin-freeze], .modal')) {
                        [...element.children].forEach(collectRecords);
                    } else {
                        element.classList.add('admin-record-part');
                        recordParts.push(element);
                    }
                };
                [...area.children].forEach(collectRecords);
                frozen.forEach(function (element) {
                    for (let ancestor = element.parentElement; ancestor && ancestor !== area; ancestor = ancestor.parentElement) {
                        const styles = window.getComputedStyle(ancestor);
                        if (['auto', 'scroll', 'hidden', 'clip'].includes(styles.overflowY)
                            || ['auto', 'scroll', 'hidden', 'clip'].includes(styles.overflowX)) ancestor.style.overflow = 'visible';
                    }
                });

                let frame = null;
                let paintFrame = null;
                const paintRecords = function () {
                    paintFrame = null;
                    if (main.classList.contains('admin-content-scroll')) return;
                    const areaTop = area.getBoundingClientRect().top;
                    if (consumerPage) {
                        const activeTitle = area.querySelector('#active-accounts > .consumer-section-controls');
                        const activeFilters = area.querySelector('#account-filters');
                        if (activeTitle) activeTitle.style.setProperty('--admin-freeze-top', '0px');
                        if (activeTitle && activeFilters) activeFilters.style.setProperty('--admin-freeze-top', activeTitle.getBoundingClientRect().height + 'px');
                    }
                    const primary = frozen.filter(element => element.classList.contains('admin-primary-freeze'));
                    primary.forEach(element => element.style.setProperty('--admin-extra-shift', '0px'));
                    const offsets = primary.map(element => Math.max(0,
                        areaTop + parseFloat(element.style.getPropertyValue('--admin-freeze-top')) - element.getBoundingClientRect().top));
                    primary.forEach((element, index) => element.style.setProperty('--admin-extra-shift', offsets[index] + 'px'));
                    const headers = frozen.map(element => ({ element, box: element.getBoundingClientRect(),
                        margin: parseFloat(window.getComputedStyle(element).marginBottom) || 0 }))
                        .filter(({ box }) => box.height > 0 && box.bottom > areaTop);
                    const visibleBottom = headers.filter(({ element, box }) => Math.abs(box.top - areaTop
                        - parseFloat(element.style.getPropertyValue('--admin-freeze-top'))) < 1)
                        .reduce((bottom, { box, margin }) => Math.max(bottom, box.bottom - areaTop + margin), 0);
                    area.style.scrollPaddingTop = visibleBottom + 'px';
                    tableHeadings.forEach(function (heading) {
                        const box = heading.getBoundingClientRect();
                        const oldShift = parseFloat(heading.style.getPropertyValue('--admin-table-header-shift')) || 0;
                        const naturalTop = box.top - oldShift;
                        let boundary = Math.max(areaTop, naturalTop);
                        headers.forEach(function ({ element, box: header, margin }) {
                            if ((element.compareDocumentPosition(heading) & Node.DOCUMENT_POSITION_FOLLOWING)
                                && header.left < box.right - 1 && header.right > box.left + 1) {
                                boundary = Math.max(boundary, header.bottom + margin);
                            }
                        });
                        const tableBottom = heading.closest('table').getBoundingClientRect().bottom;
                        const shift = Math.max(0, Math.min(boundary - naturalTop, tableBottom - naturalTop - box.height));
                        heading.style.setProperty('--admin-table-header-shift', shift + 'px');
                    });
                    recordParts.forEach(function (part) {
                        const box = part.getBoundingClientRect();
                        let boundary = box.top;
                        headers.forEach(function ({ element, box: header, margin }) {
                            if ((element.compareDocumentPosition(part) & Node.DOCUMENT_POSITION_FOLLOWING)
                                && header.left < box.right - 1 && header.right > box.left + 1) {
                                boundary = Math.max(boundary, header.bottom + margin);
                            }
                        });
                        const inset = Math.min(box.height, Math.max(0, boundary - box.top));
                        part.style.clipPath = inset > 0 ? 'inset(' + inset + 'px 0 0)' : '';
                    });
                };
                const layout = function () {
                    frame = null;
                    const oldScroll = main.classList.contains('admin-content-scroll') ? content.scrollTop : area.scrollTop;
                    main.classList.remove('admin-content-scroll');
                    document.documentElement.classList.remove('admin-fallback-scroll');
                    content.scrollTop = 0;
                    area.scrollTop = 0;
                    area.classList.remove('has-scrolled');
                    tableHeadings.forEach(heading => heading.style.setProperty('--admin-table-header-shift', '0px'));
                    frozen.forEach(function (element) {
                        element.style.setProperty('--admin-extra-shift', '0px');
                        element.style.setProperty('--admin-freeze-top', '0px');
                        element.classList.remove('admin-primary-freeze');
                    });
                    const areaBox = area.getBoundingClientRect();
                    let frozenBottom = 0;
                    const sectionEnds = new Map();
                    const initialHeaders = frozen.filter(element => element.getBoundingClientRect().bottom <= areaBox.bottom);
                    initialHeaders.filter(element => !sectionScroll || element.closest('[data-admin-freeze-section]') === first)
                        .forEach(element => element.classList.add('admin-primary-freeze'));
                    const sectionInset = parseFloat(window.getComputedStyle(first).paddingTop)
                        + parseFloat(window.getComputedStyle(first).borderTopWidth);
                    const sectionTop = frozen.filter(element => element.closest('[data-admin-freeze-section]') === first)
                        .reduce((bottom, element) => Math.max(bottom, element.getBoundingClientRect().bottom - areaBox.top
                            + (parseFloat(window.getComputedStyle(element).marginBottom) || 0)), sectionInset);
                    const globalBottom = initialHeaders
                        .reduce((bottom, element) => Math.max(bottom, element.getBoundingClientRect().bottom - areaBox.top
                            + (parseFloat(window.getComputedStyle(element).marginBottom) || 0)), 0);
                    let consumerFrozenBottom = 0;

                    frozen.forEach(function (element) {
                        if (!element.getClientRects().length) return;
                        const section = element.closest('[data-admin-freeze-section]')
                            || element.closest('.panel-card, .billing-card, .card') || first;
                        const sectionBox = section.getBoundingClientRect();
                        const box = element.getBoundingClientRect();
                        const sectionOffset = sectionBox.top - areaBox.top;
                        const startsVisible = sectionOffset < area.clientHeight;
                        const naturalOffset = box.top - areaBox.top;
                        const activeTitle = consumerPage && area.querySelector('#active-accounts > .consumer-section-controls');
                        const top = consumerPage && first.hasAttribute('data-admin-section-scroll') && activeTitle && element === activeTitle
                            ? 0
                            : consumerPage && first.hasAttribute('data-admin-section-scroll') && element === area.querySelector('#account-filters')
                                ? activeTitle.getBoundingClientRect().height
                                : consumerPage && first.hasAttribute('data-admin-section-scroll') ? consumerFrozenBottom : consumerPage ? 0
                            : sectionScroll && section !== first ? sectionTop + box.top - sectionBox.top
                                : (startsVisible ? Math.max(0, sectionOffset) : globalBottom) + box.top - sectionBox.top;
                        element.style.setProperty('--admin-freeze-top', top + 'px');
                        if (consumerPage) consumerFrozenBottom = top + box.height + (parseFloat(window.getComputedStyle(element).marginBottom) || 0);
                        if (startsVisible) frozenBottom = Math.max(frozenBottom, top + box.height);

                        let background = element.parentElement;
                        while (background !== area && window.getComputedStyle(background).backgroundColor === 'rgba(0, 0, 0, 0)') {
                            background = background.parentElement;
                        }
                        if (background === area) background = element;
                        const backgroundBox = background.getBoundingClientRect();
                        const backgroundStyles = window.getComputedStyle(background);
                        const ownStyles = window.getComputedStyle(element);
                        const firstInSection = !sectionEnds.has(section);
                        const previousBottom = sectionEnds.get(section) ?? Math.max(backgroundBox.top, sectionBox.top);
                        sectionEnds.set(section, box.bottom);
                        element.style.setProperty('--admin-freeze-background', backgroundStyles.backgroundColor === 'rgba(0, 0, 0, 0)' ? 'var(--wb-surface)' : backgroundStyles.backgroundColor);
                        element.style.setProperty('--admin-freeze-cover-top', Math.max(0, box.top - previousBottom) + 'px');
                        element.style.setProperty('--admin-freeze-cover-left', Math.max(0, box.left - backgroundBox.left) + 'px');
                        element.style.setProperty('--admin-freeze-cover-right', Math.max(0, backgroundBox.right - box.right) + 'px');
                        element.style.setProperty('--admin-freeze-cover-bottom', (parseFloat(ownStyles.marginBottom) || 0) + 'px');
                        const continuesFrame = firstInSection && background === section;
                        element.style.setProperty('--admin-freeze-border', continuesFrame ? backgroundStyles.border : '0');
                        element.style.setProperty('--admin-freeze-radius', continuesFrame ? backgroundStyles.borderTopLeftRadius : '0px');
                    });

                    const lineHeight = parseFloat(window.getComputedStyle(content).lineHeight) || 24;
                    if (area.clientHeight - frozenBottom < lineHeight * 2) {
                        main.classList.add('admin-content-scroll');
                        document.documentElement.classList.add('admin-fallback-scroll');
                        content.scrollTop = oldScroll;
                    } else {
                        const scrollbar = area.offsetWidth - area.clientWidth;
                        const originalRight = parseFloat(first.style.getPropertyValue('--admin-original-right')) || 0;
                        area.style.marginRight = (originalRight - scrollbar) + 'px';
                        area.scrollTop = oldScroll;
                        area.classList.toggle('has-scrolled', oldScroll > 0);
                    }
                    paintRecords();
                };
                const scheduleLayout = function () {
                    if (frame === null) frame = window.requestAnimationFrame(layout);
                };
                area.addEventListener('scroll', function () {
                    area.classList.toggle('has-scrolled', area.scrollTop > 0);
                    if (paintFrame === null) paintFrame = window.requestAnimationFrame(paintRecords);
                }, { passive: true });
                window.addEventListener('resize', scheduleLayout);
                document.addEventListener('shown.bs.tab', scheduleLayout);
                document.addEventListener('shown.bs.collapse', scheduleLayout);
                document.addEventListener('hidden.bs.collapse', scheduleLayout);
                document.addEventListener('closed.bs.alert', scheduleLayout);
                document.addEventListener('toggle', scheduleLayout, true);
                document.fonts?.ready.then(scheduleLayout);
                let lastWidth = main.clientWidth;
                let lastHeight = main.clientHeight;
                new ResizeObserver(function () {
                    if (main.clientWidth !== lastWidth || main.clientHeight !== lastHeight) {
                        lastWidth = main.clientWidth;
                        lastHeight = main.clientHeight;
                        scheduleLayout();
                    }
                }).observe(main);
                layout();
            });
        </script>
    <?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/layouts/admin-table-scroll.blade.php ENDPATH**/ ?>