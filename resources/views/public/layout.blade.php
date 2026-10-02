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
    <main id="main-content" tabindex="-1" class="flex-grow max-w-7xl 2xl:max-w-[1600px] w-full mx-auto px-4 py-8 sm:px-6 lg:px-8 2xl:px-12">
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
    <footer class="bg-cocoa-700 text-cocoa-200 py-8 mt-12">
        <div class="max-w-7xl 2xl:max-w-[1600px] w-full mx-auto px-4 sm:px-6 lg:px-8 2xl:px-12">
            <div class="flex flex-wrap items-center justify-between gap-6 text-xs">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-8 h-8 rounded-lg bg-cocoa-300/20 flex items-center justify-center text-cocoa-200 text-sm font-bold shrink-0">A</span>
                    <div class="min-w-0">
                        <span class="font-semibold text-white block">Aling Chona Cakes & Cupcakes</span>
                        <span class="text-cocoa-200 block">Custom Bakes & Celebration Delights</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-cocoa-200 shrink-0">
                    <span>Cash & GCash Accepted</span>
                    <span>&copy; {{ date('Y') }}</span>
                </div>
            </div>
        </div>
    </footer>
    @include('partials.validation-data')
</body>
</html>
