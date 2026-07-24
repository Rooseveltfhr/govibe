<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GOVIBE AI</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; min-height: 100vh; display: flex;
               align-items: center; justify-content: center; background: #0b1020; color: #e8ecf8; }
        main { text-align: center; padding: 2rem; }
        h1 { font-size: 2.25rem; margin-bottom: .5rem; }
        p { color: #9aa7c7; margin-top: 0; }
        nav { margin-top: 1.5rem; }
        nav a { color: #7aa2ff; text-decoration: none; margin: 0 .5rem; font-size: .9rem; }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('Welcome to GOVIBE AI') }}</h1>
        <p>{{ __('The AI platform for Haiti and the Caribbean') }}</p>
        <nav aria-label="{{ __('Language') }}">
            @foreach (config('govibe.locales') as $locale)
                <a href="{{ url('/') }}?lang={{ $locale }}" @if (app()->getLocale() === $locale) style="font-weight:bold" @endif>{{ strtoupper($locale) }}</a>
            @endforeach
        </nav>
    </main>
</body>
</html>
