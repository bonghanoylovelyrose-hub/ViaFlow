@props(['id', 'years', 'monthId' => null, 'selectedYear' => '', 'selectedMonth' => '', 'allYearValue' => '', 'allowAll' => true])

@php
    $selectedYear = (string) ($selectedYear ?? $allYearValue);
    $selectedMonth = (int) $selectedMonth ?: '';
    $monthId ??= $id.'-month';
    $options = collect(\App\Support\FilterYears::available($years));
    if (ctype_digit($selectedYear)) {
        $options = $options->push((int) $selectedYear)->unique()->sortDesc();
    }
    $numericYearLabels = $options->mapWithKeys(fn ($year) => [$year => $year])->all();
    $yearLabels = $numericYearLabels + ($allowAll ? [$allYearValue => 'All Years'] : []);
    $monthLabels = ($allowAll ? ['' => 'All Months'] : []) + collect(range(1, 12))->mapWithKeys(fn ($month) => [
        $month => \Illuminate\Support\Carbon::createFromDate(2000, $month, 1)->format('F'),
    ])->all();
    $periodLabel = $selectedYear === $allYearValue && $selectedMonth === ''
        ? 'All Periods'
        : $monthLabels[$selectedMonth].' '.$yearLabels[$selectedYear];
@endphp

<div {{ $attributes->class(['period-picker']) }} data-period-picker>
    <div class="period-picker-native">
        <select id="{{ $id }}" name="year" class="form-select" aria-label="Year">
            @foreach ($yearLabels as $value => $label)
                <option value="{{ $value }}" @selected($selectedYear === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select id="{{ $monthId }}" name="month" class="form-select" aria-label="Month">
            @foreach ($monthLabels as $value => $label)
                <option value="{{ $value }}" @selected($selectedMonth === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <button type="button" id="{{ $id }}-trigger" class="form-select text-start period-picker-trigger" data-period-trigger
        aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $id }}-picker" aria-label="Period: {{ $periodLabel }}" hidden>{{ $periodLabel }}</button>

    <template>
        <div class="period-picker-panel" id="{{ $id }}-picker" popover="auto" role="dialog" aria-label="Choose year and month">
            <div class="period-picker-section" role="group" aria-label="Year">
                <div class="period-picker-label">Year</div>
                <div @class(['period-picker-options', 'period-picker-years-with-reset' => $allowAll]) data-period-years>
                    @foreach ($numericYearLabels as $value => $label)
                        <button type="button" data-period-year value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
                    @endforeach
                </div>
                @if ($allowAll)
                    <button type="button" class="period-picker-year-reset" data-period-year value="{{ $allYearValue }}" aria-pressed="false">All Years</button>
                @endif
            </div>
            <div class="period-picker-section" role="group" aria-label="Month">
                <div class="period-picker-label">Month</div>
                <div class="period-picker-options period-picker-months">
                    @foreach ($monthLabels as $value => $label)
                        <button type="button" data-period-month value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </template>
</div>

