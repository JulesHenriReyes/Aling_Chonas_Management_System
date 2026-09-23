<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Staff Dashboard') - Aling Chona Cakes & Cupcakes</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-stone-100 text-stone-800 font-sans min-h-screen flex flex-col">
    <!-- Top Navigation Bar -->
    <nav class="bg-rose-950 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Left: Brand -->
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <span class="text-2xl">🎂</span>
                        <div>
                            <span class="font-bold text-amber-200 tracking-tight text-base block">Aling Chona</span>
                            <span class="text-[10px] text-rose-300 uppercase tracking-widest block -mt-1">Staff Portal</span>
                        </div>
                    </a>

                    <!-- Navigation Links -->
                    <div class="hidden lg:flex items-center gap-1 text-xs font-semibold">
                        <a href="{{ route('dashboard') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('dashboard') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('orders.index') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('orders.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Orders
                        </a>
                        <a href="{{ route('schedule.index') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('schedule.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Pickup Schedule
                        </a>
                        <a href="{{ route('customers.index') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('customers.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Customers
                        </a>
                        <a href="{{ route('products.index') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('products.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Products
                        </a>
                        <a href="{{ route('supplies.index') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('supplies.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Inventory
                        </a>
                        <a href="{{ route('expenses.index') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('expenses.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Expenses
                        </a>
                        <a href="{{ route('reports.index') }}" 
                           class="px-3 py-2 rounded-lg transition {{ request()->routeIs('reports.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-100 hover:bg-rose-900/60' }}">
                            Reports
                        </a>
                        @can('manage-users')
                            <a href="{{ route('users.index') }}" 
                               class="px-3 py-2 rounded-lg transition {{ request()->routeIs('users.*') ? 'bg-amber-600 text-white' : 'text-amber-300 hover:bg-rose-900/60' }}">
                                Users (Owner)
                            </a>
                        @endcan
                    </div>
                </div>

                <!-- Right: User info & Logout -->
                <div class="flex items-center gap-4 text-xs">
                    <div class="text-right hidden sm:block">
                        <div class="font-bold text-amber-100">{{ Auth::user()->full_name }}</div>
                        <div class="text-[10px] text-rose-300 uppercase tracking-wider">{{ ucfirst(Auth::user()->role) }}</div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 bg-rose-900 hover:bg-rose-800 text-rose-200 hover:text-white rounded-lg transition font-medium border border-rose-800">
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>

            <!-- Mobile navigation menu -->
            <div class="lg:hidden flex flex-wrap gap-2 py-2 border-t border-rose-900 text-xs font-semibold">
                <a href="{{ route('dashboard') }}" class="px-2 py-1 rounded {{ request()->routeIs('dashboard') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Dashboard</a>
                <a href="{{ route('orders.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('orders.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Orders</a>
                <a href="{{ route('schedule.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('schedule.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Schedule</a>
                <a href="{{ route('customers.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('customers.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Customers</a>
                <a href="{{ route('products.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('products.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Products</a>
                <a href="{{ route('supplies.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('supplies.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Inventory</a>
                <a href="{{ route('expenses.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('expenses.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Expenses</a>
                <a href="{{ route('reports.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('reports.*') ? 'bg-rose-900 text-amber-200' : 'text-rose-200' }}">Reports</a>
                @can('manage-users')
                    <a href="{{ route('users.index') }}" class="px-2 py-1 rounded {{ request()->routeIs('users.*') ? 'bg-amber-600 text-white' : 'text-amber-300' }}">Users</a>
                @endcan
            </div>
        </div>
    </nav>

    <!-- Content Area -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="text-emerald-600 text-lg">✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="text-red-600 text-lg">⚠️</span>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm shadow-sm">
                <div class="font-bold mb-1">Please correct the following errors:</div>
                <ul class="list-disc list-inside space-y-1 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-stone-900 text-stone-400 text-xs py-4 border-t border-stone-800 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <div>Aling Chona Cakes and Cupcakes — Internal Business System</div>
            <div>Signed in as: <strong class="text-stone-300">{{ Auth::user()->email }}</strong> ({{ Auth::user()->role }})</div>
        </div>
    </footer>
</body>
</html>
