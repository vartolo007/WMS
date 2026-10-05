<x-guest-layout>
    <div class="text-center space-y-4">
        <h1 class="text-2xl font-bold text-primary">منصة إدارة المستودعات</h1>
        <p class="text-gray-600">المشتريات والمبيعات والمرتجعات والمخزون لشركتك ومستودعاتها في مكان واحد.</p>

        <div class="flex items-center justify-center gap-3 pt-2">
            @auth
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-md bg-primary text-white hover:bg-primary-dark">لوحة التحكم</a>
            @else
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-md bg-primary text-white hover:bg-primary-dark">تسجيل الدخول</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-md border border-primary text-primary hover:bg-primary-light">تسجيل شركة جديدة</a>
                @endif
            @endauth
        </div>
    </div>
</x-guest-layout>
