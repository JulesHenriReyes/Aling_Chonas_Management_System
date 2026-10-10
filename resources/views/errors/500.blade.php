<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error — Aling Chona Cakes & Cupcakes</title>
    @include('partials.ui-font-preloads')
    <link rel="stylesheet" href="{{ asset('css/bakery-ui.css') }}?v={{ file_exists(public_path('css/bakery-ui.css')) ? filemtime(public_path('css/bakery-ui.css')) : 1 }}">
    <link rel="stylesheet" href="{{ asset('css/bakery-typography.css') }}?v={{ filemtime(public_path('css/bakery-typography.css')) }}">
    <style>
        body {
            background-color: var(--bakery-bg, #FDFBF7);
            color: var(--bakery-text, #3C2415);
            font-family: 'DM Sans', system-ui, sans-serif;
            margin: 0;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
            box-sizing: border-box;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid var(--bakery-line, #E8E0D4);
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            max-width: 28rem;
            width: 100%;
            box-shadow: 0 4px 6px -1px rgba(60, 36, 21, 0.05);
        }
        .error-code {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            color: var(--cocoa-600, #3C2415);
            letter-spacing: -0.025em;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            color: var(--cocoa-800, #1A0E08);
        }
        .error-desc {
            font-size: 0.875rem;
            color: var(--bakery-muted, #716153);
            line-height: 1.5;
            margin-bottom: 2rem;
        }
        .error-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <main class="error-card">
        <a href="{{ url('/') }}"><x-brand /></a>
        <div class="error-code">500</div>
        <h1 class="error-title">Unexpected Error</h1>
        <p class="error-desc">Something went wrong while processing your request. Please try again shortly or contact the bakery.</p>
        <div class="error-actions">
            <button type="button" onclick="window.location.reload()" class="ui-button primary">Try Again</button>
            <a href="{{ url('/') }}" class="ui-button quiet">Return Home</a>
        </div>
    </main>
</body>
</html>
