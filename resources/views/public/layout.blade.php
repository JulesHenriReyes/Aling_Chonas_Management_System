<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Aling Chona Cakes and Cupcakes - Order Custom Bakery Treats')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        cream: { 50: '#FDFBF7', 100: '#F5F1EB' },
                        cocoa: { 50: '#F5F1EB', 100: '#E8E0D4', 200: '#D4C4B0', 300: '#C2956B', 400: '#716153', 500: '#5C4A3A', 600: '#3C2415', 700: '#2C1810', 800: '#1A0E08' },
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @include('partials.ui-assets')
</head>
<body class="public-store bg-cream-50 text-cocoa-500 font-sans min-h-screen flex flex-col">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    {{-- Header --}}
    <header class="bg-white border-b border-cocoa-100 sticky top-0 z-40">
        <div class="max-w-7xl 2xl:max-w-[1600px] w-full mx-auto px-4 sm:px-6 lg:px-8 2xl:px-12 flex flex-wrap items-center justify-between gap-4 min-h-[4rem] py-3">
            <a href="{{ route('public.order.index') }}" class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-cocoa-600 flex items-center justify-center text-white text-lg font-bold shrink-0 shadow-sm">A</span>
                <div class="min-w-0">
                    <span class="text-base font-bold text-cocoa-600 leading-tight tracking-tight block whitespace-nowrap">Aling Chona</span>
                    <p class="text-xs text-cocoa-500 leading-tight whitespace-nowrap">Cakes & Cupcakes</p>
                </div>
            </a>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('login') }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-cocoa-100 text-cocoa-500 hover:text-cocoa-700 hover:bg-cream-100 transition">
                    Staff Login
                </a>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main id="main-content" tabindex="-1" class="flex-grow max-w-7xl 2xl:max-w-[1600px] w-full mx-auto px-4 py-6 sm:px-6 lg:px-8 2xl:px-12">
        @if (session('info'))
            <div role="alert" class="mb-6 px-4 py-3 rounded-lg bg-cream-100 border border-blue-200 text-blue-800 text-sm flex items-center gap-2">
                <x-icon path="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" class="w-4 h-4 flex-shrink-0 text-blue-600" />
                <span>{{ session('info') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div data-error-summary role="alert" tabindex="-1" class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                <div class="font-semibold mb-1">Please correct the following errors:</div>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Footer --}}
    <footer x-data="{ showLocationMap: false }" class="bg-cocoa-700 text-cocoa-100 py-8 px-4 sm:px-6 lg:px-8 2xl:px-12 mt-6">
        <div class="max-w-[1216px] 2xl:max-w-[1504px] w-full mx-auto space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
                {{-- Column 1: Brand & Tagline --}}
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-cocoa-300/20 flex items-center justify-center text-cocoa-100 text-sm font-bold shrink-0">A</span>
                        <div class="min-w-0">
                            <span class="font-bold text-white text-sm block">Aling Chona Cakes & Cupcakes</span>
                            <span class="text-cocoa-200 text-xs block">Custom Bakes & Celebration Delights</span>
                        </div>
                    </div>
                    <p class="text-cocoa-200/90 leading-relaxed text-[11px] pt-1">
                        Specializing in freshly baked customized cakes and party cupcakes for birthdays, weddings, and family celebrations.
                    </p>
                    <p class="text-cocoa-300/80 text-[11px] pt-1">
                        &copy; {{ date('Y') }} Aling Chona Cakes & Cupcakes
                    </p>
                </div>

                {{-- Column 2: Contact & Inquiries --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Contact & Inquiries</h3>
                    <ul class="space-y-2 text-cocoa-200">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-cocoa-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span>Phone: <strong class="text-white">{{ config('bakery.contact_phone', '0917 123 4567') }}</strong></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-cocoa-300 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            <span>Facebook: <a href="{{ config('bakery.facebook_url', 'https://www.facebook.com/chonaejay.hinay#') }}" target="_blank" rel="noopener noreferrer" class="text-white hover:underline font-semibold">{{ config('bakery.facebook_name', 'Aling Chona Cake & Cupcake') }}</a></span>
                        </li>
                        <li class="flex items-center gap-2 text-[11px] text-cocoa-200">
                            <svg class="w-4 h-4 text-cocoa-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <span>Payment: <strong class="text-white font-medium">Cash & GCash Accepted</strong></span>
                        </li>
                    </ul>
                </div>

                {{-- Column 3: Store & Pickups --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Pickup Location</h3>
                    <ul class="space-y-1.5 text-cocoa-200">
                        <li class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-cocoa-300 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <div>
                                <button type="button"
                                        @click="showLocationMap = true"
                                        class="text-left font-semibold text-white hover:text-amber-200 transition cursor-pointer inline-flex items-center gap-1.5 group"
                                        title="Click to view pickup map">
                                    <span class="group-hover:underline underline-offset-2">{{ config('bakery.location', 'Aling Chona Store & Residence') }}</span>
                                    <span class="text-[10px] bg-cocoa-600 group-hover:bg-cocoa-500 text-amber-200 px-1.5 py-0.5 rounded font-normal shrink-0">Map ↗</span>
                                </button>
                                <p class="text-[11px] text-cocoa-300 mt-1">Available for scheduled order pickups upon confirmation.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Pickup Location Map Modal --}}
        <div x-show="showLocationMap"
             x-cloak
             data-dialog
             role="dialog"
             aria-modal="true"
             aria-labelledby="map-modal-title"
             @keydown.escape.window="showLocationMap = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-[2px]"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="display: none;">
            <div class="relative w-full max-w-lg sm:max-w-xl bg-white text-cocoa-600 rounded-2xl shadow-2xl border border-cocoa-100 overflow-hidden"
                 @click.away="showLocationMap = false"
                 x-show="showLocationMap"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-[0.97] -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-[0.97] -translate-y-1">
                <div class="px-5 py-3.5 border-b border-cocoa-100/70 flex items-center justify-between gap-3 bg-cream-50/60">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-cocoa-600 text-white flex items-center justify-center text-xs shrink-0">📍</div>
                        <div>
                            <h3 id="map-modal-title" class="text-sm font-bold text-cocoa-700 leading-tight">Aling Chona Store & Residence</h3>
                            <p class="text-[11px] text-cocoa-400">Order pickup location & directions</p>
                        </div>
                    </div>
                    <button type="button"
                            @click="showLocationMap = false"
                            class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition focus:outline-none"
                            aria-label="Close map dialog">
                        <x-icon name="close" class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-4 sm:p-5">
                    <div class="w-full h-64 sm:h-80 rounded-xl overflow-hidden border border-cocoa-100 shadow-inner bg-cream-100">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d629.5647360970282!2d125.45279986724623!3d7.104244596689173!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2sph!4v1791385945199!5m2!1sen!2sph"
                                class="w-full h-full border-0"
                                style="border:0;"
                                allowfullscreen=""
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                    <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <span class="text-cocoa-400 text-[11px]">Available for scheduled pickups upon order confirmation.</span>
                        <div class="flex items-center gap-2">
                            <a href="https://www.google.com/maps/search/?api=1&query=7.104244596689173,125.45279986724623"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="ui-button text-xs py-1.5 px-3 inline-flex items-center gap-1.5">
                                Open in Google Maps ↗
                            </a>
                            <button type="button"
                                    @click="showLocationMap = false"
                                    class="ui-button quiet text-xs py-1.5 px-3">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    @include('partials.validation-data')
</body>
</html>
