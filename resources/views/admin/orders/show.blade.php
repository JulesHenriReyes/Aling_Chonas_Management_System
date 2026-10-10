@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

@section('content')
@if(session('completed_staff_browser_prefix'))
<script>
try { const prefix=@js(session('completed_staff_browser_prefix')); for (const key of Object.keys(sessionStorage)) if (key.startsWith(prefix+'-')) sessionStorage.removeItem(key); } catch {}
</script>
@endif
<div class="space-y-4 order-view">
    {{-- Breadcrumb --}}
    <div class="page-heading">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('orders.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium transition"><x-icon name="arrow-left" class="mr-1" /> Orders</a>
            <span class="text-cocoa-200">/</span>
            <span class="text-cocoa-400 font-mono">{{ $order->order_number }}</span>
        </div>
        <x-tooltip :text="$order->user_id === null ? 'Order submitted online by customer via public storefront.' : 'Order manually created by staff member ' . $order->user->first_name . '.'">
            <span class="px-3 py-1 rounded-full text-xs font-semibold cursor-help inline-flex items-center gap-1
                {{ $order->user_id === null ? 'bg-cream-100 text-cocoa-500 ' : 'bg-cocoa-50 text-cocoa-500 ' }}">
                {{ $order->user_id === null ? 'Public Web Order' : 'Staff Created (' . $order->user->first_name . ')' }}
            </span>
        </x-tooltip>
    </div>

    {{-- Order Header --}}
    <div class="bg-white border border-cocoa-100 rounded-xl p-6">
        <div class="order-summary-grid">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-cocoa-600 font-mono">{{ $order->order_number }}</h1><span class="association-tag">{{ $order->user_id === null ? 'Public Web' : 'Staff order' }}</span>
                    <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                    @if ($order->status !== 'cancelled')
                        <x-status :value="$order->payment_status" />
                    @elseif ($order->amount_paid > 0)
                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded text-amber-800 bg-amber-50 border border-amber-200">
                            ₱{{ number_format($order->amount_paid, 2) }} deposit retained
                        </span>
                    @endif
                </div>
                <div class="text-xs text-cocoa-400 mt-2 flex flex-wrap gap-x-4 gap-y-1">
                    <span>Submitted: <strong class="text-cocoa-500">{{ $order->created_at->format('M d, Y h:i A') }}</strong></span>
                    @if ($order->completed_at)
                        <span>Completed: <strong class="text-emerald-700">{{ $order->completed_at->format('M d, Y h:i A') }}</strong></span>
                    @endif
                    @if ($order->cancelled_at)
                        <span>Cancelled: <strong class="text-red-700">{{ $order->cancelled_at->format('M d, Y h:i A') }}</strong></span>
                    @endif
                </div>
            </div>

            {{-- Financial Summary --}}
            <div class="order-money">
                <div>
                    <span class="text-xs text-cocoa-500  font-bold block">Total</span>
                    <span class="text-xl font-bold text-cocoa-600">₱{{ number_format($order->total_amount, 2) }}</span>
                </div>
                <div>
                    <span class="text-xs text-cocoa-500  font-bold block">Paid</span>
                    <span class="text-base font-bold text-emerald-700">₱{{ number_format($order->amount_paid, 2) }}</span>
                </div>
                <div>
                    <span class="text-xs text-cocoa-500  font-bold block">Balance</span>
                    <span class="text-base font-bold {{ $order->status !== 'cancelled' && $order->remaining_balance > 0 ? 'text-red-700' : 'text-emerald-700' }}">
                        ₱{{ number_format($order->status === 'cancelled' ? 0 : $order->remaining_balance, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- 3-Column Layout --}}
    <div class="review-layout">
        {{-- Main Content: Line Items, Images, Payments --}}
        <div class="review-main space-y-4">
            {{-- Line Items --}}
            <div class="order-items bg-white border border-cocoa-100 rounded-xl p-4">
                <div class="flex items-center justify-between border-b border-cocoa-100 pb-4 mb-5">
                    <h2 class="text-sm font-semibold text-cocoa-600">Order Items & Customizations</h2>
                    <span class="text-sm text-cocoa-500">Saved prices</span>
                </div>

                <div class="space-y-5">
                    @foreach ($order->orderDetails as $detail)
                        <div class="item-section space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-cocoa-100/60 pb-3">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-cocoa-700 text-sm sm:text-base">{{ $detail->product_name_snapshot ?? $detail->product->product_name }}</h3>
                                        @if ($detail->layers)
                                            <span class="text-xs font-semibold text-cocoa-600 bg-cream-100 px-2.5 py-0.5 rounded-full border border-cocoa-200/80">{{ $detail->layers }} layer(s)</span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-cocoa-400">Saved package price: ₱{{ number_format($detail->unit_price, 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-cocoa-400 block">Subtotal ({{ $detail->quantity }} qty)</span>
                                    <span class="text-base font-bold text-cocoa-600">₱{{ number_format($detail->subtotal, 2) }}</span>
                                </div>
                            </div>

                            {{-- Customization Details --}}
                            <div class="space-y-2 text-xs">
                                @if ($detail->themes)
                                    <div class="p-3 rounded-xl bg-cream-50/70 border border-cocoa-100/80">
                                        <span class="font-bold text-cocoa-600 block text-xs mb-1">Theme / Colors</span>
                                        <p class="text-cocoa-800 font-medium leading-relaxed break-words text-xs sm:text-sm">{{ $detail->themes }}</p>
                                    </div>
                                @endif

                                @if ($detail->special_request)
                                    <div class="p-3 rounded-xl bg-amber-50/60 border border-amber-200/70">
                                        <span class="font-bold text-amber-900 block text-xs mb-1">Special Requests & Instructions</span>
                                        <p class="text-cocoa-800 font-medium leading-relaxed whitespace-pre-line break-words text-xs sm:text-sm">{{ $detail->special_request }}</p>
                                    </div>
                                @endif

                                @if (!$detail->themes && !$detail->special_request)
                                    <p class="text-xs text-cocoa-400 italic">No custom themes or special requests specified.</p>
                                @endif
                            </div>

                            {{-- Per-Product Reference Images --}}
                            @if ($detail->images->isNotEmpty())
                                <div class="pt-2 space-y-1.5">
                                    <span class="text-xs text-cocoa-500 font-bold block">Customer Reference {{ \Illuminate\Support\Str::plural('Photo', $detail->images->count()) }}</span>
                                    <div class="flex flex-wrap gap-2.5">
                                        @foreach ($detail->images as $img)
                                            <div x-data="{ expanded: false }" class="relative">
                                                <button type="button" @click="expanded = true" class="group relative block w-28 h-28 rounded-xl overflow-hidden border border-cocoa-200/80 bg-cream-100 hover:border-amber-400 focus:outline-none cursor-pointer transition shadow-xs" title="Click to view full photo: {{ $img->original_filename }}" aria-label="View reference photo {{ $img->original_filename }}">
                                                    <img src="{{ $img->url() }}" alt="{{ $img->original_filename }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                                                    <div class="absolute inset-0 bg-black/25 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white drop-shadow" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" /></svg>
                                                    </div>
                                                </button>
                                                <template x-teleport="body">
                                                    <div x-show="expanded" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-80 p-4 backdrop-blur-sm" @keydown.escape.window="expanded = false" data-dialog role="dialog" aria-modal="true" aria-label="Reference photo preview">
                                                        <div class="relative w-full h-full flex justify-center items-center" @click.outside="expanded = false">
                                                            <button @click="expanded = false" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none z-[110]" aria-label="Close image preview">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                            </button>
                                                            <img src="{{ $img->url() }}" alt="{{ $img->original_filename }}" class="max-w-full max-h-full object-contain rounded drop-shadow-2xl">
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Fixed catalog snapshot --}}
                            @if(!empty($detail->included_items_snapshot) || filled($detail->included_contents_snapshot))
                            <div class="order-inclusions">
                                <strong>Included items · Free</strong>
                                @include('partials.included-items', ['includedItems' => $detail->included_items_snapshot ?? [], 'includedText' => $detail->included_contents_snapshot, 'packageQuantity' => $detail->quantity, 'showHeading' => false])
                            </div>
                            @endif

                            @if ($detail->addOns->isNotEmpty())
                                <div class="pt-3 border-t border-cocoa-100/70 space-y-2">
                                    <span class="text-xs font-semibold text-cocoa-700 block">Paid extras</span>
                                    <div class="space-y-1.5">
                                        @foreach ($detail->addOns as $extra)
                                            @php
                                                $extraPhoto = $extra->addOn?->photo_path ?? $extra->photo_path ?? null;
                                            @endphp
                                            <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-cream-50/60 border border-cocoa-100/60">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    @if ($extraPhoto)
                                                        <div x-data="{ expanded: false }" class="shrink-0">
                                                            <button type="button" @click="expanded = true" class="block border border-cocoa-100 rounded overflow-hidden w-8 h-8 bg-cream-50 hover:opacity-80 transition focus:outline-none cursor-pointer" title="Click to view image" aria-label="View photo of {{ $extra->name_snapshot }}">
                                                                <img src="{{ asset('storage/' . $extraPhoto) }}" alt="{{ $extra->name_snapshot }}" class="w-full h-full object-cover">
                                                            </button>
                                                            <template x-teleport="body">
                                                                <div x-show="expanded" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-80 p-4 backdrop-blur-sm" @keydown.escape.window="expanded = false" data-dialog role="dialog" aria-modal="true" aria-label="Photo preview of {{ $extra->name_snapshot }}">
                                                                    <div class="relative w-full h-full flex justify-center items-center" @click.outside="expanded = false">
                                                                        <button @click="expanded = false" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none z-[110]" aria-label="Close image preview">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                        </button>
                                                                        <img src="{{ asset('storage/' . $extraPhoto) }}" alt="{{ $extra->name_snapshot }}" class="max-w-full max-h-full object-contain rounded drop-shadow-2xl">
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    @endif
                                                    <div class="min-w-0">
                                                        <p class="leading-snug font-medium text-cocoa-800 text-xs sm:text-sm">
                                                            <strong>Paid extra:</strong> {{ $extra->name_snapshot }}
                                                            <span class="text-cocoa-500 font-semibold ml-1">×{{ $extra->quantity }}</span>
                                                            <span class="text-xs text-cocoa-400 font-normal ml-1">({{ '₱' . number_format($extra->unit_price, 2) }} each)</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <span class="font-bold text-cocoa-700 text-xs sm:text-sm shrink-0 whitespace-nowrap">₱{{ number_format($extra->subtotal, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>


            @include('admin.orders.staff-review')
            @include('admin.orders.proof-review')

            {{-- Payments --}}
            <div class="order-payments bg-white border border-cocoa-100 rounded-xl p-6 space-y-4">
                <div class="payment-heading flex items-center justify-between border-b border-cocoa-100 pb-4">
                    <h2 class="text-sm font-semibold text-cocoa-600">Payments</h2>
                    <span class="text-xs font-mono font-semibold text-emerald-700">
                        Paid: ₱{{ number_format($order->amount_paid, 2) }} / ₱{{ number_format($order->total_amount, 2) }}
                    </span>
                </div>

                {{-- Down Payment Form --}}
                @if (Gate::allows('record-payments') && $order->canRecordDeposit() && $order->user_id !== null)
                    <div class="bg-cream-50 border border-cocoa-100 rounded-lg p-5 space-y-3">
                        <div class="page-heading">
                            <h3 class="font-semibold text-cocoa-600 text-xs ">
                                Verify 50% Deposit and Secure Booking
                            </h3>
                            <span class="font-bold text-sm text-cocoa-600">
                                Exact 50%: ₱{{ number_format($order->required_down_payment, 2) }}
                            </span>
                        </div>
                        <p class="text-xs text-cocoa-500">
                            The Owner approved this staff order at creation. Record the verified Cash or GCash deposit to secure the booking and allow preparation.
                        </p>

                        <form action="{{ route('orders.payments.store', $order) }}" method="POST" x-data="{ method: 'cash' }" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 pt-2">
                            @csrf
                            <input type="hidden" name="payment_type" value="down_payment">

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="deposit-amount">Amount (₱)</label>
                                <input id="deposit-amount" type="number" step="0.01" name="amount" value="{{ $order->required_down_payment }}" readonly
                                       class="w-full text-xs rounded-lg border-cocoa-100 bg-cream-100 font-semibold text-cocoa-600">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="deposit-method">Payment Method</label>
                                <select id="deposit-method" x-model="method" name="payment_method" required class="w-full text-xs rounded-lg border-cocoa-100 focus:border-cocoa-300 focus:ring-cocoa-300">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="deposit-reference">Reference Number</label>
                                <input id="deposit-reference" type="text" name="reference_number" :required="method === 'gcash'" value="{{ old('reference_number') }}" placeholder="Required if GCash"
                                       class="w-full text-xs rounded-lg border-cocoa-100 focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                            </div>

                            <div class="flex items-end">
                                <button type="submit" class="w-full py-2 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-xs rounded-lg transition ">
                                    Verify deposit and secure booking
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                <span id="pickup-payment" class="anchor-target"></span>
                {{-- Final Payment Form --}}
                @if (Gate::allows('record-payments') && $order->status === 'ready_for_pickup' && $order->remaining_balance > 0)
                    <div class="bg-cream-50 border border-cocoa-100 rounded-lg p-5 space-y-3">
                        <div class="page-heading">
                            <h3 class="font-semibold text-cocoa-600 text-xs ">
                                Complete pickup
                            </h3>
                            <span class="font-bold text-sm text-cocoa-600">
                                Balance: ₱{{ number_format($order->remaining_balance, 2) }}
                            </span>
                        </div>

                        <p class="text-sm text-cocoa-600">Collect the exact remaining balance only while the customer picks up this Ready for pickup order. Verify the cash or incoming GCash transaction before completing pickup.</p>
                        <form action="{{ route('orders.completePickup', $order) }}" method="POST" data-action-form data-validation-active="{{ old('_workflow') === 'pickup' ? 'true' : 'false' }}" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 pt-2" x-data="{ method: {{ Js::from(old('payment_method', 'cash')) }} }">
                            <input type="hidden" name="_workflow" value="pickup">
                            @csrf

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="pickup-balance-amount">Amount (₱)</label>
                                <input id="pickup-balance-amount" type="number" step="0.01" value="{{ $order->remaining_balance }}" max="{{ $order->remaining_balance }}" aria-describedby="pickup-amount-help" readonly
                                       class="w-full text-xs rounded-lg border-cocoa-100 bg-cream-100 font-semibold text-cocoa-600">
                                <p id="pickup-amount-help" class="form-hint">Exact balance · maximum ₱{{ number_format($order->remaining_balance, 2) }}. This amount is fixed.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="pickup-balance-method">Payment Method</label>
                                <select id="pickup-balance-method" name="payment_method" x-model="method" required class="w-full text-xs rounded-lg border-cocoa-100 focus:border-cocoa-300 focus:ring-cocoa-300">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="pickup-balance-reference">Reference Number <span x-show="method === 'gcash'" class="text-red-700">(required)</span></label>
                                <input id="pickup-balance-reference" type="text" name="reference_number" value="{{ old('reference_number') }}" :required="method === 'gcash'" placeholder="Required if GCash" maxlength="100"
                                       class="w-full text-xs rounded-lg border-cocoa-100 focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
                                <p class="form-hint" x-show="method === 'gcash'" x-cloak>Enter the reference from the verified incoming GCash transaction.</p>
                                @error('reference_number')<p role="alert" data-error-for="reference_number" class="text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex items-end">
                                <button type="submit" class="w-full py-2 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-xs rounded-lg transition ">
                                    Complete pickup
                                </button>
                            </div>
                            <div class="sm:col-span-2 xl:col-span-4">
                                <label class="flex items-start gap-3 text-sm text-cocoa-600"><input type="checkbox" name="pickup_confirmed" value="1" required {{ old('pickup_confirmed') ? 'checked' : '' }}><span>The customer is collecting this order now. I verified the exact remaining payment in cash or in the business GCash account.</span></label>
                                @error('pickup_confirmed')<p role="alert" data-error-for="pickup_confirmed" class="text-sm text-red-700">{{ $message }}</p>@enderror
                                <span role="status" data-submit-status></span>
                            </div>
                        </form>
                    </div>
                @endif
                @if ($order->needsStaffReview())
                    <p class="text-sm text-cocoa-500">Payment opens after staff confirms this request.</p>
                @elseif (in_array($order->status, ['confirmed', 'preparing'], true) && $order->remaining_balance > 0 && $order->amount_paid > 0)
                    <p class="text-sm text-cocoa-500">Collect the remaining balance when the customer picks up the order.</p>
                @endif

                {{-- Payment History Table --}}
                <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-cream-100 text-cocoa-600 text-xs  font-bold">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Method</th>
                                <th class="px-4 py-3">Ref #</th>
                                <th class="px-4 py-3">Recorded By</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-cocoa-100/60">
                            @forelse ($order->payments as $payment)
                                <tr class="text-sm hover:bg-cream-50 transition">
                                    <td class="px-4 py-3 text-xs text-cocoa-500">{{ $payment->payment_date->format('M d, Y h:i A') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                            {{ $payment->payment_type === 'down_payment' ? 'bg-amber-50 text-cocoa-500 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ' }}">
                                            {{ str_replace('_', ' ', $payment->payment_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-cocoa-500 text-xs">{{ $payment->payment_method }}</td>
                                    <td class="px-4 py-3 font-mono text-cocoa-400 text-xs">{{ $payment->reference_number ?? '—' }}</td>
                                    <td class="px-4 py-3 text-cocoa-400 text-xs">{{ $payment->user->first_name ?? 'Staff' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-cocoa-600">₱{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-cocoa-400">No payment records yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        {{-- Sidebar: Customer, Schedule, Actions --}}
        <div class="review-context space-y-6">
            {{-- Customer --}}
            <div class="bg-white border border-cocoa-100 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-cocoa-600 mb-3">Customer and pickup</h2>
                <div class="space-y-2.5 text-sm">
                    <div>
                        <span class="text-xs text-cocoa-400 block font-medium">Name</span>
                        <a href="{{ route('customers.show', $order->customer) }}" class="font-semibold text-cocoa-600 hover:text-cocoa-500 transition">
                            {{ $order->customer->full_name }}
                        </a>
                    </div>
                    <div>
                        <span class="text-xs text-cocoa-400 block font-medium">Phone</span>
                        <span class="font-mono font-medium text-cocoa-600">{{ $order->customer->phone_number }}</span>
                    </div>
                </div>
                <div class="border-t border-cocoa-100 my-4"></div>
                <h3 class="text-xs font-semibold text-cocoa-500 mb-3">Pickup Schedule</h3>
                <div class="space-y-2.5 text-sm">
                    <div>
                        <span class="text-xs text-cocoa-400 block font-medium">Pickup Date</span>
                        <span class="font-semibold text-cocoa-600">{{ $order->pickup_date->format('F d, Y (l)') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-cocoa-400 block font-medium">Pickup Time</span>
                        <span class="font-semibold text-cocoa-600">{{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}</span>
                    </div>
                    @if ($order->notes_text)
                        <div class="pt-2 border-t border-cocoa-100 mt-2">
                            <span class="text-xs text-cocoa-400 block font-medium">Order Notes</span>
                            <p class="text-cocoa-600 italic mt-0.5 text-xs">{{ $order->notes_text }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Lifecycle Actions --}}
            @if (!in_array($order->status, ['completed', 'cancelled'], true))
            <div class="bg-white border border-cocoa-100 rounded-xl p-5 space-y-3">
                <h2 id="order-actions" class="anchor-target text-sm font-semibold text-cocoa-600">Order actions</h2>

                @if ($order->canStartPreparation())
                    <form action="{{ route('orders.updateStatus', $order) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="preparing">
                        <button type="submit" class="w-full py-2.5 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm rounded-lg transition flex items-center justify-center gap-2 ">
                            <span>Start Preparing (Baking)</span>
                        </button>
                    </form>
                @elseif ($order->status === 'confirmed')
                    <p class="p-3 bg-amber-50 rounded-lg text-sm">{{ $order->needsStaffReview() ? 'Confirm this request before accepting a deposit.' : ($order->amount_paid === 0.0 ? 'Verify the exact 50% deposit before starting preparation.' : 'The Owner must reconcile this payment record before starting preparation.') }}</p>
                @elseif ($order->status === 'preparing')
                    <form action="{{ route('orders.updateStatus', $order) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="ready_for_pickup">
                        <button type="submit" class="w-full py-2.5 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm rounded-lg transition flex items-center justify-center gap-2 ">
                            <span>Mark Ready for Pickup</span>
                        </button>
                    </form>
                @elseif ($order->status === 'ready_for_pickup')
                    @if ($order->remaining_balance > 0)
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-cocoa-600">
                            <strong>At pickup:</strong> The Owner verifies ₱{{ number_format($order->remaining_balance, 2) }} and completes pickup when the customer collects the order.
                        </div>
                    @else
                        <form action="{{ route('orders.updateStatus', $order) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="w-full py-2.5 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm rounded-lg transition flex items-center justify-center gap-2 ">
                                <span>Complete Order</span>
                            </button>
                        </form>
                    @endif
                @endif

                @if (Gate::allows('cancel-orders') && $order->canBeCancelled())
                    <div x-data="{ cancelModalOpen: false }" class="pt-2">
                        <button type="button" @click="cancelModalOpen = true" class="w-full py-2 bg-white hover:bg-red-50 text-red-600 font-medium text-sm rounded-lg border border-red-200 transition">
                            <span>Customer cancellation</span>
                        </button>

                        <div x-show="cancelModalOpen"
                             x-cloak
                             data-dialog
                             role="dialog"
                             aria-modal="true"
                             aria-labelledby="cancel-order-dialog-title"
                             @keydown.escape.window="cancelModalOpen = false"
                             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
                            <div @click.outside="cancelModalOpen = false"
                                 class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-cocoa-100 space-y-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    </div>
                                    <div>
                                        <h3 id="cancel-order-dialog-title" class="font-bold text-cocoa-700 text-base">Cancel Order {{ $order->order_number }}</h3>
                                        <p class="text-xs text-cocoa-500">Customer cancellation policy</p>
                                    </div>
                                </div>

                                <p class="text-sm text-cocoa-600 leading-relaxed">
                                    @if($order->hasVerifiedDeposit())
                                        Cancel this order? The verified 50% deposit of ₱{{ number_format($order->required_down_payment, 2) }} is retained as cancellation collection. The remaining balance is no longer due.
                                    @else
                                        Cancel this unpaid order? This closes the customer's request.
                                    @endif
                                </p>

                                <form action="{{ route('orders.cancel', $order) }}" method="POST" class="space-y-4 pt-2">
                                    @csrf
                                    @if ($order->amount_paid === 0.0 && $order->paymentProofs->isNotEmpty())
                                        <label class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900 cursor-pointer">
                                            <input type="checkbox" name="no_funds_checked" value="1" required class="mt-0.5 rounded border-amber-300 text-red-600 focus:ring-red-500">
                                            <span>I checked every reported transfer in the business account and no funds were received. If funds arrived, I must verify and record the exact deposit before cancelling under the retained-deposit policy.</span>
                                        </label>
                                    @endif

                                    <div class="flex items-center justify-end gap-3 pt-2">
                                        <button type="button" @click="cancelModalOpen = false" class="px-4 py-2 text-sm font-medium text-cocoa-600 hover:text-cocoa-800 bg-cream-100 hover:bg-cream-200 rounded-lg transition">
                                            Keep order
                                        </button>
                                        <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 active:bg-red-800 rounded-lg transition shadow-xs">
                                            Yes, cancel order
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            @endif
        </div>

    </div>
</div>
@endsection
