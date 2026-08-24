@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F59E0B">

    <title>{{ $title ? $title.' - '.config('oasse.brand.name') : config('oasse.brand.name') }}</title>

    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <script>
        (() => {
            const stored = localStorage.getItem('oasse.theme');
            const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    @php
        $user = auth()->user();
        $sidebar = \App\Modules\Core\Support\Navigation::sidebar($user);
        $bottom = \App\Modules\Core\Support\Navigation::bottom($user);
    @endphp

    <div x-data="{ mobileNav: false }">
        {{-- Desktop sidebar (PLAN 7) --}}
        <aside class="bg-panel border-hairline fixed inset-y-0 left-0 z-40 hidden w-64 shrink-0 overflow-y-auto border-r lg:block">
            <div class="border-hairline flex h-16 items-center border-b px-4">
                <a href="{{ route('dashboard') }}" wire:navigate><x-brand /></a>
            </div>

            <x-partials.sidebar-menu :groups="$sidebar" />
        </aside>

        {{-- Mobile drawer --}}
        <div x-show="mobileNav" x-cloak class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-black/40" @click="mobileNav = false"></div>
            <aside class="bg-panel border-hairline absolute inset-y-0 left-0 w-72 overflow-y-auto border-r">
                <div class="border-hairline flex h-16 items-center justify-between border-b px-4">
                    <x-brand />
                    <button type="button" class="btn-icon text-muted" @click="mobileNav = false">
                        <x-icon name="x" />
                    </button>
                </div>
                <x-partials.sidebar-menu :groups="$sidebar" />
            </aside>
        </div>

        <div class="flex min-h-screen w-full flex-col lg:pl-64">
            <x-partials.topbar />

            <main class="flex-1 px-4 pt-4 pb-24 sm:px-6 lg:pb-8">
                @if (session('status'))
                    <div class="badge-success mb-4 flex w-full items-center justify-start px-4 py-2.5 text-sm">
                        <x-icon name="check" class="h-4 w-4" />
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="badge-danger mb-4 flex w-full items-center justify-start px-4 py-2.5 text-sm">
                        <x-icon name="warning" class="h-4 w-4" />
                        {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        <x-partials.bottom-nav :items="$bottom" />
    </div>

    @livewireScriptConfig
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('{{ route('pwa.service-worker') }}').catch(() => {});
        }
    </script>
</body>
</html>
