{{-- Screen 14 (design batch 5): company settings with a live preview. Only the owner reaches it. --}}
<x-app-layout title="إعدادات الشركة">
    <form
        method="POST"
        action="{{ route('settings.update') }}"
        enctype="multipart/form-data"
        x-data="settingsPreview({
            name: {{ Js::from(old('name', $company->name)) }},
            color: {{ Js::from(old('primary_color', $company->primary_color)) }},
            currency: {{ Js::from(old('currency_symbol', $company->currency_symbol ?? '')) }},
            ratio: {{ Js::from(old('markup_percent', \App\Support\Format::plain($company->markup_percent))) }},
            logoUrl: {{ Js::from($company->logoUrl()) }},
        })"
        class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_440px]"
    >
        @csrf
        @method('PUT')

        <div class="flex min-w-0 flex-col gap-4">
            <x-ui.page-header title="إعدادات الشركة" />

            {{-- Identity: name, logo, colour --}}
            <section class="flex flex-col gap-5 rounded-xl border border-line bg-white p-6">
                <div class="max-w-[420px]">
                    <x-ui.input name="name" label="اسم الشركة" x-model="name" required />
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-[13px] font-medium text-ink">الشعار <span class="font-normal text-muted">(اختياري)</span></span>
                    <div class="flex items-center gap-4">
                        <span class="flex h-[72px] w-[72px] flex-none items-center justify-center overflow-hidden rounded-xl border border-dashed border-line-strong bg-canvas text-xs text-subtle">
                            <img x-show="logoUrl" :src="logoUrl" alt="" class="h-full w-full object-contain">
                            <span x-show="! logoUrl">شعار</span>
                        </span>
                        <div class="flex flex-col gap-1.5">
                            <label class="inline-flex h-9 w-fit cursor-pointer items-center rounded-lg border border-line-strong bg-white px-3.5 text-sm hover:bg-canvas">
                                رفع صورة
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickLogo($event)">
                            </label>
                            <span class="text-[13px] text-muted">PNG أو JPG أو WEBP، حتى 1 ميغا. بدون شعار بنعرض أول حرف من الاسم.</span>
                        </div>
                    </div>
                    @error('logo')
                        <span class="text-[13px] font-medium text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <fieldset class="flex flex-col gap-2.5">
                    <legend class="mb-2.5 text-[13px] font-medium text-ink">اللون الأساسي</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach (config('wms.palette') as $hex)
                            <label title="{{ $hex }}" class="cursor-pointer">
                                <input type="radio" name="primary_color" value="{{ $hex }}" x-model="color" class="peer sr-only">
                                <span class="block h-10 w-10 rounded-full ring-offset-2 peer-checked:ring-2 peer-checked:ring-ink peer-focus-visible:ring-2 peer-focus-visible:ring-primary-ring" style="background: {{ $hex }}"></span>
                            </label>
                        @endforeach
                    </div>
                    @error('primary_color')
                        <span class="text-[13px] font-medium text-danger">{{ $message }}</span>
                    @enderror
                    <span class="text-[13px] text-muted">بيتطبّق على كل الشاشات والمطبوعات لكل مستخدمين الشركة.</span>
                </fieldset>
            </section>

            {{-- Currency and pricing ratio (FR-25, FR-09) --}}
            <section class="flex flex-wrap gap-5 rounded-xl border border-line bg-white p-6">
                <div class="w-40">
                    <x-ui.input name="currency_symbol" label="رمز العملة" optional x-model="currency" placeholder="ل.س" maxlength="10" />
                </div>

                <div class="w-44">
                    <x-ui.input name="markup_percent" label="نسبة التسعير" numeric suffix="%" required x-model="ratio" inputmode="decimal" autocomplete="off" />
                    <span x-show="ratioError" x-text="ratioError" class="mt-1.5 block text-[13px] font-medium text-danger"></span>
                </div>

                <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 basis-full rounded-[10px] bg-canvas px-4 py-3.5 text-[15px] tabular-nums">
                    <span>مثال: سعر شراء</span>
                    <b><bdi dir="ltr" x-text="purchasePrice"></bdi> <span x-text="currency"></span></b>
                    <span>← سعر بيع مقترح</span>
                    <b class="text-[17px] text-primary"><bdi dir="ltr" x-text="example"></bdi> <span x-text="currency"></span></b>
                </div>
            </section>

            {{-- Daily low-stock e-mail to the owner --}}
            <section class="flex flex-col gap-2 rounded-xl border border-line bg-white p-6">
                <x-ui.checkbox name="low_stock_alerts" label="ابعتلي بريد يوميًا لما تنخفض المواد عن حدها الأدنى" :checked="old() ? old('low_stock_alerts', false) : $company->low_stock_alerts" />
                <span class="text-[13px] text-muted">بيوصل على بريدك ({{ auth()->user()->email }}) الساعة 8 الصبح، وبس لما يكون في مواد منخفضة.</span>
            </section>

            <div><x-ui.button class="h-11 px-[22px]">حفظ الإعدادات</x-ui.button></div>
        </div>

        {{-- Live preview: takes the chosen colour before it is saved --}}
        <aside class="flex flex-col gap-2.5 xl:sticky xl:top-24 xl:mt-[52px]">
            <span class="text-sm font-semibold text-muted">معاينة فورية</span>

            <div
                class="overflow-hidden rounded-xl border border-line bg-canvas"
                :style="`--color-primary: ${color}; --color-primary-light: color-mix(in srgb, ${color} 9%, white)`"
            >
                <div class="flex h-12 items-center gap-2.5 border-b border-line bg-white px-3.5">
                    <template x-if="logoUrl">
                        <img :src="logoUrl" alt="" class="h-7 w-7 rounded-md object-contain">
                    </template>
                    <template x-if="! logoUrl">
                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-primary text-sm font-bold text-white" x-text="initial"></span>
                    </template>
                    <b class="truncate text-sm" x-text="name"></b>
                </div>

                <div class="flex">
                    <div class="flex w-[120px] flex-none flex-col gap-0.5 border-e border-line bg-white p-2 text-xs">
                        <span class="rounded-md bg-primary-light px-2 py-[7px] font-semibold text-primary">لوحة التحكم</span>
                        <span class="px-2 py-[7px]">دليل المواد</span>
                        <span class="px-2 py-[7px]">المستودعات</span>
                        <span class="px-2 py-[7px]">الإعدادات</span>
                    </div>

                    <div class="flex flex-1 flex-col gap-2.5 p-3.5">
                        <div class="flex flex-col gap-0.5 rounded-lg border border-line bg-white p-2.5">
                            <span class="text-[11px] text-muted">سعر البيع المقترح</span>
                            <b class="text-lg tabular-nums text-primary"><bdi dir="ltr" x-text="example"></bdi> <span class="text-[11px] font-medium text-muted" x-text="currency"></span></b>
                        </div>
                        <div class="flex gap-1.5">
                            <span class="inline-flex h-[30px] items-center rounded-md bg-primary px-3 text-xs font-semibold text-white">حفظ</span>
                            <span class="inline-flex h-[30px] items-center rounded-md border border-line-strong bg-white px-3 text-xs">إلغاء</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </form>
</x-app-layout>
