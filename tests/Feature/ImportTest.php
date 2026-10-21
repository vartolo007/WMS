<?php

use App\Enums\PartyType;
use App\Models\Company;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\PartyImportService;
use App\Services\ProductImportService;
use App\Support\CsvReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->company = Company::factory()->create();
    $this->owner = User::factory()->for($this->company)->create();
    $this->warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->actingAs($this->owner);

    $this->upload = fn (string $content, string $name = 'items.csv') => UploadedFile::fake()->createWithContent($name, $content);
});

// --- Reading the file ---------------------------------------------------------------------------------------------

test('the reader takes UTF-8 with or without a BOM and any of the three delimiters', function () {
    $plain = CsvReader::read("الاسم,الوحدة\nزيت,لتر\n");
    $bom = CsvReader::read("\xEF\xBB\xBFالاسم;الوحدة\r\nزيت;لتر\r\n");
    $tab = CsvReader::read("الاسم\tالوحدة\nزيت\tلتر\n");

    foreach ([$plain, $bom, $tab] as $file) {
        expect($file['headers'])->toBe(['الاسم', 'الوحدة'])
            ->and($file['rows'][0]['cells'])->toBe(['الاسم' => 'زيت', 'الوحدة' => 'لتر'])
            ->and($file['rows'][0]['line'])->toBe(2);
    }
});

test('the reader converts the Windows-1256 files Arabic Excel saves', function () {
    $content = iconv('UTF-8', 'CP1256', "الاسم;الوحدة\nزيت زيتون;لتر\n");

    expect(CsvReader::read($content)['rows'][0]['cells'])->toBe(['الاسم' => 'زيت زيتون', 'الوحدة' => 'لتر']);
});

test('the reader keeps quoted cells, even across lines, and skips blank lines', function () {
    $file = CsvReader::read("الاسم,الموقع\n\n\"زيت, بكر\",\"رف A\nالطابق 2\"\n,\n");

    expect($file['rows'])->toHaveCount(1)
        ->and($file['rows'][0]['cells']['الاسم'])->toBe('زيت, بكر')
        ->and($file['rows'][0]['cells']['الموقع'])->toBe("رف A\nالطابق 2");
});

test('the reader refuses an empty file, a file with only headers, and a file that is too long', function () {
    expect(fn () => CsvReader::read(''))->toThrow(RuntimeException::class, 'الملف فاضي')
        ->and(fn () => CsvReader::read("الاسم,الوحدة\n"))->toThrow(RuntimeException::class, 'سطور بيانات')
        ->and(fn () => CsvReader::read("الاسم\n".str_repeat("x\n", CsvReader::MAX_ROWS + 1)))->toThrow(RuntimeException::class, 'أكبر من');
});

// --- Products: checking -------------------------------------------------------------------------------------------

test('products are read with Arabic or English headers and units', function () {
    $service = app(ProductImportService::class);

    $arabic = $service->check("الاسم,الوحدة,الباركود,الموقع,الحد الأدنى,سعر البيع\nزيت,لتر,111,رف A,20.5,19999.50\nعلب,علبة,,,12,\n");
    $english = $service->check("name;unit;min_stock\nشاي;kilo;1,5\nأقلام;piece;3\n");

    expect($arabic['errors'])->toBe([])
        ->and($arabic['rows'][0])->toMatchArray(['name' => 'زيت', 'unit' => 'liter', 'barcode' => '111', 'location' => 'رف A', 'min_stock' => '20.5', 'custom_sale_price' => '19999.50'])
        ->and($arabic['rows'][1])->toMatchArray(['name' => 'علب', 'unit' => 'box', 'barcode' => null, 'min_stock' => '12'])
        ->and($english['errors'])->toBe([])
        ->and($english['rows'][0])->toMatchArray(['unit' => 'kilo', 'min_stock' => '1.5']);
});

test('numbers typed the Arabic or the Excel way are understood', function () {
    $result = app(ProductImportService::class)->check("الاسم,الوحدة,الحد الأدنى,سعر البيع\nأ,لتر,١٢٫٥,\"1,250\"\nب,كيلو,\"2,500\",\"1 000.5\"\n");

    expect($result['errors'])->toBe([])
        ->and($result['rows'][0])->toMatchArray(['min_stock' => '12.5', 'custom_sale_price' => '1250'])
        ->and($result['rows'][1])->toMatchArray(['min_stock' => '2500', 'custom_sale_price' => '1000.5']);
});

