<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bingo') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <nav class="bg-slate-900 text-white shadow-lg">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <a href="{{ route('games.index') }}" class="flex items-center gap-2 text-xl font-bold tracking-tight">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-400 font-black text-slate-900">B</span>
                Bingo
            </a>
            <div class="flex items-center gap-4 text-sm font-medium">
                <a href="{{ route('games.index') }}" class="hover:text-amber-300">Partidas</a>
                <a href="{{ route('games.create') }}" class="rounded-lg bg-amber-400 px-3 py-1.5 font-semibold text-slate-900 hover:bg-amber-300">Nova partida</a>
            </div>
        </div>
    </nav>

    @if (session('success'))
        <div class="mx-auto mt-4 max-w-7xl px-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="mx-auto mt-4 max-w-7xl px-4">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">{{ session('error') }}</div>
        </div>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-6">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
