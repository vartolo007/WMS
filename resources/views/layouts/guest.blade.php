{{--
    Guest pages from design batch 1 (login, register, password reset): form on one side, panel on the other.
    Platform identity (Q7: config app.name) and the default colour — the company is unknown before login.
    Slots: `headerAction` (top end of the form side), `aside` (replaces the default colour panel).
--}}
@php($platform = config('app.name'))
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ $platform }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('layouts.partials.theme')
    </head>
    <body class="bg-white font-sans text-ink antialiased">
        <div class="flex min-h-screen">
            <div class="flex flex-1 flex-col px-5 py-6 sm:px-14 sm:py-10">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('login') }}" class="flex items-center gap-2.5">
                        <span class="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] bg-ink text-[15px] font-bold text-white sm:h-9 sm:w-9 sm:rounded-lg sm:text-lg">{{ mb_substr($platform, 0, 1) }}</span>
                        <span class="text-[17px] font-bold sm:text-xl">{{ $platform }}</span>
                    </a>
                    @isset($headerAction)
                        <span class="text-[15px] text-muted">{{ $headerAction }}</span>
                    @endisset
                </div>

                <div class="flex flex-1 items-center justify-center py-10">
                    <div class="w-full {{ $width ?? 'max-w-[400px]' }}">
                        {{ $slot }}
                    </div>
                </div>

                <div class="text-[13px] text-subtle">© {{ now()->year }} {{ $platform }}</div>
            </div>

            @isset($aside)
                {{ $aside }}
            @else
                <div class="hidden w-[620px] flex-none flex-col justify-end gap-4 bg-primary p-14 text-white lg:flex">
                    <div class="text-[40px] font-bold leading-snug">كل فاتورة، كل نقل، كل مرتجع — بمكان واحد.</div>
                    <div class="max-w-[440px] text-[17px] leading-8 text-white/80">منصة لإدارة مستودعات شركتك: شراء وبيع نقدي، تحويل بين المستودعات، وتقارير حركة وجرد جاهزة للطباعة.</div>
                </div>
            @endisset
        </div>
    </body>
</html>
