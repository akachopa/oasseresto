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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="flex min-h-screen">
        <div class="hidden w-1/2 flex-col justify-between bg-gradient-to-br from-brand-500 to-brand-700 p-10 text-white lg:flex">
            <x-brand class="text-white" />

            <div class="max-w-md">
                <h1 class="text-3xl leading-tight font-bold">
                    Satu sistem untuk mengendalikan barang, uang, piutang, hutang, dan laba.
                </h1>
                <p class="mt-4 text-sm text-white/80">
                    {{ config('oasse.brand.tagline') }}
                </p>
            </div>

            <p class="text-xs text-white/60">&copy; {{ date('Y') }} OASSE</p>
        </div>

        <div class="flex w-full items-center justify-center p-6 lg:w-1/2">
            <div class="w-full max-w-sm">
                <div class="mb-8 lg:hidden">
                    <x-brand size="lg" />
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
