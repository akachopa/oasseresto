<x-layouts.guest title="Masuk">
    <div class="mb-7">
        <h2 class="text-2xl font-semibold">Masuk ke OASSE</h2>
        <p class="mt-1 text-sm text-muted">Kelola barang, uang, dan laba dari satu dashboard.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="label" for="email">Email atau Username</label>
            <input id="email" name="email" type="text" class="input" value="{{ old('email') }}"
                   required autofocus autocomplete="username">
            @error('email')
                <p class="mt-1.5 text-xs text-negative">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password">Password</label>
            <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
            @error('password')
                <p class="mt-1.5 text-xs text-negative">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="remember" value="1" class="rounded border-ink-300 text-brand-500 focus:ring-brand-500">
            Ingat saya di perangkat ini
        </label>

        <button type="submit" class="btn-primary w-full py-2.5">Masuk</button>
    </form>

    @if (app()->environment('local'))
        <div class="border-hairline text-muted mt-8 rounded-lg border p-3 text-xs">
            <p class="mb-1.5 font-semibold">Akun demo (password: password)</p>
            <ul class="grid grid-cols-2 gap-x-3 gap-y-0.5">
                <li>owner@oasse.id</li>
                <li>purchasing@oasse.id</li>
                <li>gudang@oasse.id</li>
                <li>sales@oasse.id</li>
                <li>finance@oasse.id</li>
                <li>accounting@oasse.id</li>
                <li>kasir@oasse.id</li>
            </ul>
        </div>
    @endif
</x-layouts.guest>
