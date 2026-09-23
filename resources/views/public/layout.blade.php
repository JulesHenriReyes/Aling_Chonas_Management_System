<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Aling Chona Cakes and Cupcakes - Order Custom Bakery Treats')</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-amber-50/40 text-stone-800 font-sans min-h-screen flex flex-col">
    <!-- Header / Navigation -->
    <header class="bg-rose-900 text-white shadow-md sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-3 sm:px-6 flex items-center justify-between">
            <a href="{{ route('public.order.index') }}" class="flex items-center gap-3">
                <span class="text-2xl">🎂</span>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-amber-100">Aling Chona</h1>
                    <p class="text-xs text-rose-200 uppercase tracking-widest">Cakes & Cupcakes</p>
                </div>
            </a>
            <div class="flex items-center gap-4">
                <a href="{{ route('public.order.index') }}" class="text-sm font-medium hover:text-amber-200 transition">Place Order</a>
                <span class="text-rose-400">|</span>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-xs bg-rose-800 hover:bg-rose-700 px-3 py-1.5 rounded text-amber-200 transition font-medium">
                        Staff Dashboard ({{ Auth::user()->first_name }})
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-xs text-rose-200 hover:text-white transition">
                        Staff Login
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-6xl w-full mx-auto px-4 py-8 sm:px-6">
        @if (session('info'))
            <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                <span>{{ session('info') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                <div class="font-bold mb-1">Please correct the following errors:</div>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-stone-900 text-stone-400 text-xs py-6 mt-12 border-t border-stone-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p>&copy; {{ date('Y') }} Aling Chona Cakes and Cupcakes. All rights reserved.</p>
            <div class="flex items-center gap-4 text-stone-400">
                <span>📍 Custom Bakes & Celebration Delights</span>
                <span>•</span>
                <span>💵 Cash & GCash Accepted</span>
            </div>
        </div>
    </footer>
</body>
</html>
