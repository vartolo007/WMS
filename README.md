# WMS — منصة إدارة المستودعات

منصة ويب سحابية (SaaS) متعددة الشركات لإدارة المشتريات والمبيعات والمرتجعات والنقل بين المستودعات ومتابعة أرصدة المواد، مع تقارير مبنية بالكامل على جدول العمليات.

- كل شركة لها صاحب واحد وعدة مستودعات (كل مستودع حساب مستخدم)، وبياناتها معزولة عن باقي الشركات.
- كل حركة تُرحَّل إلى جدول `operations` ضمن معاملة واحدة، ومنه تُحسب الأرصدة والتقارير ولوحات التحكم.
- واجهة عربية بالكامل (RTL)، وألوان الواجهة وشعارها وعملتها من إعدادات الشركة.

## الحالة

| المرحلة | الحالة |
|---|---|
| 0–2: الأساس، الشركات والأدوار، الأدلة والإعدادات | منجزة |
| 3: فواتير الشراء والبيع والترحيل | منجزة |
| 4: المرتجعات (بالسعر الأصلي وحصة الحسم النسبية) | منجزة |
| 5: التقارير (14 تقريرًا) ولوحات التحكم | منجزة |
| 7: النسخ الاحتياطي اليومي، مراجعة التتبّع، هذا الملف | منجزة |
| 6: النقل بين المستودعات (فوري) وإلغاء الحركات (تُحذف سطورها من العمليات وتبقى الحركة «ملغاة») | منجزة |
| حزمة الراحة: استيراد CSV، إضافة مادة/طرف من الفاتورة، احفظ واطبع، كاميرا الباركود، تنبيهات البريد | منجزة |

## الأدوار

- **مدير المنصة** (`super_admin`): خارج الشركات؛ يفعّل الاشتراكات ويوقفها ويمدّد التجارب من صفحة الشركات.
- **صاحب الشركة** (`owner`): يرى كل مستودعات شركته، ويدير المستودعات والإعدادات والمواد والأطراف والحركات والتقارير.
- **المستودع** (`warehouse`): يعمل على مستودعه فقط.

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

`migrate:fresh --seed` ينشئ مدير المنصة من `ADMIN_EMAIL` و`ADMIN_PASSWORD` في `.env`، وعند `APP_ENV=local` فقط شركة تجريبية بحسابات
`owner@demo.test` (صاحب الشركة) و`city@demo.test` و`rural@demo.test` (مستودعان)، وكلمة المرور `password`. لا تعمل هذه الحسابات في الإنتاج.
الشركة التجريبية فيها أسبوع حركات جاهز (شراء، بيع، مرتجع، نقل)، فاللوحات والتقارير بتعرض أرقامًا من أول دخول، و«السكر» بمستودع الريف تحت حده الأدنى.

## النشر على السيرفر (الإنتاج)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env    # ثم عدّل القيم أدناه
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force   # مدير المنصة فقط، بدون بيانات تجريبية
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- في `.env`: `APP_ENV=production` و`APP_DEBUG=false` و`APP_URL` بعنوان الموقع (HTTPS، وهو شرط لكاميرا الباركود)، وبيانات `DB_*` و`MAIL_*`، و`ADMIN_EMAIL` و`ADMIN_PASSWORD` لمدير المنصة.
- شغّل المجدول كل دقيقة (قسم «المهام المجدولة» أدناه)، وإلا ما بتنعمل النسخ الاحتياطية ولا تنبيهات البريد.
- مجلدا `storage` و`bootstrap/cache` لازم يكونوا قابلين للكتابة من خادم الويب.
- بعد أي تعديل على `.env` أعد `php artisan config:cache`.

### مفاتيح `.env` الخاصة بالمنصة

| المفتاح | الافتراضي | الغرض |
|---|---|---|
| `WMS_TRIAL_DAYS` | 14 | مدة الفترة التجريبية للشركة الجديدة |
| `WMS_STAGNANT_DAYS` | 60 | بعدها تُعدّ المادة راكدة في لوحة التحكم |
| `WMS_SUPPORT_PHONE` / `_EMAIL` / `_HOURS` | قيم تجريبية | وسيلة التواصل التي تظهر لصاحب الشركة الموقوفة |
| `WMS_MYSQLDUMP_PATH` | `mysqldump` | مسار `mysqldump`؛ على XAMPP لازم يتحدد: `C:/xampp/mysql/bin/mysqldump.exe` (لأنه مش بالـ PATH) |
| `WMS_BACKUP_KEEP_DAYS` | 14 | عمر النسخ الاحتياطية قبل حذفها (الأحدث تبقى دائمًا) |
| `WMS_BACKUP_PATH` | `storage/app/backups` | مكان النسخ (خارج `public`) |

## الاختبارات والتنسيق

```bash
php artisan test
./vendor/bin/pint
```

الاختبارات تعمل على قاعدة `wms_testing`. اختبارات النسخ الاحتياطي تحتاج `mysqldump` (تُتخطّى مع سبب واضح إن لم يوجد).

