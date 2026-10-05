<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">المستودعات</h2>
            <a href="{{ route('warehouses.create') }}" class="px-4 py-2 rounded-md bg-primary text-white text-sm hover:bg-primary-dark">مستودع جديد</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded-md bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">المستودع</th>
                            <th class="px-4 py-3 text-start font-medium">البريد الإلكتروني</th>
                            <th class="px-4 py-3 text-start font-medium">الحالة</th>
                            <th class="px-4 py-3 text-start font-medium">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($warehouses as $warehouse)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $warehouse->name }}</td>
                                <td class="px-4 py-3 text-gray-700" dir="ltr">{{ $warehouse->email }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'px-2 py-1 rounded-full text-xs',
                                        'bg-green-100 text-green-800' => $warehouse->is_active,
                                        'bg-red-100 text-red-800' => ! $warehouse->is_active,
                                    ])>{{ $warehouse->is_active ? 'فعّال' : 'موقوف' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="text-primary hover:text-primary-dark underline">تعديل</a>
                                        <form method="POST" action="{{ route('warehouses.toggle', $warehouse) }}">
                                            @csrf @method('PATCH')
                                            @if ($warehouse->is_active)
                                                <x-danger-button>إيقاف</x-danger-button>
                                            @else
                                                <x-secondary-button type="submit">تفعيل</x-secondary-button>
                                            @endif
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">لا توجد مستودعات بعد. ابدأ بإنشاء أول مستودع.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
