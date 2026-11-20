<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Active Barn</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-surface p-4 font-sans text-ink antialiased">
<div class="w-full max-w-md">
    <div class="mb-6 text-center">
        <p class="text-3xl font-extrabold tracking-tight">ACTIVE BARN</p>
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sistem Informasi Absensi Member</p>
    </div>

    <form method="POST" action="{{ url('/login') }}" class="space-y-5 rounded-2xl border border-line bg-white p-8">
        @csrf
        <div>
            <h1 class="text-xl font-extrabold">Masuk</h1>
            <p class="mt-1 text-sm text-slate-500">Gunakan akun admin atau resepsionis.</p>
        </div>

        <div>
            <label for="email" class="text-sm font-semibold">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1.5 w-full rounded-xl border border-line px-4 py-3 text-sm outline-none focus:border-ink">
            @error('email')
                <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="text-sm font-semibold">Kata sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1.5 w-full rounded-xl border border-line px-4 py-3 text-sm outline-none focus:border-ink">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-line"> Ingat saya
        </label>

        <button type="submit" class="w-full rounded-xl bg-ink px-4 py-3 text-sm font-bold text-white hover:bg-black">Masuk</button>
    </form>
</div>
</body>
</html>
