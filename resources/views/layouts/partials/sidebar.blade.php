{{-- Sidebar items come from App\Support\Navigation (one place per role). --}}
@php
    $mobile = $mobile ?? false;
    $link = fn (bool $active) => implode(' ', [
        'flex items-center rounded-lg px-3 text-[15px]',
        $mobile ? 'h-11' : 'h-10',
        $active ? 'bg-primary-light font-semibold text-primary' : 'font-medium text-ink hover:bg-canvas',
    ]);
@endphp

<nav class="flex w-full flex-col gap-0.5">
    @foreach ($navigation['main'] as $item)
        <a href="{{ route($item['route']) }}" class="{{ $link(request()->routeIs($item['active'])) }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif>{{ $item['label'] }}</a>
    @endforeach

    @if ($navigation['company'])
        @if ($mobile)
            <span class="mx-3 my-1.5 h-px bg-line"></span>
        @else
            <div class="px-3 pb-1.5 pt-5 text-xs font-semibold text-subtle">إدارة الشركة</div>
        @endif
        @foreach ($navigation['company'] as $item)
            <a href="{{ route($item['route']) }}" class="{{ $link(request()->routeIs($item['active'])) }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    @endif

    @unless ($mobile)
        <div class="flex-1"></div>
    @endunless
    @if ($mobile)
        <span class="mx-3 my-1.5 h-px bg-line"></span>
    @endif

    <a href="{{ route('profile.edit') }}" class="{{ $link(request()->routeIs('profile.*')) }}">الملف الشخصي</a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="{{ $link(false) }} w-full !text-muted">تسجيل الخروج</button>
    </form>
</nav>
