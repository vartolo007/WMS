<x-app-layout title="المستودعات">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="المستودعات" subtitle="لكل مستودع حساب دخول خاص. المستودع الموقوف ما بيقدر يدخل، وبتضل حركاته ظاهرة إلك.">
            <x-ui.button :href="route('warehouses.create')">+ مستودع جديد</x-ui.button>
        </x-ui.page-header>

        @if ($warehouses->isEmpty())
            <x-ui.empty-state title="ما في مستودعات بعد" description="أنشئ حساب لكل مستودع، مثلاً مستودع المدينة ومستودع الريف.">
                <x-ui.button :href="route('warehouses.create')">+ مستودع جديد</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <x-ui.th>المستودع</x-ui.th>
                        <x-ui.th>البريد الإلكتروني</x-ui.th>
                        <x-ui.th>الحالة</x-ui.th>
                        <x-ui.th></x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($warehouses as $warehouse)
                        <tr @class(['text-subtle' => ! $warehouse->is_active])>
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
</x-app-layout>
