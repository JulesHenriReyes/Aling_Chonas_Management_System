<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report</title>
    <style>
        @page { margin: 52pt 48pt 40pt; }
        body { font-family: "DejaVu Sans", sans-serif; color: #20272e; font-size: 9.5pt; line-height: 1.3; }
        h1 { font-size: 21pt; line-height: 1.1; margin: 0 0 4pt; color: #111820; }
        h2 { font-size: 12pt; color: #111820; margin: 12pt 0 6pt; page-break-after: avoid; }
        p { margin: 0 0 3pt; }
        .subtitle { font-size: 12pt; color: #46535c; }
        .meta { font-size: 8.5pt; color: #59636b; margin-top: 6pt; margin-bottom: 9pt; }
        .meta span { margin-right: 18pt; }
        .summary-layout { width: 100%; border: 0; border-collapse: collapse; }
        .summary-layout > tbody > tr > td { vertical-align: top; border: 0; padding: 0; }
        .summary-layout > tbody > tr > td + td { padding-left: 16pt; }
        .summary-layout h2 { margin-top: 8pt; }
        .summary-layout .meta { margin-top: 0; margin-bottom: 4pt; }
        .summary-layout table.data { font-size: 8.5pt; }
        .summary-layout table.data th { font-size: 8pt; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data th { background: #263843; color: #fff; font-weight: bold; font-size: 8.5pt; text-align: left; padding: 7pt 6pt; }
        table.data td { padding: 4pt 6pt; border-bottom: 0.5pt solid #dce2e6; vertical-align: middle; word-wrap: break-word; line-height: 12pt; }
        table.data tbody tr:nth-child(even) { background: #f3f6f7; }
        table.data .numeric { text-align: right; }
        table.data td.numeric { white-space: nowrap; }
        table.data .total td { font-weight: bold; background: #e9eff2; border-top: 1pt solid #b4c1c8; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .summary-layout > tbody > tr { page-break-inside: auto; }
        .empty { color: #59636b; font-style: italic; }
        .notes { font-size: 8pt; color: #59636b; margin-top: 8pt; }
        .notes h2 { font-size: 10pt; margin: 0 0 5pt; }
        .notes p { margin-bottom: 3pt; }
        .trend-page { page-break-before: always; }
        .trend-page h2 { font-size: 17pt; margin: 0 0 5pt; }
        .trend-page .notes h2 { font-size: 10pt; }
        .trend-meta { color: #59636b; font-size: 8.5pt; margin-bottom: 13pt; }
        table.trends { font-size: 9pt; }
        table.trends th { font-size: 8pt; padding: 5pt; line-height: 11pt; }
        table.trends td { padding: 2pt 5pt; line-height: 11pt; }
    </style>
</head>
<body>
    <h1>Financial report</h1>
    <p class="subtitle">{{ $period->start->format('M j, Y') }} to {{ $period->end->format('M j, Y') }}</p>
    <p class="meta">
        <span>Currency PHP</span>
        <span>Business dates {{ $period->timezone }}</span>
        <span>Generated {{ $asOf->format('M j, Y H:i') }} {{ $period->timezone }}</span>
    </p>

    <table class="summary-layout"><tbody><tr>
        <td style="width: 42%">
            <h2>Financial summary</h2>
            <p class="meta">{{ $summary['completed_order_count'] }} completed {{ $summary['completed_order_count'] === 1 ? 'order' : 'orders' }}</p>
            @include('admin.reports.pdf-table', ['section' => $sections[0]])
        </td>
        <td style="width: 28%">
            <h2>Collections by payment method</h2>
            @include('admin.reports.pdf-table', ['section' => $sections[1]])
        </td>
        <td style="width: 30%">
            <h2>Expenses by category</h2>
            @include('admin.reports.pdf-table', ['section' => $sections[2]])
        </td>
    </tr></tbody></table>
    <p class="notes">Operational result = completed sales + retained cancellation deposits - valid expenses. Collections are reported separately. This is not accounting profit.</p>

    <h2>Package performance</h2>
    @include('admin.reports.pdf-table', ['section' => $sections[3], 'packageTable' => true])

    @php($trendChunks = array_chunk($sections[4]['rows'], 18))
    @foreach($trendChunks as $chunk)
        <div class="trend-page">
            <h2>{{ $sections[4]['title'] }}{{ $loop->first ? '' : ' continued' }}</h2>
            <p class="trend-meta">All amounts in PHP. Inclusive business dates in {{ $period->timezone }}.</p>
            @include('admin.reports.pdf-table', ['section' => array_merge($sections[4], ['rows' => $chunk,
                'total' => $loop->last ? $sections[4]['total'] : null]), 'trendTable' => true])
            @if($loop->last)
                <div class="notes">
                    <h2>How to read this report</h2>
                    @foreach(\App\Services\ReportExportService::DEFINITIONS as $note)
                        <p>{{ $note }}</p>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>

