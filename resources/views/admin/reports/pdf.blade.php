<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $result->definition->label }} — {{ $result->filters->periodLabel() }}</title>
    <style>
        @page {
            margin: 16mm 9mm 17mm 9mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #1e293b;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8px;
            line-height: 1.35;
            margin: 0;
        }

        body.compact {
            font-size: 6.6px;
        }

        .report-header {
            border-bottom: 2px solid #0f4c81;
            display: table;
            margin-bottom: 9px;
            padding-bottom: 7px;
            width: 100%;
        }

        .report-header .brand,
        .report-header .title {
            display: table-cell;
            vertical-align: bottom;
        }

        .report-header .brand {
            width: 31%;
        }

        .report-header .title {
            text-align: right;
            width: 69%;
        }

        .brand-name {
            color: #0f4c81;
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 0.04em;
        }

        .brand-subtitle {
            color: #64748b;
            font-size: 7px;
            margin-top: 2px;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
        }

        .report-period {
            color: #475569;
            font-size: 8px;
            margin-top: 3px;
        }

        .report-description {
            color: #475569;
            font-size: 7.5px;
            margin: 0 0 7px;
        }

        .summary-table {
            border-collapse: separate;
            border-spacing: 4px;
            margin: 0 -4px 8px;
            table-layout: fixed;
            width: calc(100% + 8px);
        }

        .summary-table td {
            background: #f8fafc;
            border: 1px solid #dbe4ee;
            border-radius: 4px;
            padding: 5px 6px;
            vertical-align: top;
        }

        .summary-label {
            color: #64748b;
            font-size: 6.2px;
            font-weight: bold;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .summary-value {
            color: #0f172a;
            font-size: 9px;
            font-weight: bold;
            margin-top: 2px;
        }

        .meta {
            border-left: 3px solid #dbe4ee;
            color: #64748b;
            font-size: 7px;
            margin-bottom: 8px;
            padding: 2px 0 2px 7px;
        }

        .data-table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table th {
            background: #0f4c81;
            border: 0.5px solid #0f4c81;
            color: #ffffff;
            font-size: 6.2px;
            font-weight: bold;
            padding: 4px 3px;
            text-align: left;
            text-transform: uppercase;
            vertical-align: bottom;
        }

        .data-table td {
            border: 0.5px solid #dbe4ee;
            overflow-wrap: break-word;
            padding: 3px;
            vertical-align: top;
            white-space: normal;
            word-wrap: break-word;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .data-table tr {
            page-break-inside: avoid;
        }

        .data-table th.money,
        .data-table td.money,
        .data-table th.number,
        .data-table td.number {
            text-align: right;
        }

        .data-table td.date,
        .data-table td.datetime {
            white-space: nowrap;
        }

        .empty {
            border: 1px solid #dbe4ee;
            color: #64748b;
            padding: 28px;
            text-align: center;
        }

        .footer {
            border-top: 1px solid #dbe4ee;
            bottom: -12mm;
            color: #64748b;
            font-size: 6.5px;
            left: 0;
            padding-top: 4px;
            position: fixed;
            right: 0;
        }

        .footer .right {
            float: right;
        }

        .footer .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body class="{{ count($result->definition->columns) > 14 ? 'compact' : '' }}">
    @php($weights = collect($result->definition->columns)->map(fn (array $column): int => match ($column['type']) {
        'money', 'number' => 6,
        'date' => 8,
        'datetime' => 10,
        'long_text' => 20,
        'status' => 9,
        default => 10,
    }))
    @php($weightTotal = max(1, $weights->sum()))
    @php($columnCount = count($result->definition->columns))

    <div class="report-header">
        <div class="brand">
            <div class="brand-name">ViaFlow</div>
            <div class="brand-subtitle">Via Vida Water Billing &amp; Management System</div>
        </div>
        <div class="title">
            <h1 class="report-title">{{ $result->definition->label }}</h1>
            <div class="report-period">
                {{ $result->filters->periodLabel() }}
                &nbsp;&middot;&nbsp; Date basis: {{ $result->definition->dateBasis }}
                &nbsp;&middot;&nbsp; {{ number_format($result->total) }} records
            </div>
        </div>
    </div>

    <p class="report-description">{{ $result->definition->description }}</p>

    <table class="summary-table">
        <tr>
            @foreach ($result->summary as $item)
                <td>
                    <div class="summary-label">{{ $item['label'] }}</div>
                    <div class="summary-value">{{ \App\Support\ReportValue::display($item['value'], $item['type']) }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="meta">
        Generated {{ now()->format('M d, Y h:i A') }} by ViaFlow Reports Center.
        @if ($result->filters->status)
            &nbsp; Status: {{ $result->filters->status }}.
        @endif
        @if ($result->filters->category)
            &nbsp; Category: {{ $result->filters->category }}.
        @endif
        @if ($result->filters->channel)
            &nbsp; Channel: {{ $result->filters->channel }}.
        @endif
        @if ($result->filters->type)
            &nbsp; Type: {{ $result->filters->type }}.
        @endif
    </div>

    @if ($result->total === 0)
        <div class="empty">No records found for the selected filters.</div>
    @else
        <table class="data-table">
            <colgroup>
                @foreach ($weights as $weight)
                    <col style="width: {{ round(($weight / $weightTotal) * 100, 2) }}%">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    @foreach ($result->definition->columns as $column)
                        <th class="{{ in_array($column['type'], ['money', 'number'], true) ? 'money' : '' }}">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($result->rows as $row)
                    <tr>
                        @foreach ($result->definition->columns as $key => $column)
                            <td class="{{ $column['type'] }}">
                                {{ \App\Support\ReportValue::display($row[$key] ?? null, $column['type']) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        ViaFlow &middot; {{ $result->definition->label }} &middot; Generated {{ now()->format('M d, Y h:i A') }}
        <span class="right">Page <span class="page-number"></span></span>
    </div>
</body>
</html>
