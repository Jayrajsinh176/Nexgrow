<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Nexgrow') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="text-lg font-semibold text-slate-900">Nexgrow</a>
            <div class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-slate-600 hover:text-slate-900">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-600 hover:text-slate-900">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-slate-900 px-4 py-2 text-white hover:bg-slate-700">Sign up</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-6 py-10">
        @yield('content')
    </main>
</body>
</html>
