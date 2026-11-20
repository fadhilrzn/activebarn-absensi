<x-layouts.app title="Kelola Pengguna">
    <div class="mx-auto max-w-4xl space-y-6">
        <a href="{{ route('settings.index') }}" class="text-sm font-semibold text-slate-500 hover:text-ink">&larr; Kembali ke Pengaturan</a>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-line bg-white p-6">
            <h2 class="text-lg font-extrabold">Akun pengguna</h2>
            <p class="text-sm text-slate-500">Admin mengelola semua menu. Resepsionis hanya Dashboard dan Scan Check-In. Akun nonaktif tidak bisa masuk dan sesinya dihentikan.</p>

            <ul class="mt-5 divide-y divide-line">
                @foreach ($users as $u)
                    @php $self = $u->is(auth()->user()); @endphp
                    <li class="py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold">{{ $u->name }} @if ($self) <span class="text-xs font-normal text-slate-500">(Anda)</span> @endif</p>
                                <p class="truncate text-xs text-slate-500">{{ $u->email }}</p>
                            </div>

                            @if ($self)
                                <div class="flex items-center gap-2">
                                    <x-badge color="success">{{ $u->role?->label() }}</x-badge>
                                    <x-badge color="success">Aktif</x-badge>
                                </div>
                            @else
                                <form method="POST" action="{{ route('users.update', $u) }}" class="flex flex-wrap items-center gap-3">
                                    @csrf
                                    @method('PUT')
                                    <label class="sr-only" for="role-{{ $u->id }}">Peran {{ $u->name }}</label>
                                    <select id="role-{{ $u->id }}" name="role" class="rounded-xl border border-line px-3 py-2 text-sm">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($u->role === $role)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="is_active" value="1" @checked($u->is_active) class="rounded border-line"> Aktif
                                    </label>
                                    <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-semibold text-white hover:bg-black">Simpan</button>
                                </form>
                            @endif
                        </div>

                        @unless ($self)
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-ink">Ganti kata sandi {{ $u->name }}</summary>
                                <form method="POST" action="{{ route('users.password', $u) }}" class="mt-2 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <label class="sr-only" for="pw-{{ $u->id }}">Kata sandi baru</label>
                                    <input id="pw-{{ $u->id }}" name="password" type="text" minlength="8" required autocomplete="off" placeholder="Kata sandi baru (min. 8 karakter)"
                                           class="w-72 rounded-xl border border-line px-3 py-2 text-sm">
                                    <button type="submit" class="rounded-xl border border-line px-4 py-2 text-sm font-semibold hover:bg-slate-50"
                                            onclick="return confirm('Ganti kata sandi akun ini? Sesinya akan dihentikan.')">Ganti</button>
                                </form>
                            </details>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </section>

        <form method="POST" action="{{ route('users.store') }}" class="rounded-2xl border border-line bg-white p-6">
            @csrf
            <h2 class="text-lg font-extrabold">Tambah akun</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-input label="Nama" name="name" required />
                <x-input label="Email" name="email" type="email" required autocomplete="off" />
                <div>
                    <label for="role" class="text-sm font-semibold">Peran <span class="text-red-500">*</span></label>
                    <select id="role" name="role" class="mt-1.5 w-full rounded-xl border border-line px-4 py-2.5 text-sm">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-input label="Kata sandi awal" name="password" type="text" required autocomplete="off" hint="Minimal 8 karakter. Minta pemiliknya menggantinya di menu Akun saya." />
            </div>
            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl bg-ink px-6 py-2.5 text-sm font-semibold text-white hover:bg-black">Buat akun</button>
            </div>
        </form>
    </div>
</x-layouts.app>
