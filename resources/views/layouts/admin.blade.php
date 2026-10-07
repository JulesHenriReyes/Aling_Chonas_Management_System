<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Staff Dashboard') - Aling Chona Cakes & Cupcakes</title>
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
        /* Custom scrollbar for sidebar */
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
    </style>
    @include('partials.ui-assets')
</head>
<body class="bg-cream-50 text-cocoa-500 font-sans min-h-screen" x-data="{ sidebarOpen: false }">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    {{-- Mobile Sidebar Overlay --}}
    <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-out duration-200" x-transition:leave="transition-opacity ease-in duration-150"
         data-sidebar-backdrop class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="sidebarOpen = false" x-cloak></div>

    {{-- Sidebar --}}
    <aside id="staff-navigation" data-sidebar :data-open="sidebarOpen.toString()" aria-label="Staff navigation" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed top-0 left-0 z-50 h-full w-60 bg-cocoa-700 text-cocoa-100 flex flex-col transition-transform duration-200 lg:translate-x-0">

        <button type="button" data-sidebar-close @click="sidebarOpen = false" aria-label="Close navigation" class="sidebar-close self-end p-3 text-white"><x-icon name="close" /></button>
        {{-- Brand --}}
        <div class="px-5 py-5 border-b border-white/10">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-lg bg-cocoa-300 flex items-center justify-center text-white text-lg font-bold">A</span>
                <div>
                    <span class="font-bold text-white text-sm tracking-tight block leading-tight">Aling Chona</span>
                    <span class="text-xs text-cocoa-100 leading-tight">Cakes & Cupcakes</span>
                </div>
            </a>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto sidebar-scroll">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('dashboard') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" class="w-[18px] h-[18px] flex-shrink-0" />
                Dashboard
            </a>

            <a href="{{ route('orders.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('orders.*', 'proofs.*', 'refunds.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" class="w-[18px] h-[18px] flex-shrink-0" />
                Orders
            </a>

            <a href="{{ route('schedule.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('schedule.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" class="w-[18px] h-[18px] flex-shrink-0" />
                Pickup Schedule
            </a>

            <div class="pt-4 pb-1.5 px-3">
                <span class="sidebar-label">Manage</span>
            </div>

            <a href="{{ route('customers.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('customers.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" class="w-[18px] h-[18px] flex-shrink-0" />
                Customers
            </a>

            <a href="{{ route('products.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('products.*', 'add-ons.*', 'options.*', 'payment-settings.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" class="w-[18px] h-[18px] flex-shrink-0" />
                Products
            </a>

            <a href="{{ route('supplies.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('supplies.*', 'inventory.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" class="w-[18px] h-[18px] flex-shrink-0" />
                Inventory
            </a>

            <div class="pt-4 pb-1.5 px-3">
                <span class="sidebar-label">Finance</span>
            </div>

            <a href="{{ route('expenses.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('expenses.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" class="w-[18px] h-[18px] flex-shrink-0" />
                Expenses
            </a>

            @can('view-reports')
            <a href="{{ route('reports.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                      {{ request()->routeIs('reports.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                <x-icon path="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" class="w-[18px] h-[18px] flex-shrink-0" />
                Reports
            </a>
            @endcan

            @can('manage-users')
                <div class="pt-4 pb-1.5 px-3">
                    <span class="sidebar-label">Admin</span>
                </div>

                <a href="{{ route('users.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-r-lg rounded-l-none text-sm transition
                          {{ request()->routeIs('users.*') ? 'border-l-[3px] border-cocoa-300 bg-white/10 text-white font-semibold' : 'border-l-[3px] border-transparent text-cocoa-100 hover:bg-white/5 hover:text-white' }}">
                    <x-icon path="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" class="w-[18px] h-[18px] flex-shrink-0" />
                    <span>Users (Owner)</span>
                </a>
            @endcan
        </nav>

    </aside>

    {{-- Main Content Area --}}
    <div class="lg:pl-60 min-h-screen flex flex-col">
        {{-- Top Bar --}}
        <header id="admin-topbar" class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-cocoa-100">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 h-14">
                {{-- Mobile menu button --}}
                <button type="button" aria-label="Open navigation" aria-controls="staff-navigation" :aria-expanded="sidebarOpen.toString()" @click="sidebarOpen = true" class="lg:hidden p-2 -ml-2 rounded-lg text-cocoa-400 hover:text-cocoa-600 hover:bg-cream-100 transition">
                    <x-icon path="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" class="w-5 h-5" />
                </button>

                {{-- Page title in top bar --}}
                <span class="hidden lg:block text-sm text-cocoa-500">Staff workspace</span>

                {{-- Right side --}}
                <div class="flex items-center gap-3 text-sm min-w-0">
                    <span class="text-cocoa-500 text-xs min-w-0">{{ Auth::user()->full_name }} <span class="text-cocoa-500">&middot;</span> <span class="capitalize">{{ Auth::user()->role }}</span></span>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 text-xs font-medium text-cocoa-500 hover:text-cocoa-700 border border-cocoa-100 rounded-lg hover:bg-cream-100 transition">
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Content --}}
        <main id="main-content" tabindex="-1" class="flex-grow px-4 sm:px-6 lg:px-8 py-6">
            <div class="max-w-7xl mx-auto staff-content-container">
                @if (session('success'))
                    <div role="status" class="mb-5 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
                        <x-icon name="check" class="text-emerald-700" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div role="alert" class="mb-5 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm flex items-center gap-2">
                        <x-icon path="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" class="w-4 h-4 flex-shrink-0 text-red-600" />
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div data-error-summary role="alert" tabindex="-1" class="mb-5 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                        <div class="font-semibold mb-1">Please correct the following errors:</div>
                        <ul class="list-disc list-inside space-y-0.5 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>

        {{-- Footer --}}
        <footer class="border-t border-cocoa-100 px-4 sm:px-6 lg:px-8 py-3 mt-auto">
            <div class="text-xs text-cocoa-500 flex items-center justify-between">
                <span>Aling Chona Cakes & Cupcakes — Staff System</span>
                
            </div>
        </footer>
    </div>

    @stack('drawers')

    @include('partials.validation-data')
</body>
</html>
