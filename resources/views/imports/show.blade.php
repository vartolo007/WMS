{{-- Import products or parties from a CSV file: upload → preview or errors → confirm. All or nothing. --}}
<x-app-layout :title="'استيراد '.$service->plural()">
    <div class="flex max-w-4xl flex-col gap-6">
        <x-ui.page-header :title="'استيراد '.$service->plural().' من ملف CSV'" subtitle="ارفع الملف، شوف الملخّص، وبعدها أكّد. إذا في سطر غلط ما بينستورد شي.">
            <x-ui.button variant="secondary" :href="route($backRoute)">رجوع</x-ui.button>
        </x-ui.page-header>

        <section class="flex flex-col gap-4 rounded-xl border border-line bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <b class="text-base">1 · جهّز الملف</b>
                <x-ui.button variant="secondary" size="sm" :href="route('imports.sample', $kind)">تنزيل ملف نموذجي</x-ui.button>
            </div>
            <p class="text-sm leading-7 text-muted">
                من Excel: <b class="text-ink">حفظ باسم ← CSV</b>. السطر الأول عناوين الأعمدة، وكل سطر بعده {{ $kind === 'products' ? 'مادة' : 'طرف' }}.
                الترميز UTF-8 أو Windows-1256 والفاصل «,» أو «;» كلهم مقبولين، والحد الأقصى 5,000 سطر.
            </p>
            <x-ui.table>
                <thead>
                    <tr><x-ui.th>العمود</x-ui.th><x-ui.th>إلزامي؟</x-ui.th><x-ui.th>مثال</x-ui.th></tr>
                </thead>
                <tbody>
                    @foreach ($service->help() as [$name, $required, $example])
                        <tr>
                            <x-ui.td class="font-semibold">{{ $name }}</x-ui.td>
                            <x-ui.td>{{ $required ? 'نعم' : 'لا' }}</x-ui.td>
                            <x-ui.td class="text-muted">{{ $example }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-line bg-white p-5">
            <b class="text-base">2 · ارفع الملف</b>
            <form method="POST" action="{{ route('imports.preview', $kind) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="file" accept=".csv,.txt,text/csv" required
                       class="min-w-0 flex-1 rounded-lg border border-line-strong bg-white p-2 text-sm file:me-3 file:rounded-md file:border-0 file:bg-canvas file:px-3 file:py-1.5 file:text-sm file:font-medium">
                <x-ui.button>فحص الملف</x-ui.button>
            </form>
            @error('file')
                <x-ui.alert>{{ $message }}</x-ui.alert>
            @enderror
        </section>

        @if ($result)
            <section class="flex flex-col gap-4 rounded-xl border border-line bg-white p-5">
                <b class="text-base">3 · النتيجة</b>

                @if ($result['errors'] !== [])
                    <x-ui.alert :title="count($result['errors']).' من '.$result['count'].' سطر فيهم أخطاء — ما انستورد شي'">
                        صحّح الأسطر التالية بالملف وارفعه من جديد.
                    </x-ui.alert>
                    <x-ui.table>
                        <thead><tr><x-ui.th>السطر</x-ui.th><x-ui.th>المشكلة</x-ui.th></tr></thead>
                        <tbody>
                            @foreach (array_slice($result['errors'], 0, 50) as $error)
                                <tr>
                                    <x-ui.td class="whitespace-nowrap font-semibold tabular-nums">{{ $error['line'] }}</x-ui.td>
                                    <x-ui.td>{{ $error['message'] }}</x-ui.td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    @if (count($result['errors']) > 50)
                        <span class="text-[13px] text-muted">وغيرها {{ count($result['errors']) - 50 }} سطر. صحّح هدول وارفع الملف لتشوفهم.</span>
                    @endif
                @else
                    <x-ui.alert tone="success" :title="'الملف سليم: '.$result['count'].' سطر جاهزين للاستيراد'">
                        هي أول {{ count($result['preview']) }} سطور. ما انستورد شي لسا.
                    </x-ui.alert>
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach (array_keys($result['preview'][0]) as $field)
                                    <x-ui.th>{{ $field }}</x-ui.th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['preview'] as $row)
                                <tr>
                                    @foreach ($row as $value)
                                        <x-ui.td>{{ $value ?? '—' }}</x-ui.td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    <form method="POST" action="{{ route('imports.confirm', $kind) }}" class="flex gap-2">
                        @csrf
                        <x-ui.button>استورد {{ $result['count'] }} سطر</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('imports.show', $kind)">إلغاء</x-ui.button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
