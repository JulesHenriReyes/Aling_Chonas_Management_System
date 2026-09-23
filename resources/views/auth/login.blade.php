<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Aling Chona Cakes & Cupcakes</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-stone-100 text-stone-800 min-h-screen flex items-center justify-center p-4 font-sans">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-stone-200 overflow-hidden">
        <div class="bg-rose-900 text-white p-6 text-center">
            <span class="text-4xl block mb-2">🎂</span>
            <h1 class="text-xl font-bold tracking-tight text-amber-100">Aling Chona Cakes & Cupcakes</h1>
            <p class="text-xs text-rose-200 uppercase tracking-widest mt-1">Internal Staff Management System</p>
        </div>

        <div class="p-6 sm:p-8">
            <h2 class="text-lg font-bold text-stone-900 mb-1">Staff Sign In</h2>
            <p class="text-xs text-stone-500 mb-6">Restricted access for Owner and Assistant accounts only.</p>

            @if ($errors->any())
                <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-stone-700 mb-1">Password</label>
                    <input type="password" name="password" required
                           class="w-full text-sm rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded text-rose-900 focus:ring-rose-800">
                        <span class="text-stone-600">Remember me</span>
                    </label>
                    <a href="{{ route('public.order.index') }}" class="text-rose-800 hover:underline">
                        ← Back to Public Site
                    </a>
                </div>

                <button type="submit" 
                        class="w-full mt-4 py-2.5 bg-rose-900 hover:bg-rose-800 text-white font-bold text-sm rounded-xl transition shadow">
                    Sign In to Staff System
                </button>
            </form>
        </div>
    </div>
</body>
</html>
