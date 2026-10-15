{{-- Screen 13 (design batch 5): the list with the create / edit form beside it (under it on mobile). --}}
<x-app-layout title="المستودعات">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="المستودعات" subtitle="لكل مستودع حساب دخول خاص. المستودع الموقوف ما بيقدر يدخل، وبتضل حركاته ظاهرة إلك." />

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
            <div class="flex min-w-0 flex-col gap-3">
                @if ($warehouses->isEmpty())
                    <x-ui.empty-state title="ما عندك مستودعات لسا" description="أنشئ أول مستودع لتبدأ تسجّل حركات. مثلاً: مستودع المدينة أو مستودع الريف." />
                @else
                    <x-ui.table>
                        <thead>
                            <tr>
                                <x-ui.th>المستودع</x-ui.th>
                                <x-ui.th>بريد الحساب</x-ui.th>
                                <x-ui.th>الحالة</x-ui.th>
                                <x-ui.th></x-ui.th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($warehouses as $warehouse)
                                <tr @class(['text-subtle' => ! $warehouse->is_active, 'bg-primary-light' => $editing?->is($warehouse)])>
                                    <x-ui.td class="font-medium">{{ $warehouse->name }}</x-ui.td>
                                    <x-ui.td><span dir="ltr">{{ $warehouse->email }}</span></x-ui.td>
                                    <x-ui.td>
                                        <x-ui.badge :tone="$warehouse->is_active ? 'success' : 'danger'">{{ $warehouse->is_active ? 'فعّال' : 'موقوف' }}</x-ui.badge>
                                    </x-ui.td>
                                    <x-ui.td class="whitespace-nowrap text-left">
                                        <div class="inline-flex items-center gap-1">
                                            <x-ui.button size="sm" variant="ghost" :href="route('warehouses.edit', $warehouse)">تعديل</x-ui.button>
                                            @if ($warehouse->is_active)
                                                <x-ui.confirm :title="'إيقاف '.$warehouse->name.'؟'" :action="route('warehouses.toggle', $warehouse)" method="PATCH" confirm-label="نعم، أوقفه">
                                                    <x-slot:trigger>
                                                        <x-ui.button size="sm" variant="ghost" type="button" class="!text-danger">إيقاف</x-ui.button>
                                                    </x-slot:trigger>
                                                    حساب المستودع ما رح يقدر يدخل. حركاته وأرصدته بتضل محفوظة وظاهرة إلك، وفيك ترجّع تفعّله بأي وقت.
                                                </x-ui.confirm>
                                            @else
                                                <form method="POST" action="{{ route('warehouses.toggle', $warehouse) }}">
                                                    @csrf @method('PATCH')
                                                    <x-ui.button size="sm" variant="ghost">تفعيل</x-ui.button>
                                                </form>
                                            @endif
                                        </div>
                                    </x-ui.td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
            </div>

            <form
                method="POST"
                action="{{ $editing ? route('warehouses.update', $editing) : route('warehouses.store') }}"
                class="flex flex-col gap-4 rounded-xl border border-line bg-white p-6"
            >
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="flex flex-col gap-1">
                    <b class="text-lg text-ink">{{ $editing ? 'تعديل '.$editing->name : 'مستودع جديد' }}</b>
                    <span class="text-sm leading-7 text-muted">كل مستودع إله حساب دخول خاص بيشوف فيه حركاته بس.</span>
                </div>

                @include('warehouses.partials.form', ['warehouse' => $editing])

                <div class="flex flex-col gap-2">
                    <x-ui.button class="h-11">{{ $editing ? 'حفظ' : 'إنشاء المستودع والحساب' }}</x-ui.button>
                    @if ($editing)
                        <x-ui.button variant="secondary" class="h-11" :href="route('warehouses.index')">إلغاء</x-ui.button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