test('every kind of mistake is reported with its line', function () {
    Product::factory()->for($this->company)->create(['barcode' => '999']);
    $file = implode("\n", [
        'الاسم,الوحدة,الباركود,الحد الأدنى,سعر البيع',
        ',لتر,,,',                // line 2: no name
        'زيت,لتر,111,,',         // line 3: fine
        'سكر,برميل,,,',          // line 4: unknown unit
        'خل,لتر,111,,',          // line 5: barcode repeated in the file
        'رز,كيلو,999,,',         // line 6: barcode already in the catalogue
        'علب,علبة,,1.5,',        // line 7: a box takes whole numbers
        'ملح,كيلو,,,-3',         // line 8: negative price
    ])."\n";

    $result = app(ProductImportService::class)->check($file);
    $errors = collect($result['errors'])->pluck('message', 'line');

    expect($result['count'])->toBe(7)
        ->and($result['rows'])->toHaveCount(1)
        ->and($errors->keys()->all())->toBe([2, 4, 5, 6, 7, 8])
        ->and($errors[2])->toContain('اسم المادة')
        ->and($errors[4])->toContain('الوحدة')
        ->and($errors[5])->toContain('مكرر بالملف')->and($errors[5])->toContain('سطر 3')
        ->and($errors[6])->toContain('موجودة بالشركة')
        ->and($errors[7])->toContain('كسور')
        ->and($errors[8])->toContain('سعر البيع');
});

test('a barcode of another company does not block the import', function () {
    Product::factory()->for(Company::factory()->create())->create(['barcode' => '555']);

    expect(app(ProductImportService::class)->check("الاسم,الوحدة,الباركود\nزيت,لتر,555\n")['errors'])->toBe([]);
});

test('a missing required column is named', function () {
    expect(fn () => app(ProductImportService::class)->check("الاسم,الباركود\nزيت,1\n"))->toThrow(RuntimeException::class, 'الوحدة');
    expect(fn () => app(PartyImportService::class)->check("الاسم,الهاتف\nأحمد,1\n"))->toThrow(RuntimeException::class, 'النوع');
});

test('the sample files import cleanly', function () {
    foreach ([ProductImportService::class, PartyImportService::class] as $class) {
        $service = app($class);
        $sample = $service->sample();

        expect($sample)->toStartWith("\xEF\xBB\xBF")
            ->and($service->check($sample)['errors'])->toBe([]);
    }
});

// --- Parties --------------------------------------------------------------------------------------------------------

test('parties are read with Arabic or English types and every mistake is reported', function () {
    $result = app(PartyImportService::class)->check(implode("\n", [
        'الاسم,النوع,الهاتف',
        'أبو خالد,عميل,0944 111 222',
        'الشام,مورد,',
        'John,supplier,+1 (555) 000',
        'بدون نوع,كوكب,',
        'هاتف غلط,عميل,abc',
        ',عميل,',
    ])."\n");
    $errors = collect($result['errors'])->pluck('message', 'line');

    expect(collect($result['rows'])->pluck('type', 'name')->all())->toBe(['أبو خالد' => 'customer', 'الشام' => 'supplier', 'John' => 'supplier'])
        ->and($errors->keys()->all())->toBe([5, 6, 7])
        ->and($errors[5])->toContain('النوع')
        ->and($errors[6])->toContain('هاتف')
        ->and($errors[7])->toContain('الاسم');
});

// --- The screens --------------------------------------------------------------------------------------------------------

test('the import page opens for the owner and the warehouse user and offers the sample', function () {
    foreach ([$this->owner, $this->warehouse] as $user) {
        foreach (['products', 'parties'] as $kind) {
            $this->actingAs($user)->get("/imports/{$kind}")->assertOk()->assertSee('تنزيل ملف نموذجي')->assertSee('إلزامي');
        }
    }

    $this->get('/imports/products/sample')->assertOk()->assertDownload('wms-products-sample.csv');
    $this->get('/imports/other')->assertNotFound();
    $this->get('/products')->assertSee('استيراد من CSV');
    $this->get('/parties')->assertSee('استيراد من CSV');
});

test('a clean file shows a preview first, then imports everything on confirmation', function () {
    $csv = "الاسم,الوحدة,الباركود\nزيت,لتر,111\nسكر,كيلو,222\nعلب,علبة,\n";

    $this->post('/imports/products/preview', ['file' => ($this->upload)($csv)])
        ->assertOk()
        ->assertSee('الملف سليم')
        ->assertSee('3 سطر')
        ->assertSee('استورد 3 سطر');

    expect(Product::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);

    $this->post('/imports/products/confirm')
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('status', 'تم استيراد 3 من المواد.');

    $products = Product::orderBy('id')->get();

    expect($products->pluck('name')->all())->toBe(['زيت', 'سكر', 'علب'])
        ->and($products->pluck('company_id')->unique()->all())->toBe([$this->company->id])
        ->and($products->pluck('unit')->all())->toBe(['liter', 'kilo', 'box'])
        ->and($products->pluck('is_active')->unique()->all())->toBe([true])
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    // the waiting file is gone: confirming again finds nothing
    $this->post('/imports/products/confirm')->assertRedirect(route('imports.show', 'products'))->assertSessionHasErrors('file');
});

