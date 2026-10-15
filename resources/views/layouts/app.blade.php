{{-- App shell from design batch 1: top bar + right sidebar by role, dropdown menu below 1024px. --}}
@php
    $user = auth()->user();
    $company = $user->company;
    $isAdmin = $user->isSuperAdmin();
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
        <div x-data="{ menu: false }" @keydown.escape.window="menu = false" class="flex min-h-screen flex-col">

            {{-- Top bar --}}
            <header @class([
                'sticky top-0 z-30 flex h-14 flex-none items-center gap-3 border-b px-3 lg:h-16 lg:gap-6 lg:px-6',
                'border-ink bg-ink text-white' => $isAdmin,
                'border-line bg-white' => ! $isAdmin,
            ])>
                <button type="button" @click="menu = ! menu" class="flex h-11 w-11 flex-none flex-col items-center justify-center gap-1 rounded-lg lg:hidden {{ $isAdmin ? 'bg-white/10' : 'bg-canvas' }}" aria-label="القائمة">
                    <span class="h-0.5 w-[18px] {{ $isAdmin ? 'bg-white' : 'bg-ink' }}"></span>
                    <span class="h-0.5 w-[18px] {{ $isAdmin ? 'bg-white' : 'bg-ink' }}"></span>
                    <span class="h-0.5 w-[18px] {{ $isAdmin ? 'bg-white' : 'bg-ink' }}"></span>
                </button>

                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 lg:w-[200px]">
                    @if ($company?->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="" class="h-8 w-8 flex-none rounded-lg object-contain lg:h-9 lg:w-9">
                    @else
                        <span @class([
                            'flex h-8 w-8 flex-none items-center justify-center rounded-lg text-base font-bold lg:h-9 lg:w-9 lg:text-lg',
                            'bg-white text-ink' => $isAdmin,
                            'bg-primary text-white' => ! $isAdmin,
                        ])>{{ mb_substr($brandName, 0, 1) }}</span>
                    @endif
                    <span class="truncate text-[15px] font-bold lg:text-[17px]">{{ $brandName }}</span>
                </a>

                @if ($isAdmin)
                    <span class="hidden border-s border-white/20 ps-3 text-[13px] text-white/60 sm:inline">لوحة إدارة المنصة</span>
                @elseif ($user->isWarehouse())
                    <span class="hidden border-s border-line ps-3 text-sm text-muted sm:inline">{{ $user->name }}</span>
                @elseif ($user->isOwner())
                    <div class="hidden lg:block">
                        @include('layouts.partials.warehouse-selector')
                    </div>
                @endif

                <div class="flex-1"></div>

                @if ($user->isOwner() && $company->status === \App\Enums\CompanyStatus::Trial)
                    <x-ui.badge tone="warning" class="hidden lg:inline-flex">
                        {{ $company->isTrialExpired() ? 'انتهت التجربة' : 'تجريبي · باقي '.$company->trialDaysLeft().' يوم' }}
                    </x-ui.badge>
                @endif

                <div class="flex items-center gap-2.5">
                    <div class="hidden flex-col items-end leading-tight sm:flex">
                        <span class="text-sm font-semibold">{{ $user->name }}</span>
                        <span class="text-xs {{ $isAdmin ? 'text-white/60' : 'text-muted' }}">{{ $user->role->label() }}</span>
                    </div>
                    <span @class([
                        'flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold',
                        'bg-white/15 text-white' => $isAdmin,
                        'bg-line text-muted' => ! $isAdmin,
                    ])>{{ mb_substr($user->name, 0, 1) }}</span>
                </div>
            </header>

            {{-- Mobile: warehouse selector on its own row --}}
            @if ($user->isOwner())
                <div class="border-b border-line bg-white px-3 py-2 lg:hidden">
                    @include('layouts.partials.warehouse-selector', ['fullWidth' => true])
                </div>
            @endif

            <div class="relative flex min-h-0 flex-1">
                {{-- Desktop sidebar (right side in RTL) --}}
                <aside class="hidden w-[248px] flex-none border-e border-line bg-white px-3 py-4 lg:flex">
                    @include('layouts.partials.sidebar')
                </aside>

                {{-- Mobile dropdown menu --}}
                <div x-show="menu" x-cloak class="fixed inset-x-0 bottom-0 top-14 z-20 lg:hidden">
                    <div class="absolute inset-0 bg-ink/35" @click="menu = false"></div>
                    <div x-show="menu" x-transition class="relative max-h-full overflow-y-auto bg-white p-2 shadow-xl">
                        @include('layouts.partials.sidebar', ['mobile' => true])
                    </div>
                </div>

                <main class="min-w-0 flex-1 px-4 py-6 lg:px-10 lg:py-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-ui.toast />
    </body>
</html>
