@props(['title' => 'Active Barn'])
@php
    $user = auth()->user();
    $initials = \Illuminate\Support\Str::of($user->name)->substr(0, 2)->upper();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Active Barn</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface font-sans text-ink antialiased">
<div class="min-h-screen lg:flex">
    <div id="overlay" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-line bg-white p-5 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <div class="px-2">
            <p class="text-2xl font-extrabold tracking-tight">ACTIVE BARN</p>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Absensi Member</p>
        </div>

        <a href="{{ route('scan.index') }}"
           class="mt-6 flex items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 text-sm font-bold text-ink hover:brightness-95">
            <x-icon name="scan" /> Quick Scan Check-In
        </a>

        <nav class="mt-6 space-y-1">
            <x-nav-item :href="route('dashboard')" icon="dashboard" :active="request()->routeIs('dashboard')">Dashboard</x-nav-item>
            @if ($user->isAdmin())
                <x-nav-item :href="route('bookings.index')" icon="booking" :active="request()->routeIs('bookings.*', 'imports.*')">Data Booking</x-nav-item>
                <x-nav-item :href="route('qr.index')" icon="qr" :active="request()->routeIs('qr.*')">QR Check-In &amp; WhatsApp</x-nav-item>
            @endif
            <x-nav-item :href="route('scan.index')" icon="scan" :active="request()->routeIs('scan.*')">Scan Check-In</x-nav-item>
            @if ($user->isAdmin())
                <x-nav-item :href="route('reports.index')" icon="report" :active="request()->routeIs('reports.*')">Laporan Absensi</x-nav-item>
                <x-nav-item :href="route('settings.index')" icon="settings" :active="request()->routeIs('settings.*', 'users.*')">Pengaturan</x-nav-item>
            @endif
        </nav>

        <div class="mt-auto space-y-3 border-t border-line pt-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                    <x-icon name="logout" /> Keluar
                </button>
            </form>
            <a href="{{ route('account.edit') }}" title="Akun saya"
               @class(['flex items-center gap-3 rounded-xl border p-3 hover:bg-slate-50', 'border-ink' => request()->routeIs('account.*'), 'border-line' => ! request()->routeIs('account.*')])>
                <span class="flex size-10 items-center justify-center rounded-full bg-ink text-sm font-bold text-white">{{ $initials }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $user->role?->label() }} · Akun saya</p>
                </div>
            </a>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-white/90 px-4 py-4 backdrop-blur sm:px-8">
            <button id="menu-button" type="button" class="rounded-lg border border-line p-2 lg:hidden" aria-label="Buka menu">
                <x-icon name="menu" />
            </button>
            <h1 class="text-xl font-extrabold">{{ $title }}</h1>
            <p class="ml-auto hidden text-sm font-medium text-slate-500 sm:block">{{ now()->translatedFormat('l, d F Y') }}</p>
        </header>

        <main class="p-4 sm:p-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-brand/40 bg-brand-soft px-4 py-3 text-sm font-semibold text-brand-ink" role="status">
                    {{ session('status') }}
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>

<script>
    (function () {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('overlay');
        function toggle(open) {
            sidebar.classList.toggle('-translate-x-full', !open);
            overlay.classList.toggle('hidden', !open);
        }
        document.getElementById('menu-button').addEventListener('click', function () { toggle(true); });
        overlay.addEventListener('click', function () { toggle(false); });
    })();
</script>
</body>
</html>
