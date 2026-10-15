{{-- Product fields inside the drawer. Values are filled by the drawer's Alpine code; after a failed save old() fills them. --}}
<x-ui.input name="name" label="اسم المادة" required placeholder="مثال: زيت زيتون" />
<x-ui.input name="barcode" label="الباركود" ltr optional hint="لازم يكون فريد بالشركة" autocomplete="off" />
<x-ui.select name="unit" label="الوحدة" :options="\App\Support\Units::options()" />
<x-ui.input name="location" label="الموقع" optional placeholder="مثال: رف A3" />
<x-ui.input name="min_stock" label="الحد الأدنى" numeric optional hint="تحته بتصير المادة منخفضة" inputmode="decimal" autocomplete="off" />
<x-ui.input name="custom_sale_price" label="سعر البيع اليدوي" numeric optional hint="إذا فاضي بنقترح السعر من آخر سعر شراء" inputmode="decimal" autocomplete="off" />