@once
    @push('styles')
        <style>
            .period-picker, .period-picker-native > *, .period-picker-section { min-width: 0; }
            .period-picker [hidden] { display: none !important; }
            .period-picker-native { display: flex; gap: .5rem; }
            .period-picker-trigger { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .period-picker-panel {
                position: fixed;
                inset: auto;
                margin: 0;
                width: min(22rem, calc(100vw - 1rem));
                max-height: calc(100dvh - 1rem);
                padding: .65rem;
                border: 1px solid var(--vf-light);
                border-radius: .75rem;
                background: white;
                color: var(--vf-navy);
                box-shadow: 0 .5rem 1.5rem rgba(6, 43, 128, .2);
            }
            .period-picker-panel:popover-open {
                display: grid;
                grid-template-columns: minmax(5rem, .65fr) minmax(0, 1.65fr);
                gap: .65rem;
                overflow: auto;
            }
            .period-picker-label { font-size: .75rem; font-weight: 700; margin: 0 .25rem .3rem; }
            .period-picker-options {
                display: grid;
                grid-auto-rows: minmax(1.85rem, auto);
                align-content: start;
                gap: .15rem;
                max-height: min(14rem, calc(100dvh - 4rem));
                overflow-y: auto;
                overscroll-behavior: contain;
                scrollbar-gutter: stable;
            }
            .period-picker-months { grid-template-columns: repeat(2, minmax(0, 1fr)); scrollbar-gutter: auto; }
            .period-picker-months > [value=""] { grid-column: 1 / -1; }
            .period-picker-years-with-reset { max-height: min(12rem, calc(100dvh - 6rem)); }
            .period-picker-year-reset { width: 100%; min-height: 1.85rem; margin-top: .15rem; }
            .period-picker-section button {
                padding: .25rem .3rem;
                border: 0;
                border-radius: .35rem;
                background: transparent;
                color: inherit;
                font-size: .8125rem;
                line-height: 1.2;
            }
            .period-picker-section button:hover { background: var(--vf-light); }
            .period-picker-section button[aria-pressed="true"] { background: var(--vf-royal); color: white; font-weight: 700; }
            .period-picker-section button:focus-visible { outline: 2px solid var(--vf-bright); outline-offset: -2px; }
            .period-picker-panel:popover-open .period-picker-year-reset { border: 1px solid var(--vf-light); }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-period-picker]').forEach(picker => {
                    const nativeFields = picker.querySelector('.period-picker-native');
                    const year = nativeFields.querySelector('[name="year"]');
                    const month = nativeFields.querySelector('[name="month"]');
                    const trigger = picker.querySelector('[data-period-trigger]');
                    const panel = picker.querySelector('template').content.firstElementChild;
                    if (typeof panel.showPopover !== 'function') return;

                    const years = [...panel.querySelectorAll('[data-period-year]')];
                    const months = [...panel.querySelectorAll('[data-period-month]')];
                    const yearList = panel.querySelector('[data-period-years]');
                    const selectOption = (options, value) => {
                        options.forEach(option => option.setAttribute('aria-pressed', option.value === value));
                    };

                    const position = () => {
                        if (!panel.matches(':popover-open')) return;

                        const anchor = trigger.getBoundingClientRect();
                        const edge = 8;
                        const gap = 5;
                        const width = document.documentElement.clientWidth;
                        const height = window.innerHeight;
                        const above = anchor.bottom + gap + panel.offsetHeight > height - edge
                            && anchor.top > height - anchor.bottom;
                        const top = above ? anchor.top - panel.offsetHeight - gap : anchor.bottom + gap;

                        panel.style.left = `${Math.max(edge, Math.min(anchor.left, width - panel.offsetWidth - edge))}px`;
                        panel.style.top = `${Math.max(edge, Math.min(top, height - panel.offsetHeight - edge))}px`;
                    };

                    document.body.append(panel);
                    Array.from(year.labels).forEach(label => { label.htmlFor = trigger.id; });
                    nativeFields.hidden = true;
                    trigger.hidden = false;

                    trigger.addEventListener('click', () => {
                        if (panel.matches(':popover-open')) {
                            panel.hidePopover();
                            return;
                        }

                        year.form.dispatchEvent(new Event('period-picker-open'));
                        selectOption(years, year.value);
                        selectOption(months, month.value);
                        panel.showPopover({ source: trigger });
                        position();
                        yearList.scrollTop = 0;
                        yearList.firstElementChild.focus({ preventScroll: true });
                    });

                    panel.addEventListener('click', event => {
                        const option = event.target.closest('[data-period-year], [data-period-month]');
                        if (!option) return;

                        const field = option.hasAttribute('data-period-year') ? year : month;
                        field.value = option.value;
                        panel.hidePopover();
                        field.form.requestSubmit();
                    });

                    panel.addEventListener('toggle', () => {
                        trigger.setAttribute('aria-expanded', panel.matches(':popover-open'));
                    });
                    window.addEventListener('resize', position);
                    window.addEventListener('scroll', position, { passive: true, capture: true });
                });
            });
        </script>
    @endpush
@endonce
