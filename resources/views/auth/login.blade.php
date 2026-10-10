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
                    fontFamily: { sans: ['DM Sans', 'system-ui', 'sans-serif'] },
                    colors: {
                        cream: { 50: '#FDFBF7', 100: '#F5F1EB' },
                        cocoa: { 50: '#F5F1EB', 100: '#E8E0D4', 200: '#D4C4B0', 300: '#C2956B', 400: '#716153', 500: '#5C4A3A', 600: '#3C2415', 700: '#2C1810', 800: '#1A0E08' },
                    }
                }
            }
        }
    </script>
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @include('partials.ui-assets')
</head>
<body class="bg-cream-50 text-cocoa-500 min-h-screen flex items-center justify-center p-4 font-sans">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main id="main-content" tabindex="-1" class="max-w-sm w-full">
        {{-- Brand --}}
        <div class="text-center mb-8 login-wordmark"><a href="{{ route('public.order.index') }}"><x-brand /></a></div>

        {{-- Login Card --}}
        <div class="bg-white rounded-xl border border-cocoa-100 p-6 sm:p-8">
            <h2 class="text-base font-bold text-cocoa-600 mb-1">Staff Sign In</h2>
            <p class="text-xs text-cocoa-500 mb-6">Access restricted to authorized staff only.</p>

            @if ($errors->any())
                <div data-error-summary role="alert" tabindex="-1" class="mb-4 px-3 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                    @if ($errors->count() > 1)
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @else
                        {{ $errors->first() }}
                    @endif
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

                <div x-data="{ showPassword: false }">
                    <label for="password" class="block text-xs font-semibold text-cocoa-500 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" :type="showPassword ? 'text' : 'password'" id="password" name="password" required autocomplete="current-password"
                               class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50 pr-10"
                               placeholder="Enter your password">
                        <button type="button" @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-cocoa-400 hover:text-cocoa-600 transition">
                            <span class="sr-only" x-text="showPassword ? 'Hide password' : 'Show password'"></span>
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded text-cocoa-600 focus:ring-cocoa-300 border-cocoa-200">
                        <span class="text-cocoa-500">Remember me</span>
                    </label>
                    <a href="{{ route('public.order.index') }}" class="text-cocoa-500 hover:text-cocoa-700 transition">
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
