<x-layouts.app :title="$title">
    <div class="rounded-2xl border border-dashed border-line bg-white p-10 text-center">
        <p class="text-lg font-bold">{{ $title }}</p>
        <p class="mt-2 text-sm text-slate-500">Halaman ini akan dibangun pada langkah berikutnya.</p>
        <a href="{{ route('dashboard') }}" class="mt-5 inline-block rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white">Kembali ke Dashboard</a>
    </div>
</x-layouts.app>
