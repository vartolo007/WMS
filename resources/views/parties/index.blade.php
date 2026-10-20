{{-- Screen 12 (design batch 5): customers and suppliers. The "حركته" link comes with Phase 5 reports. --}}
@php
    $tabs = [['label' => 'الكل', 'type' => null]];
    foreach (\App\Enums\PartyType::cases() as $case) {
        $tabs[] = ['label' => $case->plural(), 'type' => $case];
    }
@endphp

<x-app-layout title="العملاء والموردون">
    <div
        x-data="recordForm({
            open: {{ Js::from($errors->any()) }},
            editingId: {{ Js::from(old('editing_id')) }},
            editingName: {{ Js::from(old('name')) }},
            storeUrl: {{ Js::from(route('parties.store')) }},
            updateUrl: {{ Js::from(route('parties.update', '__ID__')) }},
            fields: ['type', 'name', 'phone'],
            defaults: { type: 'customer' },
        })"
        class="flex flex-col gap-6"
    >
        <x-ui.page-header title="العملاء والموردون" subtitle="مشتركين بين كل مستودعات الشركة.">
            <x-ui.button variant="secondary" :href="route('imports.show', 'parties')">استيراد من CSV</x-ui.button>
            <x-ui.button type="button" @click="create()">+ إضافة</x-ui.button>
        </x-ui.page-header>

        <div class="flex flex-wrap items-center gap-3">
            <nav class="flex gap-1 rounded-[10px] bg-line p-1" aria-label="نوع الطرف">
                @foreach ($tabs as $tab)
                    @php($active = $tab['type'] === $type)
                    <a href="{{ route('parties.index', array_filter(['type' => $tab['type']?->value, 'q' => $search])) }}"
                       @class([
                           'flex h-9 items-center whitespace-nowrap rounded-[7px] px-4 text-sm',
                           'bg-white font-semibold text-ink shadow-sm' => $active,
                           'text-muted hover:text-ink' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>{{ $tab['label'] }}</a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('parties.index') }}" class="flex w-full min-w-0 items-center gap-3 sm:w-auto sm:flex-1 sm:max-w-md">
                @if ($type)
                    <input type="hidden" name="type" value="{{ $type->value }}">
                @endif
                <div class="min-w-0 flex-1">
                    <x-ui.input name="q" type="search" :value="$search" placeholder="بحث بالاسم أو الهاتف" autocomplete="off" />
                </div>
                <x-ui.button variant="secondary">بحث</x-ui.button>
            </form>
        </div>

        @if ($parties->isEmpty())
            @if ($search !== '' || $type)
                <x-ui.empty-state title="ما في نتائج" description="جرّب تبويب تاني أو بحث مختلف، أو أضف طرف جديد.">
                    <x-ui.button type="button" @click="create()">+ إضافة</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state title="ما في عملاء أو موردين بعد" description="أضف عملاءك وموردينك مرة وحدة، وبتستخدمهم كل المستودعات بالفواتير.">
                    <x-ui.button type="button" @click="create()">+ إضافة</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            {{-- Desktop: table --}}
            <x-ui.table class="hidden md:block">
                <thead>
                    <tr>
                        <x-ui.th>الاسم</x-ui.th>
                        <x-ui.th>النوع</x-ui.th>
                        <x-ui.th>الهاتف</x-ui.th>
                        <x-ui.th>الحالة</x-ui.th>
                        <x-ui.th></x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($parties as $party)
                        <tr @class(['text-subtle' => ! $party->is_active])>
                            <x-ui.td class="font-medium">{{ $party->name }}</x-ui.td>
                            <x-ui.td>{{ $party->type->label() }}</x-ui.td>
                            <x-ui.td>
                                @if ($party->phone)
                                    <span dir="ltr" class="tabular-nums">{{ $party->phone }}</span>
                                @else
                                    —
                                @endif
                            </x-ui.td>
                            <x-ui.td>
                                <x-ui.badge :tone="$party->is_active ? 'success' : 'danger'">{{ $party->is_active ? 'فعّال' : 'موقوف' }}</x-ui.badge>
                            </x-ui.td>
                            <x-ui.td class="whitespace-nowrap text-left">
                                @include('parties.partials.actions')
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            {{-- Mobile: one card per party, phone as a call link --}}
            <ul class="flex flex-col gap-3 md:hidden">
                @foreach ($parties as $party)
                    <li @class(['flex flex-col gap-2 rounded-[10px] border border-line bg-white p-4', 'text-subtle' => ! $party->is_active])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-semibold">{{ $party->name }}</span>
                                <span class="text-sm text-muted">{{ $party->type->label() }}</span>
                            </div>
                            <x-ui.badge :tone="$party->is_active ? 'success' : 'danger'">{{ $party->is_active ? 'فعّال' : 'موقوف' }}</x-ui.badge>
                        </div>
                        @if ($party->phone)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $party->phone) }}" dir="ltr" class="self-start text-sm font-semibold text-primary tabular-nums">{{ $party->phone }}</a>
                        @endif
                        <div class="-mx-2">
                            @include('parties.partials.actions')
                        </div>
                    </li>
                @endforeach
            </ul>

            {{ $parties->links() }}
        @endif

        <x-ui.modal close="close()" title="editingId ? 'تعديل «' + editingName + '»' : 'إضافة طرف'">
            <form method="POST" :action="action" x-ref="form" class="flex flex-col gap-4" action="{{ route('parties.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="! editingId">
                <input type="hidden" name="editing_id" :value="editingId ?? ''">

                @include('parties.partials.form')

                <div class="mt-1 flex gap-2">
                    <x-ui.button>حفظ</x-ui.button>
                    <x-ui.button variant="secondary" type="button" @click="close()">إلغاء</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
</x-app-layout>