test('a file with one bad row imports nothing and cannot be confirmed', function () {
    $csv = "الاسم,الوحدة\nزيت,لتر\nسكر,برميل\nعلب,علبة\n";

    $this->post('/imports/products/preview', ['file' => ($this->upload)($csv)])
        ->assertOk()
        ->assertSee('ما انستورد شي')
        ->assertSee('1 من 3 سطر');

    $this->post('/imports/products/confirm')->assertSessionHasErrors('file');

    expect(Product::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the file is checked again on confirmation: a barcode taken in the meantime stops the import', function () {
    $this->post('/imports/products/preview', ['file' => ($this->upload)("الاسم,الوحدة,الباركود\nزيت,لتر,777\nسكر,كيلو,\n")])->assertSee('الملف سليم');

    Product::factory()->for($this->company)->create(['barcode' => '777']);

    $this->post('/imports/products/confirm')->assertOk()->assertSee('ما انستورد شي')->assertSee('موجودة بالشركة');

    expect(Product::count())->toBe(1);
});

test('parties import the same way', function () {
    $csv = "الاسم,النوع,الهاتف\nأبو خالد,عميل,0944 111 222\nالشام,مورد,\n";

    $this->post('/imports/parties/preview', ['file' => ($this->upload)($csv, 'parties.csv')])->assertOk()->assertSee('الملف سليم');
    $this->post('/imports/parties/confirm')->assertRedirect(route('parties.index'))->assertSessionHas('status', 'تم استيراد 2 من العملاء والموردين.');

    $parties = Party::orderBy('id')->get();

    expect($parties->pluck('type')->all())->toBe([PartyType::Customer, PartyType::Supplier])
        ->and($parties->first()->phone)->toBe('0944 111 222')
        ->and($parties->pluck('company_id')->unique()->all())->toBe([$this->company->id]);
});

test('the preview speaks Arabic: column headers and unit / type labels, not field keys', function () {
    $this->post('/imports/products/preview', ['file' => ($this->upload)('الاسم,الوحدة,الحد الأدنى
طحين,كيلو,30
')])
        ->assertOk()
        ->assertSeeInOrder(['الاسم', 'الوحدة', 'الحد الأدنى', 'طحين', 'كيلو', '30'])
        ->assertDontSee('min_stock')
        ->assertDontSee('>kilo<', false);

    $this->post('/imports/parties/preview', ['file' => ($this->upload)('الاسم,النوع
الشام,supplier
', 'parties.csv')])
        ->assertOk()
        ->assertSeeInOrder(['الاسم', 'النوع', 'الشام', 'مورد'])
        ->assertDontSee('>supplier<', false);
});

test('a row with several problems reports all of them on its line', function () {
    $this->post('/imports/parties/preview', ['file' => ($this->upload)('الاسم,النوع,الهاتف
,جار,abc
أبو خالد,عميل,0944
', 'parties.csv')])
        ->assertOk()
        ->assertSee('1 من 2 سطر')
        ->assertSeeInOrder(['2', 'اكتب رقم هاتف صحيح'])
        ->assertSee('اسم')
        ->assertSee('النوع');
});

test('an unusable upload is refused with a message', function () {
    $this->post('/imports/products/preview', ['file' => UploadedFile::fake()->create('items.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
    $this->post('/imports/products/preview', [])->assertSessionHasErrors('file');
    $this->post('/imports/products/preview', ['file' => ($this->upload)('')])->assertSessionHasErrors('file');
    $this->post('/imports/products/preview', ['file' => ($this->upload)("الاسم,الباركود\nزيت,1\n")])->assertSessionHasErrors('file');

    expect(Product::count())->toBe(0);
});

test('one company cannot confirm another company\'s waiting file', function () {
    $this->post('/imports/products/preview', ['file' => ($this->upload)("الاسم,الوحدة\nزيت,لتر\n")]);
    $token = session('import.products');

    $other = User::factory()->for(Company::factory()->create())->create();

    // a forged session token points into the other company's folder, where nothing waits
    $this->actingAs($other)->withSession(['import.products' => $token])->post('/imports/products/confirm')->assertSessionHasErrors('file');

    expect(Product::withoutGlobalScopes()->count())->toBe(0);
});

test('guests and the platform owner have no import', function () {
    auth()->logout();
    $this->get('/imports/products')->assertRedirect('/login');
    $this->post('/imports/products/preview')->assertRedirect('/login');

    $this->actingAs(User::factory()->superAdmin()->create())->get('/imports/products')->assertForbidden();
});
