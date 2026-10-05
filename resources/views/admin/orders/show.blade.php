@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

@section('content')
<div class="space-y-4">
    {{-- Breadcrumb --}}
    <div class="page-heading">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('orders.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium transition"><x-icon name="arrow-left" class="mr-1" /> Orders</a>
            <span class="text-cocoa-200">/</span>
            <span class="text-cocoa-400 font-mono">{{ $order->order_number }}</span>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold
            {{ $order->user_id === null ? 'bg-cream-100 text-cocoa-500 ' : 'bg-cocoa-50 text-cocoa-500 ' }}">
            {{ $order->user_id === null ? 'Public Web Order' : 'Staff Created (' . $order->user->first_name . ')' }}
        </span>
    </div>

    {{-- Order Header --}}
    <div class="bg-white border border-cocoa-100 rounded-xl p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-cocoa-600 font-mono">{{ $order->order_number }}</h1>
                    <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                    <x-status :value="$order->payment_status" />
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
            <div class="order-money border-t md:border-t-0 md:border-l border-cocoa-100 md:pl-6 pt-4 md:pt-0">
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
                    <span class="text-base font-bold {{ $order->remaining_balance > 0 ? 'text-red-600' : 'text-cocoa-400' }}">
                        ₱{{ number_format($order->remaining_balance, 2) }}
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
            <div class="bg-white border border-cocoa-100 rounded-xl p-4">
                <div class="flex items-center justify-between border-b border-cocoa-100 pb-4 mb-5">
                    <h2 class="text-sm font-semibold text-cocoa-600">Order Items & Customizations</h2>
                    <span class="text-sm text-cocoa-500">Saved prices</span>
                </div>

                <div class="space-y-5">
                    @foreach ($order->orderDetails as $detail)
                        <div class="item-section space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-cocoa-100/60 pb-3">
                                <div>
                                    <h3 class="font-semibold text-cocoa-600 text-sm">{{ $detail->product_name_snapshot ?? $detail->product->product_name }}</h3>
                                    <span class="text-xs text-cocoa-400">Saved package price: ₱{{ number_format($detail->unit_price, 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-cocoa-400 block">Subtotal ({{ $detail->quantity }} qty)</span>
                                    <span class="text-base font-bold text-cocoa-600">₱{{ number_format($detail->subtotal, 2) }}</span>
                                </div>
                            </div>

                            {{-- Customization Details --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-cocoa-500 font-bold block text-xs ">Layers</span>
                                    <span class="text-cocoa-700 font-medium">{{ $detail->layers ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-cocoa-500 font-bold block text-xs ">Theme / Colors</span>
                                    <span class="text-cocoa-700 font-medium">{{ $detail->themes ?? 'None specified' }}</span>
                                </div>
                                <div>
                                    <span class="text-cocoa-500 font-bold block text-xs ">Special Requests</span>
                                    <span class="text-cocoa-700 font-medium">{{ $detail->special_request ?? 'None' }}</span>
                                </div>
                            </div>

                            {{-- Per-Product Reference Images --}}
                            @if ($detail->images->isNotEmpty())
                                <div class="pt-2">
                                    <span class="text-xs text-cocoa-500 font-bold block mb-1.5">Reference Photos:</span>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($detail->images as $img)
                                            <div x-data="{ expanded: false }" class="relative">
                                                <button type="button" @click="expanded = true" class="block border border-cocoa-100 rounded-lg overflow-hidden w-16 h-16 bg-cream-50 hover:opacity-80 transition focus:outline-none">
                                                    <img src="{{ asset('storage/' . $img->file_path) }}" alt="{{ $img->original_filename }}" class="w-full h-full object-cover">
                                                </button>
                                                <template x-teleport="body">
                                                    <div x-show="expanded" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-80 p-4 backdrop-blur-sm" @keydown.escape.window="expanded = false">
                                                        <div class="relative w-full h-full flex justify-center items-center" @click.outside="expanded = false">
                                                            <button @click="expanded = false" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none z-[110]">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                            </button>
                                                            <img src="{{ asset('storage/' . $img->file_path) }}" alt="{{ $img->original_filename }}" class="max-w-full max-h-full object-contain rounded drop-shadow-2xl">
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Fixed catalog snapshot --}}
                            @include('partials.included-items', ['includedItems' => $detail->included_items_snapshot ?? [], 'includedText' => $detail->included_contents_snapshot, 'packageQuantity' => $detail->quantity])
                            @foreach ($detail->addOns as $extra)
                                <p class="text-sm"><strong>Paid extra:</strong> {{ $extra->name_snapshot }} × {{ $extra->quantity }} for this whole order line · ₱{{ number_format($extra->subtotal, 2) }}<br>{{ $extra->description_snapshot }} · ₱{{ number_format($extra->unit_price, 2) }} each</p>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Reference Images --}}
            <div class="bg-white border border-cocoa-100 rounded-xl p-4">
                <div class="flex items-center justify-between border-b border-cocoa-100 pb-4 mb-4">
                    <h2 class="text-sm font-semibold text-cocoa-600">All Reference Images</h2>
                    <span class="text-xs text-cocoa-400">{{ $order->images->count() }} image(s)</span>
                </div>

                @if ($order->images->isNotEmpty())
                    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-4 md:grid-cols-6 gap-3 mb-4">
                        @foreach ($order->images as $img)
                            <div class="border border-cocoa-100 rounded-lg overflow-hidden bg-cream-50" x-data="{ expanded: false }">
                                <button type="button" @click="expanded = true" class="block aspect-square w-full hover:opacity-80 transition focus:outline-none">
                                    <img src="{{ asset('storage/' . $img->file_path) }}" alt="{{ $img->original_filename }}" class="w-full h-full object-cover">
                                </button>
                                <div class="px-1.5 py-1 text-[9px] text-cocoa-400 truncate" title="{{ $img->original_filename }}">
                                    {{ $img->original_filename }}
                                </div>
                                <template x-teleport="body">
                                    <div x-show="expanded" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-80 p-4 backdrop-blur-sm" @keydown.escape.window="expanded = false">
                                        <div class="relative w-full h-full flex justify-center items-center" @click.outside="expanded = false">
                                            <button @click="expanded = false" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none z-[110]">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                            <img src="{{ asset('storage/' . $img->file_path) }}" alt="{{ $img->original_filename }}" class="max-w-full max-h-full object-contain rounded drop-shadow-2xl">
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-cocoa-400 py-4 text-center">No images uploaded for this order.</p>
                @endif

            </div>

            @include('admin.orders.staff-review')
            @include('admin.orders.proof-review')

            {{-- Payments --}}
            <div class="bg-white border border-cocoa-100 rounded-xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-cocoa-100 pb-4">
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
                            Staff has confirmed this request. Recording the verified deposit secures the booking and allows preparation.
                        </p>

                        <form action="{{ route('orders.payments.store', $order) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 pt-2">
                            @csrf
                            <input type="hidden" name="payment_type" value="down_payment">

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="field-admin-orders-show-blade-php-2-{{ $detail->id ?? 0 }}">amount (₱)</label>
                                <input id="field-admin-orders-show-blade-php-2-{{ $detail->id ?? 0 }}" type="number" step="0.01" name="amount" value="{{ $order->required_down_payment }}" readonly
                                       class="w-full text-xs rounded-lg border-cocoa-100 bg-cream-100 font-semibold text-cocoa-600">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="field-admin-orders-show-blade-php-3-{{ $detail->id ?? 0 }}">method</label>
                                <select id="field-admin-orders-show-blade-php-3-{{ $detail->id ?? 0 }}" name="payment_method" required class="w-full text-xs rounded-lg border-cocoa-100 focus:border-cocoa-300 focus:ring-cocoa-300">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="field-admin-orders-show-blade-php-4-{{ $detail->id ?? 0 }}">GCash Ref #</label>
                                <input id="field-admin-orders-show-blade-php-4-{{ $detail->id ?? 0 }}" type="text" name="reference_number" placeholder="Required if GCash"
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
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="field-admin-orders-show-blade-php-5-{{ $detail->id ?? 0 }}">amount (₱)</label>
                                <input id="field-admin-orders-show-blade-php-5-{{ $detail->id ?? 0 }}" type="number" step="0.01" value="{{ $order->remaining_balance }}" readonly
                                       class="w-full text-xs rounded-lg border-cocoa-100 bg-cream-100 font-semibold text-cocoa-600">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="field-admin-orders-show-blade-php-6-{{ $detail->id ?? 0 }}">method</label>
                                <select id="field-admin-orders-show-blade-php-6-{{ $detail->id ?? 0 }}" name="payment_method" x-model="method" required class="w-full text-xs rounded-lg border-cocoa-100 focus:border-cocoa-300 focus:ring-cocoa-300">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-cocoa-600 mb-1" for="field-admin-orders-show-blade-php-7-{{ $detail->id ?? 0 }}">GCash Ref #</label>
                                <input id="field-admin-orders-show-blade-php-7-{{ $detail->id ?? 0 }}" type="text" name="reference_number" value="{{ old('reference_number') }}" :required="method === 'gcash'" placeholder="Required if GCash" maxlength="100"
                                       class="w-full text-xs rounded-lg border-cocoa-100 focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
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
                @if (in_array($order->status, ['pending', 'confirmed', 'preparing'], true))
                    <p class="text-sm text-cocoa-500">Staff confirmation opens the exact 50% deposit. Verification secures the booking. The remaining balance is collected at actual pickup after Ready for pickup.</p>
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

            @include('admin.orders.refund')

            {{-- Lifecycle Actions --}}
            <div class="bg-white border border-cocoa-100 rounded-xl p-5 space-y-3">
                <h2 class="text-sm font-semibold text-cocoa-600">Order actions</h2>

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
                    <p class="p-3 bg-amber-50 rounded-lg text-sm">Preparation is locked until staff confirmation and the exact 50% deposit are verified.</p>
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
                @elseif ($order->status === 'completed')
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800 font-semibold text-center">
                        Order Completed
                    </div>
                @elseif ($order->status === 'cancelled')
                    <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-800 font-semibold text-center">
                        {{ $order->cancellation_kind === 'staff_rejected' ? 'Request declined' : 'Order Cancelled' }}
                    </div>
                @endif

                @if (Gate::allows('cancel-orders') && $order->status !== 'completed' && $order->status !== 'cancelled')
                    <form action="{{ route('orders.cancel', $order) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? The deposit is retained under the customer-cancellation policy. For bakery failure, use the full-refund action instead.');">
                        @csrf
                        @if ($order->amount_paid === 0.0 && $order->paymentProofs->isNotEmpty())
                            <label class="flex items-start gap-3 my-3 text-sm"><input type="checkbox" name="no_funds_checked" value="1" required><span>I checked every reported transfer in the business account and no funds were received. If funds arrived, I must verify them before closing this order.</span></label>
                        @endif
                        <button type="submit" class="w-full py-2 bg-white hover:bg-red-50 text-red-600 font-medium text-sm rounded-lg border border-red-200 transition">
                            <span>Customer cancellation</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
