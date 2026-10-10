@extends('layouts.admin')

@section('title', 'Dashboard')

@section('splash')
@php
    $playIntro = session('fresh_login') || request()->boolean('intro');
@endphp

@if ($playIntro)
    {{-- Fullscreen Welcome Hero Splash Minimizing into Dashboard Banner --}}
    <div id="hero-login-splash"
         x-data="heroLoginIntro()"
         x-init="start()"
         @click="skip()"
         @keydown.window.escape="skip()"
         @keydown.window.space="skip()"
         @keydown.window.enter="skip()"
         aria-live="polite"
         class="fixed inset-0 z-[100] flex items-center overflow-hidden bg-cocoa-950 text-white cursor-pointer select-none">
        
        {{-- Background Image --}}
        <div class="absolute inset-0 z-0">
            <img src="{{ asset('storage/catalog/packages/simple_2_layer_chocolate_moist.jpg') }}" 
                 alt="Aling Chona Cakes Welcome" 
                 class="w-full h-full object-cover object-right sm:object-center filter brightness-[0.92] contrast-[1.05]" />
        </div>

        {{-- Deep Cocoa Contrast Scrims matching Dashboard Banner --}}
        <div class="absolute inset-0 z-10 bg-gradient-to-r from-cocoa-950/95 via-cocoa-950/75 sm:via-cocoa-950/45 to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 z-10 bg-gradient-to-t from-cocoa-950/40 via-transparent to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 z-10 pointer-events-none" style="background: linear-gradient(to left, rgba(0,0,0,0.25), rgba(0,0,0,0));"></div>

        {{-- Skip Pill Button --}}
        <button type="button" @click.stop="skip()" 
                aria-label="Skip welcome animation"
                class="splash-skip-btn absolute top-5 right-5 sm:top-6 sm:right-6 z-30 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-black/45 hover:bg-black/70 text-white/85 hover:text-white border border-white/20 text-xs backdrop-blur-md cursor-pointer shadow-lg">
            <span>Skip</span>
            <span class="text-[10px] text-white/50">(ESC)</span>
            <svg class="w-3.5 h-3.5 ml-0.5 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        {{-- Foreground Welcome Presentation --}}
        <div class="splash-content relative z-20 w-full px-6 sm:px-10 md:px-14 lg:px-16 py-8 flex flex-col justify-between max-w-5xl">
            <div class="space-y-3 sm:space-y-4">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-400 shadow-md" style="color: #5c3a21;">
                        <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #5c3a21;"></span>
                        <span>Signature Bake</span>
                    </span>
                    <span class="text-xs sm:text-sm text-amber-200/90 font-medium">· Chocolate Moist Packages</span>
                </div>

                <div>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight drop-shadow-md">
                        Welcome back, {{ Auth::user()->first_name }}
                    </h1>
                    <p class="text-xs sm:text-sm md:text-base text-stone-200 mt-1 sm:mt-2 max-w-2xl leading-relaxed">
                        {{ \App\Support\PickupCalendar::today()->format('l, F j, Y') }} ({{ config('bakery.pickup_timezone') }}) · Operations Active
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.heroLoginIntro = function() {
            return {
                dockTimer: null,
                cleanupTimer: null,
                start() {
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        this.skip();
                        return;
                    }
                    document.body.classList.add('hero-intro-active');
                    this.dockTimer = setTimeout(() => {
                        this.dock();
                    }, 650);
                },
                dock() {
                    const splash = document.getElementById('hero-login-splash');
                    const target = document.getElementById('dashboard-hero-banner');
                    if (!splash || !target) {
                        this.skip();
                        return;
                    }

                    const rect = target.getBoundingClientRect();
                    splash.classList.add('is-docking');
                    document.body.classList.add('hero-intro-docking');

                    splash.style.top = rect.top + 'px';
                    splash.style.left = rect.left + 'px';
                    splash.style.width = rect.width + 'px';
                    splash.style.height = rect.height + 'px';
                    splash.style.borderRadius = '1rem';
                    splash.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.3)';

                    this.cleanupTimer = setTimeout(() => {
                        splash.classList.add('is-hidden');
                        setTimeout(() => {
                            splash.style.display = 'none';
                            document.body.classList.remove('hero-intro-active', 'hero-intro-docking');
                        }, 350);
                    }, 850);
                },
                skip() {
                    if (this.dockTimer) clearTimeout(this.dockTimer);
                    if (this.cleanupTimer) clearTimeout(this.cleanupTimer);
                    const splash = document.getElementById('hero-login-splash');
                    if (splash) {
                        splash.style.display = 'none';
                    }
                    document.body.classList.remove('hero-intro-active', 'hero-intro-docking');
                }
            };
        };
    </script>
