@extends('layouts.admin')
@section('title', 'Reports')
@section('content')
<div class="workspace">
    <header class="workspace-heading">
        <div>
            <h1>Reports</h1>
            <p>Sales, collections and daily operating costs.</p>
        </div>
        <a class="ui-button" href="{{ route('reports.export', $period->query()) }}">Export CSV</a>
    </header>

    <form method="GET" class="workspace-filters report-filters" x-data="{ mode: @js(old('mode', $period->filters['mode'] ?? 'preset')) }" aria-label="Report period">
        <div>
            <label for="period-mode">Period</label>
            <select id="period-mode" name="mode" x-model="mode">
                <option value="preset">Presets</option>
                <option value="month">Single month</option>
                <option value="month_range">Month range</option>
                <option value="custom">Custom dates</option>
            </select>
        </div>
        <div x-show="mode === 'preset'">
            <label for="preset">Preset</label>
            <select id="preset" name="preset" :disabled="mode !== 'preset'">
                @foreach(['this_month' => 'This month to date', 'last_month' => 'Last month', 'this_year' => 'This year to date'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('preset', $period->filters['preset'] ?? 'this_month') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="mode === 'month'" x-cloak>
            <label for="month">Month and year</label>
            <input id="month" type="month" name="month" value="{{ old('month', request('month', $period->start->format('Y-m'))) }}" :disabled="mode !== 'month'" :required="mode === 'month'">
        </div>
        <div x-show="mode === 'month_range'" x-cloak>
            <label for="start_month">Starting month</label>
            <input id="start_month" type="month" name="start_month" value="{{ old('start_month', request('start_month', $period->start->format('Y-m'))) }}" :disabled="mode !== 'month_range'" :required="mode === 'month_range'">
        </div>
        <div x-show="mode === 'month_range'" x-cloak>
            <label for="end_month">Ending month</label>
            <input id="end_month" type="month" name="end_month" value="{{ old('end_month', request('end_month', $period->end->format('Y-m'))) }}" :disabled="mode !== 'month_range'" :required="mode === 'month_range'">
        </div>
        <div x-show="mode === 'custom'" x-cloak>
            <label for="start_date">Start date</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $period->start->toDateString()) }}" :disabled="mode !== 'custom'" :required="mode === 'custom'">
        </div>
        <div x-show="mode === 'custom'" x-cloak>
            <label for="end_date">End date</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date', $period->end->toDateString()) }}" :disabled="mode !== 'custom'" :required="mode === 'custom'">
        </div>
        <button class="ui-button primary">Apply period</button>
    </form>

    <div class="resolved-period">
        <h2>{{ $period->label() }}</h2>
        <p>Inclusive business dates · {{ $period->timezone }} · PHP</p>
    </div>

    <section class="financial-summary" aria-label="Financial summary">
        @php
            $metricTooltips = [
                'sales' => 'Total invoiced value of orders with Completed status during this period.',
                'gross_collections' => 'All verified cash and GCash payments received and ledgered in this period.',
                'cancellation_income' => 'Non-refundable deposits retained from customer-initiated cancellations.',
                'expenses' => 'Total shop expenses logged during this period (excluding voided entries).',
            ];
            $metricSubtitles = [
                'sales' => $summary['completed_order_count'] . ' completed ' . ($summary['completed_order_count'] === 1 ? 'order' : 'orders'),
                'gross_collections' => 'Verified cash and GCash payments',
                'cancellation_income' => 'Retained from cancelled orders',
                'expenses' => 'Operating and store costs',
            ];
            $metricIcons = [
                'sales' => ['name' => 'shopping-cart', 'badge' => 'metric-badge-sales'],
                'gross_collections' => ['name' => 'banknotes', 'badge' => 'metric-badge-collections'],
                'cancellation_income' => ['name' => 'shield-check', 'badge' => 'metric-badge-deposits'],
                'expenses' => ['name' => 'receipt', 'badge' => 'metric-badge-expenses'],
            ];
        @endphp
        @foreach(\App\Services\FinancialReportService::LABELS as $metric => $label)
            @php $iconConfig = $metricIcons[$metric] ?? ['name' => 'clipboard', 'badge' => 'metric-badge-default']; @endphp
            <a href="{{ route('reports.records', $period->query() + ['metric' => $metric]) }}" class="summary-metric group" aria-label="Inspect {{ strtolower($label) }} records">
                <div class="summary-metric-header">
                    <span class="metric-icon-badge {{ $iconConfig['badge'] }}" aria-hidden="true">
                        <x-icon :name="$iconConfig['name']" />
                    </span>
                    <span class="summary-metric-label-wrap">
                        <span class="summary-metric-label">{{ $label }}</span>
                        @if(!empty($metricTooltips[$metric]))
                            <span @click.stop.prevent><x-tooltip :text="$metricTooltips[$metric]" /></span>
                        @endif
                    </span>
                </div>
                <div class="summary-metric-body">
                    <strong class="summary-metric-value">₱{{ number_format($summary[$metric], 2) }}</strong>
                    <small class="summary-metric-subtitle">{{ $metricSubtitles[$metric] ?? '' }}</small>
                </div>
                <div class="summary-metric-footer">
                    <span class="summary-metric-action">Inspect records</span>
                    <span class="summary-metric-arrow" aria-hidden="true">→</span>
                </div>
            </a>
        @endforeach
    </section>

    <section class="operational-result">
        <div>
            <h2 class="inline-flex items-center gap-1">Operational result <x-tooltip text="Completed sales + retained cancellation deposits − valid expenses. Not formal accounting net profit." /></h2>
            <strong>₱{{ number_format($summary['operational_net_income'], 2) }}</strong>
        </div>
        <div class="result-explanation"><strong>Completed sales + retained cancellation deposits − valid expenses</strong><p>Collections are shown separately. This is not accounting profit: cost of goods sold and other accounting costs are unavailable.</p></div>
    </section>

    <section class="workspace-panel report-trends">
        <div class="workspace-heading">
            <h2>{{ $grain }} trend</h2>
            <span>{{ $summary['completed_order_count'] }} completed orders</span>
        </div>
        <p class="chart-legend">
            <span class="legend-sales">Completed sales</span>
            <span class="legend-net">Payment collections</span>
            <span class="legend-expenses">Expenses</span>
        </p>

        @php
            $maximum = max(1, collect($trends)->max(fn($row) => max(abs($row['sales']), abs($row['payment_collections']), abs($row['expenses']))));
            $hasNegative = collect($trends)->contains(fn($row) => $row['payment_collections'] < 0);
            $baseline = $hasNegative ? 125 : 215;
            $scale = ($hasNegative ? 95 : 175) / $maximum;
            $width = max(620, count($trends) * 36 + 80);
            $labelStep = count($trends) > 31 ? 4 : (count($trends) > 15 ? 2 : 1);
        @endphp

        <div class="table-scroll" tabindex="0" role="region" aria-label="Scrollable financial trend chart">
            <svg role="img" aria-labelledby="trend-title trend-description" viewBox="0 0 {{ $width }} 275" class="trend-chart" style="min-width:{{ $width }}px">
                <title id="trend-title">{{ $grain }} financial trend for {{ $period->label() }}</title>
                <desc id="trend-description">Completed sales, payment collections and expenses. Exact values, including zero activity, appear in the table below. Negative collections extend below the zero line.</desc>
                @foreach([0.25, 0.5, 0.75, 1] as $fraction)
                    <line x1="55" y1="{{ $baseline - $maximum * $scale * $fraction }}" x2="{{ $width - 15 }}" y2="{{ $baseline - $maximum * $scale * $fraction }}" stroke="#e7ded4" stroke-dasharray="3 4" />
                @endforeach
                <line x1="55" y1="{{ $baseline }}" x2="{{ $width - 15 }}" y2="{{ $baseline }}" stroke="#9c8b7a" />
                <text x="5" y="{{ $baseline + 4 }}">₱0</text>
                <text x="5" y="20">₱{{ number_format($maximum, 0) }}</text>
                @foreach($trends as $index => $row)
                    @php($x = 65 + $index * (($width - 85) / max(1, count($trends))))
                    @foreach(['sales' => '#3c2415', 'payment_collections' => '#0f766e', 'expenses' => '#b45309'] as $metric => $color)
                        @php($height = abs($row[$metric]) * $scale)
                        <rect x="{{ $x + $loop->index * 8 }}" y="{{ $row[$metric] < 0 ? $baseline : $baseline - $height }}" width="6" height="{{ $height }}" fill="{{ $color }}">
                            <title>{{ $row['period'] }} · {{ str_replace('_', ' ', $metric) }}: ₱{{ number_format($row[$metric], 2) }}</title>
                        </rect>
                    @endforeach
                    @if($index % $labelStep === 0 || $loop->last)
                        <text x="{{ $x + 10 }}" y="250" text-anchor="middle" font-size="11" fill="#78604d" >{{ $grain === 'Daily' ? substr($row['period'], 5) : $row['period'] }}</text>
                    @endif
                @endforeach
            </svg>
        </div>

        <details>
            <summary>View chart values</summary>
            <div class="workspace-table table-scroll" role="region" aria-label="Actual financial trend values" tabindex="0">
                <table>
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th class="numeric">Sales</th>
                            <th class="numeric">Gross collections</th>
                            <th class="numeric">Payment collections</th>
                            <th class="numeric">Retained deposits</th>
                            <th class="numeric">Expenses</th>
                            <th class="numeric">Operational result</th>
                            <th class="numeric">Orders</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trends as $row)
                            <tr>
                                <th scope="row">{{ $row['period'] }}</th>
                                @foreach(['sales', 'gross_collections', 'payment_collections', 'cancellation_income', 'expenses', 'operational_net_income'] as $metric)
                                    <td class="numeric">{{ number_format($row[$metric], 2) }}</td>
                                @endforeach
                                <td class="numeric">{{ $row['completed_order_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total (PHP)</th>
                            @foreach(['sales', 'gross_collections', 'payment_collections', 'cancellation_income', 'expenses', 'operational_net_income'] as $metric)
                                <td class="numeric">{{ number_format($summary[$metric], 2) }}</td>
                            @endforeach
                            <td class="numeric">{{ $summary['completed_order_count'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </details>
    </section>

    <div class="report-breakdowns">
        <section>
            <h2>Expenses by category</h2>
            <div class="workspace-table table-scroll" role="region" aria-label="Expense categories" tabindex="0">
                <table class="small-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="numeric">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expensesByCategory as $category)
                            <tr>
                                <td><a class="record-link" href="{{ route('expenses.index', ['category' => $category->category, 'start_date' => $period->start->toDateString(), 'end_date' => $period->end->toDateString()]) }}">{{ $category->category === 'ingredients' ? 'Groceries' : ucfirst($category->category) }}</a></td>
                                <td class="numeric">₱{{ number_format($category->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <td class="numeric">₱{{ number_format($summary['expenses'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section>
            <h2>Collections by method</h2>
            <div class="workspace-table table-scroll" role="region" aria-label="Collections by payment method" tabindex="0">
                <table class="small-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th class="numeric">Gross verified</th>
                            <th class="numeric">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($methods as $method)
                            <tr>
                                <td>{{ ucfirst($method['method']) }}</td>
                                <td class="numeric">₱{{ number_format($method['gross'], 2) }}</td>
                                <td class="numeric">₱{{ number_format($method['net'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section>
        <div class="workspace-heading">
            <h2>Package performance</h2>
            <a class="ui-button quiet" href="{{ route('reports.records', $period->query() + ['metric' => 'sales']) }}">View completed orders</a>
        </div>
        <p class="form-hint">Saved order names and prices. Paid extras are counted once with their package line.</p>
        <div class="workspace-table table-scroll" role="region" aria-label="Package performance" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th>Package</th>
                        <th class="numeric">Packages sold</th>
                        <th class="numeric">Orders</th>
                        <th class="numeric">Package sales</th>
                        <th class="numeric">Paid extras</th>
                        <th class="numeric">Total sales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($packages as $package)
                        <tr>
                            <td>{{ $package->product_name_snapshot ?: 'Legacy package #' . $package->product_id }}</td>
                            <td class="numeric">{{ $package->package_quantity }}</td>
                            <td class="numeric">{{ $package->order_count }}</td>
                            <td class="numeric">₱{{ number_format($package->package_amount, 2) }}</td>
                            <td class="numeric">₱{{ number_format($package->extras_amount, 2) }}</td>
                            <td class="numeric">₱{{ number_format($package->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No completed orders in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <aside class="workspace-panel current-snapshot">
        <h2>Current snapshot · as of {{ $asOf->format('M d, Y H:i') }} {{ $period->timezone }}</h2>
        <p><a class="record-link" href="{{ route('supplies.index', ['low_stock' => 1]) }}">{{ $lowStockCount }} active supplies at or below reorder level</a>.</p>
        <p class="form-hint">This current stock count are outside the selected period. Stock is tracked per supply and unit; no inventory value is inferred.</p>
    </aside>

    <p class="form-hint">Sales use completion dates; collections use payment dates; retained deposits use customer cancellation dates; expenses use expense dates. Unverified or rejected receipts, voided expenses, and bakery-failure retention are excluded.</p>
</div>
@endsection
