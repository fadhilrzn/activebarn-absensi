<x-layouts.app title="Akun Saya">
    <div class="mx-auto max-w-2xl space-y-6">
        <form method="POST" action="{{ route('account.update') }}" class="rounded-2xl border border-line bg-white p-6">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-extrabold">Profil</h2>
            <p class="text-sm text-slate-500">Peran: {{ $user->role?->label() }}. Peran hanya bisa diubah oleh admin.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-input label="Nama" name="name" :value="$user->name" required />
                <x-input label="Email" name="email" type="email" :value="$user->email" required />
            </div>
            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-ink px-6 py-2.5 text-sm font-semibold text-white hover:bg-black">Simpan profil</button>
            </div>
        </form>

        <form method="POST" action="{{ route('account.password') }}" class="rounded-2xl border border-line bg-white p-6">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-extrabold">Ganti kata sandi</h2>
            <div class="mt-5 grid gap-4">
                <x-input label="Kata sandi saat ini" name="current_password" type="password" required autocomplete="current-password" />
                <x-input label="Kata sandi baru" name="password" type="password" required autocomplete="new-password" hint="Minimal 8 karakter dan berbeda dari kata sandi saat ini." />
                <x-input label="Ulangi kata sandi baru" name="password_confirmation" type="password" required autocomplete="new-password" />
            </div>
            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-ink px-6 py-2.5 text-sm font-semibold text-white hover:bg-black">Ganti kata sandi</button>
            </div>
        </form>
    </div>
</x-layouts.app>