@endif
@endsection

@section('content')
<div class="space-y-6">
    {{-- Hero Carousel Overlay Banner --}}
    <div id="dashboard-hero-banner"
         x-data="{
        slides: [
            {
                image: '{{ asset('storage/catalog/packages/simple_2_layer_chocolate_moist.jpg') }}',
                title: 'Chocolate Moist Packages',
                tag: 'Signature Bake',
                desc: 'Rich handcrafted chocolate cakes baked fresh for celebrations'
            },
            {
                image: '{{ asset('storage/catalog/packages/simple_2_layer_chiffon.jpg') }}',
                title: 'Vanilla Chiffon Delights',
                tag: 'Customer Favorite',
                desc: 'Fluffy chiffon layers paired with complimentary celebration extras'
            },
            {
                image: '{{ asset('storage/catalog/packages/simple_1_layer_chocolate_moist.jpg') }}',
                title: 'Deluxe Feast Bundles',
                tag: 'Deluxe Collection',
                desc: 'Two-tier centerpieces with traditional Filipino dessert pairings'
            },
            {
                image: '{{ asset('storage/catalog/packages/simple_1_layer_chiffon.jpg') }}',
                title: 'Celebration Treats & Cupcakes',
                tag: 'Daily Specialty',
                desc: 'Custom-themed decorations and handcrafted party add-ons'
            }
        ],
        active: 0,
        autoplayTimer: null,
        start() {
            this.autoplayTimer = setInterval(() => { this.next(); }, 6000);
        },
        stop() {
            if (this.autoplayTimer) clearInterval(this.autoplayTimer);
        },
        next() {
            this.active = (this.active + 1) % this.slides.length;
        },
        prev() {
            this.active = (this.active - 1 + this.slides.length) % this.slides.length;
        }
    }" 
    x-init="start()" 
    @mouseenter="stop()" 
    @mouseleave="start()" 
    class="relative rounded-2xl overflow-hidden border border-cocoa-200/60 shadow-lg bg-cocoa-950 text-white min-h-[220px] flex items-center">
        
        {{-- Carousel Slides (Background Images) --}}
        <div class="absolute inset-0 z-0">
            <template x-for="(slide, index) in slides" :key="index">
                <div x-show="active === index"
                     x-transition:enter="transition-opacity ease-out duration-700"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-500"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute inset-0">
                    <img :src="slide.image" :alt="slide.title" class="w-full h-full object-cover object-right sm:object-center filter brightness-[0.92] contrast-[1.05]" />
                </div>
            </template>
        </div>

        {{-- Deep Cocoa Contrast Scrim (Left-Heavy Gradient to guarantee 100% text legibility) --}}
        <div class="absolute inset-0 z-10 bg-gradient-to-r from-cocoa-950/95 via-cocoa-950/75 sm:via-cocoa-950/45 to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 z-10 bg-gradient-to-t from-cocoa-950/40 via-transparent to-transparent pointer-events-none"></div>
        {{-- Right-to-Left Contrast Scrim to protect controls and buttons against bright background images --}}
        <div class="absolute inset-0 z-10 pointer-events-none" style="background: linear-gradient(to left, rgba(0,0,0,0.25), rgba(0,0,0,0));"></div>

        {{-- Foreground Overlay Content --}}
        <div class="relative z-20 w-full px-4 py-4 sm:px-6 sm:py-6 md:px-8 md:py-7 flex flex-col md:flex-row md:items-center justify-between gap-4 sm:gap-6">
            <div class="max-w-xl space-y-2 sm:space-y-2.5">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-400 shadow-sm" style="color: #5c3a21;">
                        <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background-color: #5c3a21;"></span>
                        <span x-text="slides[active].tag">Customer Favorite</span>
                    </span>
                    <span class="text-xs text-amber-200/90 font-medium">· <span x-text="slides[active].title">Cake Packages</span></span>
                </div>

                <div>
                    <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-white tracking-tight drop-shadow-sm">
                        Welcome back, {{ Auth::user()->first_name }}
                    </h1>
                    <p class="text-[11px] sm:text-xs md:text-sm text-stone-200 mt-0.5 sm:mt-1">
                        {{ \App\Support\PickupCalendar::today()->format('l, F j, Y') }} ({{ config('bakery.pickup_timezone') }}) · Operations Active
                    </p>
                </div>
            </div>

            {{-- Quick Actions & Controls inside Overlay --}}
            <div class="flex flex-col sm:flex-row md:flex-col lg:flex-row items-start sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    @can('manage-orders')
                    <a href="{{ route('orders.create') }}" class="inline-flex items-center gap-1.5 bg-amber-400 hover:bg-amber-300 text-cocoa-950 font-bold text-xs sm:text-sm px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl shadow-md transition hover:-translate-y-0.5">
                        <x-icon name="plus" class="w-4 h-4" /> New Staff Order
                    </a>
                    @endcan
                    <a href="{{ route('public.order.index') }}" target="_blank" class="inline-flex items-center gap-1.5 bg-white/20 hover:bg-white/30 text-white border border-white/25 font-semibold text-xs sm:text-sm px-3 py-2 sm:px-3.5 sm:py-2.5 rounded-xl backdrop-blur-md transition hover:-translate-y-0.5">
                        Public Store <x-icon name="external" class="w-3.5 h-3.5 ml-0.5" />
                    </a>
                    <a href="{{ route('dashboard', ['intro' => 1]) }}" title="Replay welcome intro animation" class="inline-flex items-center gap-1.5 bg-white/10 hover:bg-white/20 text-white/90 hover:text-white border border-white/20 font-semibold text-xs sm:text-sm px-2.5 py-2 sm:px-3 sm:py-2.5 rounded-xl backdrop-blur-md transition hover:-translate-y-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span class="hidden sm:inline">Replay</span>
                    </a>
                </div>

                {{-- Carousel Controls: Pill Dots & Step Buttons --}}
                <div class="flex items-center gap-1.5 self-end sm:self-auto bg-black/50 backdrop-blur-md px-2.5 py-1 rounded-full border border-white/15 shadow-sm">
                    <button type="button" @click="prev()" aria-label="Previous cake" 
                            class="text-white/80 hover:text-white transition cursor-pointer"
                            style="min-height:unset; height:22px; width:22px; padding:0; border:none; background:transparent; display:flex; align-items:center; justify-content:center;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="flex items-center px-0.5">
                        <template x-for="(slide, index) in slides" :key="index">
                            <button type="button" @click="active = index" :aria-label="'Slide ' + (index + 1)"
                                     class="flex items-center justify-center cursor-pointer focus:outline-none"
                                     style="min-height:unset; height:18px; background:transparent; border:none; padding:0 3px;">
                                <span class="block rounded-full transition-all duration-300"
                                      :class="active === index ? 'w-5 h-1.5 bg-amber-400' : 'w-1.5 h-1.5 bg-white/40 hover:bg-white/70'"></span>
                            </button>
                        </template>
                    </div>
                    <button type="button" @click="next()" aria-label="Next cake" 
                            class="text-white/80 hover:text-white transition cursor-pointer"
                            style="min-height:unset; height:22px; width:22px; padding:0; border:none; background:transparent; display:flex; align-items:center; justify-content:center;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Metric Cards: Responsive Chevron Pipeline Flow Bar (2x2 grid on mobile, interlocking flow on desktop) --}}
    <div id="dashboard-kpi-ledger" class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-0 lg:bg-white lg:border lg:border-cocoa-200/80 lg:rounded-2xl lg:shadow-sm lg:overflow-hidden">
        {{-- 1. Pending Review --}}
        <a href="{{ route('orders.index', ['queue' => 'review']) }}" 
           class="relative bg-white border border-cocoa-100/90 rounded-xl p-3.5 sm:p-5 shadow-sm lg:rounded-none lg:border-0 lg:shadow-none lg:py-4 lg:sm:py-5 lg:px-6 lg:pr-10 hover:bg-cream-50/60 transition-all flex flex-col justify-between group min-w-0">
            <div class="w-full min-w-0">
                <div class="flex items-start justify-between gap-1.5 sm:gap-2 min-w-0">
                    <span class="text-[11px] sm:text-xs font-semibold text-cocoa-500 tracking-wide leading-tight group-hover:text-cocoa-700 transition-colors">Pending Review</span>
                    @if ($pendingCount > 0)
                        <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-semibold bg-amber-100/80 text-amber-900 border border-amber-200/60 shrink-0">
                            Action
                        </span>
                    @else
                        <span class="text-[10px] sm:text-[11px] font-medium text-cocoa-400 shrink-0">All clear</span>
                    @endif
                </div>
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-cocoa-900 mt-1.5 sm:mt-2 tabular-nums break-words">
                    {{ $pendingCount }}
                </div>
            </div>
            <div class="text-[11px] sm:text-xs text-cocoa-400 mt-2 break-words leading-relaxed group-hover:text-cocoa-500 transition-colors">
                New orders awaiting confirmation
            </div>

            {{-- Chevron separator for desktop --}}
            <div class="absolute top-0 right-0 hidden lg:block h-full w-7 pointer-events-none translate-x-full z-10" aria-hidden="true">
                <svg class="h-full w-full text-cocoa-200" viewBox="0 0 28 100" fill="none" preserveAspectRatio="none">
                    <path d="M0 0 L26 50 L0 100" vector-effect="non-scaling-stroke" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
            </div>
        </a>

        {{-- 2. Paid Bookings & Active Prep --}}
        <div class="relative bg-white border border-cocoa-100/90 rounded-xl p-3.5 sm:p-5 shadow-sm lg:rounded-none lg:border-0 lg:shadow-none lg:py-4 lg:sm:py-5 lg:pl-10 lg:pr-10 flex flex-col justify-between group min-w-0">
            <div class="w-full min-w-0">
                <div class="flex items-start justify-between gap-1.5 sm:gap-2 min-w-0">
                    <a href="{{ route('orders.index', ['queue' => 'booked']) }}" class="text-[11px] sm:text-xs font-semibold text-cocoa-500 tracking-wide leading-tight hover:text-cocoa-700 transition-colors">
                        Paid Bookings
                    </a>
                    <span class="text-[10px] sm:text-[11px] font-medium text-cocoa-400 shrink-0">In production</span>
                </div>
                <a href="{{ route('orders.index', ['queue' => 'booked']) }}" class="block">
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-cocoa-900 mt-1.5 sm:mt-2 tabular-nums break-words">
                        {{ $activeOrdersCount }}
                    </div>
                </a>
            </div>

            @if ($awaitingDepositCount > 0 || $awaitingReceiptCount > 0)
                <div class="text-[11px] sm:text-xs text-cocoa-400 mt-2 min-w-0 leading-relaxed flex items-center flex-nowrap gap-1.5">
                    @if ($awaitingDepositCount > 0)
                        <a href="{{ route('orders.index', ['queue' => 'deposit']) }}" 
                           title="Confirmed — awaiting deposit: {{ $awaitingDepositCount }}" 
                           class="inline-flex items-center gap-1 text-amber-800 hover:text-amber-950 font-semibold hover:underline shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                            <span>{{ $awaitingDepositCount }} deposit</span>
                            <span class="sr-only">Confirmed — awaiting deposit: {{ $awaitingDepositCount }}</span>
                        </a>
                    @endif
                    @if ($awaitingDepositCount > 0 && $awaitingReceiptCount > 0)
                        <span class="text-cocoa-300 select-none shrink-0">·</span>
                    @endif
                    @if ($awaitingReceiptCount > 0)
                        <a href="{{ route('orders.index', ['queue' => 'receipts']) }}" 
                           title="Receipt awaiting verification: {{ $awaitingReceiptCount }}" 
                           class="inline-flex items-center gap-1 text-blue-800 hover:text-blue-950 font-semibold hover:underline shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                            <span>{{ $awaitingReceiptCount }} receipts</span>
                            <span class="sr-only">Receipt awaiting verification: {{ $awaitingReceiptCount }}</span>
                        </a>
                    @endif
                </div>
            @else
                <div class="text-[11px] sm:text-xs text-cocoa-400 mt-2 break-words leading-relaxed">
                    Deposit verified · baking & pickup
                </div>
            @endif

            {{-- Chevron separator for desktop --}}
            <div class="absolute top-0 right-0 hidden lg:block h-full w-7 pointer-events-none translate-x-full z-10" aria-hidden="true">
                <svg class="h-full w-full text-cocoa-200" viewBox="0 0 28 100" fill="none" preserveAspectRatio="none">
                    <path d="M0 0 L26 50 L0 100" vector-effect="non-scaling-stroke" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
            </div>
        </div>

        {{-- 3. Completed Orders --}}
        <a href="{{ route('orders.index', ['status' => 'completed']) }}" 
           class="relative bg-white border border-cocoa-100/90 rounded-xl p-3.5 sm:p-5 shadow-sm lg:rounded-none lg:border-0 lg:shadow-none lg:py-4 lg:sm:py-5 lg:pl-10 lg:pr-10 hover:bg-cream-50/60 transition-all flex flex-col justify-between group min-w-0">
            <div class="w-full min-w-0">
                <div class="flex items-start justify-between gap-1.5 sm:gap-2 min-w-0">
                    <span class="text-[11px] sm:text-xs font-semibold text-cocoa-500 tracking-wide leading-tight group-hover:text-cocoa-700 transition-colors">Completed</span>
                    <span class="text-[10px] sm:text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200/60 shrink-0">Settled</span>
                </div>
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-cocoa-900 mt-1.5 sm:mt-2 tabular-nums break-words">
                    {{ $completedCount }}
                </div>
            </div>
            <div class="text-[11px] sm:text-xs text-cocoa-400 mt-2 break-words leading-relaxed group-hover:text-cocoa-500 transition-colors">
                Delivered & settled in full
            </div>

            {{-- Chevron separator for desktop --}}
            <div class="absolute top-0 right-0 hidden lg:block h-full w-7 pointer-events-none translate-x-full z-10" aria-hidden="true">
                <svg class="h-full w-full text-cocoa-200" viewBox="0 0 28 100" fill="none" preserveAspectRatio="none">
                    <path d="M0 0 L26 50 L0 100" vector-effect="non-scaling-stroke" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
            </div>
        </a>

        {{-- 4. Low Stock Supplies (with balanced right-side padding/offset shift) --}}
        <a href="{{ route('supplies.index') }}" 
           aria-label="Low stock supplies: {{ $lowStockCount }} items" 
           class="relative bg-white border border-cocoa-100/90 rounded-xl p-3.5 sm:p-5 shadow-sm lg:rounded-none lg:border-0 lg:shadow-none lg:py-4 lg:sm:py-5 lg:pl-12 lg:pr-7 hover:bg-cream-50/60 transition-all flex flex-col justify-between group min-w-0 {{ $lowStockCount > 0 ? 'bg-red-50/20' : '' }}">
            <div class="w-full min-w-0">
                <div class="flex items-start justify-between gap-1.5 sm:gap-2 min-w-0">
                    <span class="text-[11px] sm:text-xs font-semibold {{ $lowStockCount > 0 ? 'text-red-700' : 'text-cocoa-500' }} tracking-wide leading-tight group-hover:text-cocoa-700 transition-colors">
                        Inventory Alerts
                    </span>
                    @if ($lowStockCount > 0)
                        <span class="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-semibold bg-red-100 text-red-800 border border-red-200 shrink-0">
                            Reorder
                        </span>
                    @else
                        <span class="text-[10px] sm:text-[11px] font-medium text-cocoa-400 shrink-0">Stocked</span>
                    @endif
                </div>
                <div class="text-2xl sm:text-3xl font-extrabold tracking-tight {{ $lowStockCount > 0 ? 'text-red-600' : 'text-cocoa-900' }} mt-1.5 sm:mt-2 tabular-nums break-words">
                    {{ $lowStockCount }}
                </div>
            </div>
            <div class="text-[11px] sm:text-xs {{ $lowStockCount > 0 ? 'text-red-600 font-medium' : 'text-cocoa-400' }} mt-2 break-words leading-relaxed">
                {{ $lowStockCount > 0 ? 'Supplies below minimum threshold' : 'All bakery supplies in stock' }}
            </div>
        </a>
    </div>

    {{-- Two-Column Grid: Stacked vertically on mobile, 3-column layout on desktop --}}
    <div id="dashboard-workspace-grid" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Today's Pickups --}}
        <div class="bg-white border border-cocoa-100 rounded-xl shadow-sm">
            <div class="flex items-center justify-between px-4 py-3.5 sm:px-5 sm:py-4 border-b border-cocoa-100">
                <h2 class="text-sm font-semibold text-cocoa-600">Today's Pickups ({{ $todayPickups->count() }})</h2>
                <a href="{{ route('schedule.index') }}" class="text-xs text-cocoa-500 hover:text-cocoa-700 font-medium transition">View All</a>
            </div>

            @if ($todayPickups->isEmpty())
                <div class="px-5 py-8 sm:py-10 text-center space-y-2.5">
                    <div class="w-12 h-12 rounded-full bg-[#f3ece6] border border-[#e5dcd3] text-[#5c3a21] flex items-center justify-center mx-auto shadow-sm">
                        <x-icon name="calendar" class="w-6 h-6 text-[#5c3a21]" />
                    </div>
                    <p class="text-xs font-semibold text-cocoa-700">No pickups scheduled for today</p>
                    <p class="text-[11px] text-cocoa-400 max-w-xs mx-auto leading-relaxed">Confirmed customer orders scheduled for pickup today will appear here.</p>
                </div>
            @else
                <div class="divide-y divide-cocoa-100/60">
                    @foreach ($todayPickups as $order)
                        <div class="px-5 py-3 hover:bg-cream-50 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('orders.show', $order) }}" class="text-sm font-semibold text-cocoa-600 hover:text-cocoa-700 transition">
                                        {{ $order->order_number }}
                                    </a>
                                    <div class="text-xs text-cocoa-500 mt-0.5 truncate">{{ $order->customer->full_name }}</div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="text-xs font-mono font-semibold text-cocoa-500">
                                        {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                    </span>
                                    <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Orders --}}
        <div class="lg:col-span-2 bg-white border border-cocoa-100 rounded-xl shadow-sm">
            <div class="flex items-center justify-between px-4 py-3.5 sm:px-5 sm:py-4 border-b border-cocoa-100">
                <h2 class="text-sm font-semibold text-cocoa-600">Recent Orders</h2>
                <a href="{{ route('orders.index') }}" class="text-xs text-cocoa-500 hover:text-cocoa-700 font-medium transition">View All Orders</a>
            </div>

            @if ($recentOrders->isEmpty())
                <div class="px-5 py-8 sm:py-10 text-center space-y-2.5">
                    <div class="w-12 h-12 rounded-full bg-[#f3ece6] border border-[#e5dcd3] text-[#5c3a21] flex items-center justify-center mx-auto shadow-sm">
                        <x-icon name="shopping-cart" class="w-6 h-6 text-[#5c3a21]" />
                    </div>
                    <p class="text-xs font-semibold text-cocoa-700">No orders recorded yet</p>
                    <p class="text-[11px] text-cocoa-400 max-w-xs mx-auto leading-relaxed">New staff orders and customer storefront requests will populate here.</p>
                </div>
            @else
                <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-cream-100 text-cocoa-400 text-xs font-semibold">
                                <th class="px-5 py-3">Order</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Pickup</th>
                                <th class="px-5 py-3">Total</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-cocoa-100/60">
                            @foreach ($recentOrders as $order)
                                <tr class="text-sm hover:bg-cream-50 transition">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('orders.show', $order) }}" class="font-mono font-semibold text-cocoa-600 hover:text-cocoa-700 transition">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 text-cocoa-500">{{ $order->customer->full_name }}</td>
                                    <td class="px-5 py-3 text-cocoa-400 text-xs">
                                        {{ $order->pickup_date->format('M d') }}, {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-cocoa-600">₱{{ number_format($order->total_amount, 2) }}</td>
                                    <td class="px-5 py-3">
                                        <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="text-xs font-semibold text-cocoa-500 hover:text-cocoa-700 bg-cream-50 hover:bg-cream-100 border border-cocoa-100 px-3 py-1.5 rounded-lg transition inline-block">{{ $order->status === 'pending' ? 'Review' : 'View' }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    {{-- Reusable Skeleton Template for Dynamic Dashboard Transitions --}}
    <template id="dashboard-skeleton">
        @include('partials.dashboard-skeleton')
    </template>
</div>
@endsection
