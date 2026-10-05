<x-app-layout title="الملف الشخصي">
    <div class="flex max-w-2xl flex-col gap-6">
        <x-ui.page-header title="الملف الشخصي" subtitle="اسمك وبريدك وكلمة المرور" />

        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="mb-5 text-xl font-semibold">معلومات الحساب</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="flex flex-col gap-5">
                @csrf
                @method('PATCH')

                <x-ui.input name="name" label="الاسم" :value="$user->name" required autocomplete="name" />
                <x-ui.input name="email" type="email" label="البريد الإلكتروني" :value="$user->email" ltr required autocomplete="username" />

                <div><x-ui.button>حفظ</x-ui.button></div>
            </form>
        </section>

        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="mb-1 text-xl font-semibold">تغيير كلمة المرور</h2>
            <p class="mb-5 text-sm text-muted">تغيير كلمة المرور بيحتاج كلمة المرور الحالية.</p>

            <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
                @csrf
                @method('PUT')

                <x-ui.input name="current_password" type="password" label="كلمة المرور الحالية" bag="updatePassword" autocomplete="current-password" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="password" type="password" label="كلمة المرور الجديدة" bag="updatePassword" autocomplete="new-password" />
                    <x-ui.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" bag="updatePassword" autocomplete="new-password" />
                </div>

                <div><x-ui.button>تغيير كلمة المرور</x-ui.button></div>
            </form>
        </section>
    </div>
</x-app-layout>
