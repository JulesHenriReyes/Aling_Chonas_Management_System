<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Aling Chona Cakes & Cupcakes</title>
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
    @include('partials.ui-assets')
</head>
<body class="bg-cream-50 text-cocoa-500 min-h-screen flex items-center justify-center p-4 font-sans">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main id="main-content" tabindex="-1" class="max-w-sm w-full">
        {{-- Brand --}}
        <div class="text-center mb-8">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-cocoa-600 items-center justify-center text-white text-2xl font-bold mb-4">A</span>
            <h1 class="text-xl font-bold text-cocoa-600 tracking-tight">Aling Chona</h1>
            <p class="text-xs text-cocoa-400  mt-0.5">Cakes & Cupcakes</p>
        </div>

        {{-- Login Card --}}
        <div class="bg-white rounded-xl border border-cocoa-100 p-6 sm:p-8">
            <h2 class="text-base font-bold text-cocoa-600 mb-1">Staff Sign In</h2>
            <p class="text-xs text-cocoa-400 mb-6">Access restricted to authorized staff only.</p>

            @if ($errors->any())
                <div data-error-summary role="alert" tabindex="-1" class="mb-4 px-3 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Email address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                           class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50"
                           placeholder="you@alingchona.local">
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-cocoa-500 mb-1.5">password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50"
                           placeholder="Enter your password">
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded text-cocoa-600 focus:ring-cocoa-300 border-cocoa-200">
                        <span class="text-cocoa-400">Remember me</span>
                    </label>
                    <a href="{{ route('public.order.index') }}" class="text-cocoa-500 hover:text-cocoa-500 transition">
                        <x-icon name="arrow-left" class="mr-1" /> Public Site
                    </a>
                </div>

                <button type="submit"
                        class="w-full mt-2 py-2.5 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm rounded-lg transition focus:outline-none focus:ring-2 focus:ring-cocoa-300 focus:ring-offset-2">
                    Sign In to Staff System
                </button>
            </form>
        </div>
    </main>
    @include('partials.validation-data')
</body>
</html>
