<x-layouts.app title="Tambah Booking">
    <form method="POST" action="{{ route('bookings.store') }}" class="mx-auto max-w-4xl space-y-6">
        @csrf

        @error('form')
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <section class="rounded-2xl border border-line bg-white p-6">
            <h2 class="text-lg font-extrabold">Member</h2>
            <p class="text-sm text-slate-500">Member dikenali dari nomor WhatsApp. Nomor yang sama tidak akan dibuat dua kali.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-input label="Nama member" name="name" required />
                <x-input label="No. WhatsApp" name="phone" required placeholder="0812-3456-7890" />
            </div>
        </section>

        <section class="rounded-2xl border border-line bg-white p-6">
            <h2 class="text-lg font-extrabold">Sesi kelas</h2>
            <p class="text-sm text-slate-500">Jika nomor sesi sudah ada, detail sesi harus sama persis dengan yang tersimpan.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <x-input label="Nomor sesi" name="session_number" type="number" min="1" required />
                <x-input label="Tanggal" name="date" type="date" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Mulai" name="start" type="time" required />
                    <x-input label="Selesai" name="end" type="time" required />
                </div>
                <x-input label="Kelas" name="class" required list="class-list" />
                <x-input label="Trainer" name="trainer" required list="trainer-list" />
                <x-input label="Ruangan" name="room" required list="room-list" />
            </div>
        </section>

        <section class="rounded-2xl border border-line bg-white p-6">
            <h2 class="text-lg font-extrabold">Pembayaran</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="source" class="text-sm font-semibold">Sumber booking</label>
                    <select id="source" name="source" class="mt-1.5 w-full rounded-xl border border-line px-4 py-2.5 text-sm">
                        <option value="WhatsApp" @selected(old('source') === 'WhatsApp')>WhatsApp</option>
                        <option value="Aplikasi" @selected(old('source') === 'Aplikasi')>Aplikasi</option>
                    </select>
                </div>
                <x-input label="Paket" name="package" placeholder="Contoh: Paket 5x" />
                <x-input label="Harga (Rp)" name="price" type="number" min="0" placeholder="150000" />
                <x-input label="Metode pembayaran" name="payment" placeholder="Contoh: Transfer" />
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('bookings.index') }}" class="rounded-xl border border-line bg-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">Batal</a>
            <button type="submit" class="rounded-xl bg-ink px-6 py-2.5 text-sm font-semibold text-white hover:bg-black">Simpan booking</button>
        </div>

        <datalist id="class-list">@foreach ($classNames as $n)<option value="{{ $n }}">@endforeach</datalist>
        <datalist id="trainer-list">@foreach ($trainerNames as $n)<option value="{{ $n }}">@endforeach</datalist>
        <datalist id="room-list">@foreach ($roomNames as $n)<option value="{{ $n }}">@endforeach</datalist>
    </form>
</x-layouts.app>