## المهام المجدولة والنسخ الاحتياطي

| المهمة | موعدها | الأمر |
|---|---|---|
| نسخة احتياطية لقاعدة البيانات | يوميًا 02:00 | `php artisan wms:backup` |
| اختبار استرجاع أحدث نسخة | الأحد 03:00 | `php artisan wms:backup:check` |
| بريد المواد المنخفضة لصاحب كل شركة (فقط إن وُجدت مواد منخفضة) | يوميًا 08:00 | `php artisan wms:alerts:low-stock` |

المجدول لا يعمل وحده: لازم استدعاء `php artisan schedule:run` كل دقيقة.

```bash
# لينكس (cron)
* * * * * cd /path/to/wms && php artisan schedule:run >> /dev/null 2>&1
```

```bat
:: ويندوز (Task Scheduler) — مرة وحدة بصلاحيات المدير
schtasks /Create /SC MINUTE /MO 1 /TN "WMS Scheduler" /TR "C:\xampp\php\php.exe D:\wms\artisan schedule:run"
```

- `wms:backup` يكتب `wms-YYYYmmdd-HHMMSS.sql.gz` في مجلد النسخ، ويرفض أي نسخة ناقصة، ويحذف القديم، ويخرج برمز خطأ عند الفشل (والفشل مسجّل في `storage/logs`).
- `wms:backup:check` يسترجع أحدث نسخة في قاعدة مؤقتة (`<DB_DATABASE>_restore_check`)، ويقارن جداولها وأعداد صفوفها بالقاعدة الحية، ثم يحذفها. يحتاج صلاحية `CREATE DATABASE`؛ بدونها يكتفي بالتحقق من اكتمال النسخة وجداولها.
- **انسخ مجلد النسخ إلى مكان آخر** (قرص خارجي أو خدمة تخزين): نسخة على نفس الجهاز لا تحمي من ضياع الجهاز.

### البريد والتنبيهات

- بريد المواد المنخفضة يوصل لصاحب الشركة، ويقدر يوقفه من «إعدادات الشركة». لا يُرسل لشركة موقوفة ولا عند عدم وجود مواد منخفضة.
- فشل النسخ الاحتياطي أو اختبار الاسترجاع أو مهمة التنبيه يُرسل بريدًا إلى `ADMIN_EMAIL` (والفشل مسجّل في `storage/logs` دائمًا، وتعطّل البريد لا يخفيه).
- للإرسال الفعلي بدّل `MAIL_MAILER=log` إلى SMTP في `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="alerts@example.com"
```

محليًا مع `log` تقرأ الرسالة في `storage/logs/laravel.log`، وتجرّبها بـ `php artisan wms:alerts:low-stock`.

## استيراد CSV وكاميرا الباركود

- **استيراد CSV:** زر «استيراد من CSV» في صفحتي المواد والأطراف. يقبل UTF-8 أو ويندوز-1256 (إكسل عربي) والفواصل `,` `;` أو Tab، حتى 5000 صف. يُفحص الملف كله أولًا ويظهر أي خطأ مع رقم السطر، ولا يُستورد شيء إلا إذا كان الملف سليمًا كله. ملف نموذجي قابل للتنزيل من نفس الصفحة.
- **كاميرا الباركود:** تعتمد على `BarcodeDetector` المضمّن في المتصفح (كروم/إيدج)، ولا تظهر إلا حيث يدعمها. تحتاج **HTTPS** (أو `localhost`)، وإلا يبقى الماسح USB والكتابة اليدوية.

### استرجاع نسخة يدويًا

```bash
# 1) أنشئ قاعدة فارغة (اسم جديد حتى لا تكتب فوق الحية)
mysql -uroot -e "CREATE DATABASE wms_restored CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 2) حمّل النسخة المطلوبة
gzip -dc storage/app/backups/wms-20261020-021500.sql.gz | mysql -uroot wms_restored

# 3) تأكد من الأعداد، ثم وجّه DB_DATABASE في .env إلى القاعدة المسترجعة (أو بدّل أسماء القاعدتين)
mysql -uroot -e "SELECT COUNT(*) FROM wms_restored.users"
```

على ويندوز بدون `gzip`: فُكّ الضغط بـ 7-Zip ثم `mysql -uroot wms_restored < wms-....sql` (من `C:\xampp\mysql\bin`).

## التوثيق

- المتطلبات: [`docs/SRS.md`](docs/SRS.md)
- قاعدة البيانات: [`docs/schema.json`](docs/schema.json)
- بنية الكود: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- مراجعة التتبّع (المتطلبات ← الكود ← الاختبارات): [`docs/TRACEABILITY.md`](docs/TRACEABILITY.md)
- الخطة والمراحل: [`docs/PLAN.md`](docs/PLAN.md)
- القرارات والأسئلة المفتوحة: [`docs/DECISIONS.md`](docs/DECISIONS.md)

---

Abd Al-Rahman Al-Azhari — الطريقة الأزهرية
