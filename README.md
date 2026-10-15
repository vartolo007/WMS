# WMS — منصة إدارة المستودعات

منصة ويب سحابية (SaaS) متعددة الشركات لإدارة المشتريات والمبيعات والمرتجعات والنقل بين المستودعات ومتابعة أرصدة المواد، مع تقارير مبنية بالكامل على جدول العمليات.

- كل شركة لها صاحب واحد وعدة مستودعات (كل مستودع حساب مستخدم)، وبياناتها معزولة عن باقي الشركات.
- كل حركة تُرحَّل إلى جدول `operations` ضمن معاملة واحدة، ومنه تُحسب الأرصدة والتقارير.
- واجهة عربية بالكامل (RTL)، وألوان الواجهة من إعدادات الشركة.

## التقنيات

Laravel 12 · PHP 8.2 · MySQL (XAMPP) · Blade + Tailwind + Alpine.js · Laravel Breeze · Pest · brick/math

## التشغيل محليًا

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# أنشئ قاعدتي البيانات wms و wms_testing ثم:
php artisan migrate:fresh --seed
php artisan storage:link   # يعرض شعار الشركة (مجلد public/storage)
composer run dev
```

الاختبارات والتنسيق:

```bash
php artisan test
./vendor/bin/pint
```

## التوثيق

- المتطلبات: [`docs/SRS.md`](docs/SRS.md)
- قاعدة البيانات: [`docs/schema.json`](docs/schema.json)
- بنية الكود: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- الخطة والمراحل: [`docs/PLAN.md`](docs/PLAN.md)
- القرارات والأسئلة المفتوحة: [`docs/DECISIONS.md`](docs/DECISIONS.md)

---

Abd Al-Rahman Al-Azhari — الطريقة الأزهرية
