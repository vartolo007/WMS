{{-- Signed-in page without sidebar or warehouse selector: brand on one side, logout on the other (design screen 20). --}}
@php
    $company = auth()->user()->company;
    $brandName = $company?->name ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ $brandName }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('layouts.partials.theme')
    </head>
    <body class="bg-canvas font-sans text-ink antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="flex h-14 flex-none items-center gap-3 border-b border-line bg-white px-4 lg:h-16 lg:px-6">
                @if ($company?->logoUrl())
                    <img src="{{ $company->logoUrl() }}" alt="" class="h-9 w-9 flex-none rounded-lg object-contain">
                @else
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-primary text-lg font-bold text-white">{{ mb_substr($brandName, 0, 1) }}</span>
                @endif
                <span class="truncate text-[17px] font-bold">{{ $brandName }}</span>

                <div class="flex-1"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-[15px] text-muted hover:text-ink">تسجيل الخروج</button>
                </form>
            </header>

            <main class="flex flex-1 items-center justify-center px-4 py-10">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
