{{-- Screen 11 (design batch 5): product catalogue with balance, suggested price and the "low only" filter. --}}
<x-app-layout title="دليل المواد">
    <div
        x-data="recordForm({
            open: {{ Js::from($errors->any()) }},
            editingId: {{ Js::from(old('editing_id')) }},
            editingName: {{ Js::from(old('name')) }},
            storeUrl: {{ Js::from(route('products.store')) }},
            updateUrl: {{ Js::from(route('products.update', '__ID__')) }},
            fields: ['name', 'barcode', 'unit', 'location', 'min_stock', 'custom_sale_price'],
        })"
        class="flex flex-col gap-6"
    >
        <x-ui.page-header title="دليل المواد" :subtitle="'الدليل مشترك لكل مستودعات الشركة · الرصيد: '.$balanceScope">
            <x-ui.button type="button" @click="create()">+ مادة</x-ui.button>
        </x-ui.page-header>

        <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="min-w-[240px] flex-1 sm:max-w-sm">
                <x-ui.input name="q" type="search" :value="$search" placeholder="ابحث بالاسم أو الباركود" autocomplete="off" />
            </div>
            <x-ui.button variant="secondary">بحث</x-ui.button>
            <x-ui.checkbox name="inactive" label="إظهار الموقوفة" :checked="$showInactive" onchange="this.form.submit()" />
            <x-ui.checkbox name="low" label="المنخفضة فقط" :checked="$lowOnly" onchange="this.form.submit()" />
        </form>

        @if ($products->isEmpty())
            @if ($search !== '')
                <x-ui.empty-state title="ما في مادة بهالاسم أو الباركود" description="تأكد من الرمز، أو أضفها كمادة جديدة.">
                    <x-ui.button type="button" @click="create()">+ مادة جديدة</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state title="ما في مواد بعد" description="أضف موادك مرة وحدة، وبتستخدمها كل المستودعات بالفواتير.">
                    <x-ui.button type="button" @click="create()">+ مادة جديدة</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <x-ui.th>الاسم</x-ui.th>
                        <x-ui.th>الباركود</x-ui.th>
                        <x-ui.th>الوحدة</x-ui.th>
                        <x-ui.th>الموقع</x-ui.th>
                        <x-ui.th numeric>الرصيد</x-ui.th>
                        <x-ui.th numeric>السعر المقترح</x-ui.th>
                        <x-ui.th></x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr @class(['text-subtle' => ! $product->is_active])>
                            <x-ui.td class="font-medium">
                                {{ $product->name }}
                                @unless ($product->is_active)
                                    <x-ui.badge tone="slate" shape="tag" class="ms-2">موقوفة</x-ui.badge>
                                @endunless
                            </x-ui.td>
                            <x-ui.td>
                                @if ($product->barcode)
                                    <span dir="ltr">{{ $product->barcode }}</span>
                                @else
                                    —
                                @endif
                            </x-ui.td>
                            <x-ui.td>{{ $product->unitLabel() }}</x-ui.td>
                            <x-ui.td>{{ $product->location ?? '—' }}</x-ui.td>
                            <x-ui.td numeric class="whitespace-nowrap font-semibold">
                                <bdi dir="ltr">{{ $product->displayQuantity($balances[$product->id]) }}</bdi>
                                <span class="text-xs font-normal text-muted">{{ $product->unitLabel() }}</span>
                            </x-ui.td>
                            <x-ui.td numeric>
                                @if ($suggestedPrices[$product->id] !== null)
                                    <x-ui.money :amount="$suggestedPrices[$product->id]" />
                                @else
                                    <span class="text-subtle">—</span>
                                @endif
                            </x-ui.td>
                            <x-ui.td class="whitespace-nowrap text-left">
                                <div class="inline-flex items-center gap-1">
                                    <x-ui.button size="sm" variant="ghost" type="button"
                                                 @click="edit({{ Js::from([
                                                     ...$product->only(['id', 'name', 'barcode', 'unit', 'location']),
                                                     'min_stock' => \App\Support\Format::plain($product->min_stock),
                                                     'custom_sale_price' => \App\Support\Format::plain($product->custom_sale_price),
                                                 ]) }})">تعديل</x-ui.button>
                                    @if ($product->is_active)
                                        <x-ui.confirm :title="'إيقاف «'.$product->name.'»؟'" :action="route('products.toggle', $product)" method="PATCH" confirm-label="إيقاف" cancel-label="تراجع">
                                            <x-slot:trigger>
                                                <x-ui.button size="sm" variant="ghost" type="button" class="!text-danger">إيقاف</x-ui.button>
                                            </x-slot:trigger>
                                            ما رح تظهر بالبحث ولا بالباركود بالحركات الجديدة. الحركات القديمة والتقارير ما بتتأثر، وفيك تعيد تفعيلها بأي وقت.
                                        </x-ui.confirm>
                                    @else
                                        <form method="POST" action="{{ route('products.toggle', $product) }}">
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

            {{ $products->links() }}
        @endif

        <x-ui.drawer close="close()" title="editingId ? 'تعديل «' + editingName + '»' : 'مادة جديدة'">
            <form method="POST" :action="action" x-ref="form" class="flex flex-col gap-5" action="{{ route('products.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="! editingId">
                <input type="hidden" name="editing_id" :value="editingId ?? ''">

                @include('products.partials.form')

                <div class="flex gap-2">
                    <x-ui.button>حفظ</x-ui.button>
                    <x-ui.button variant="secondary" type="button" @click="close()">إلغاء</x-ui.button>
                </div>
            </form>
        </x-ui.drawer>
    </div>
</x-app-layout>
