<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportFileRequest;
use App\Models\Party;
use App\Models\Product;
use App\Services\CatalogImportService;
use App\Services\PartyImportService;
use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import products or parties from a CSV file (comfort pack). Two steps: the upload is read and validated in
 * full and shown as a preview or a list of errors; the confirmation re-reads the same stored file and
 * imports it all or not at all. Allowed for whoever may manage the catalogues (Q2).
 */
class ImportController extends Controller
{
    /** @var array<string, array{service: class-string<CatalogImportService>, model: class-string, route: string}> */
    private const KINDS = [
        'products' => ['service' => ProductImportService::class, 'model' => Product::class, 'route' => 'products.index'],
        'parties' => ['service' => PartyImportService::class, 'model' => Party::class, 'route' => 'parties.index'],
    ];

    public function show(string $kind): View
    {
        $service = $this->service($kind);

        return $this->page($kind, $service);
    }

    public function preview(ImportFileRequest $request, string $kind): View|RedirectResponse
    {
        $service = $this->service($kind);

        try {
            $result = $service->check($request->file('file')->get());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        // Only a clean file can be confirmed; it waits in private storage under a token kept in the session.
        $this->forget($kind);

        if ($result['errors'] === []) {
            $token = Str::lower(Str::random(32));
            Storage::disk('local')->put($this->path($kind, $token), $request->file('file')->get());
            session(["import.{$kind}" => $token]);
        }

        return $this->page($kind, $service, $result);
    }

    public function confirm(Request $request, string $kind): View|RedirectResponse
    {
        $service = $this->service($kind);
        $token = session("import.{$kind}");

        if (! $token || ! Storage::disk('local')->exists($this->path($kind, $token))) {
            return redirect()->route('imports.show', $kind)->withErrors(['file' => 'انتهت جلسة الاستيراد، ارفع الملف من جديد.']);
        }

        // Checked again: the catalogue may have changed since the preview (a barcode taken meanwhile).
        $result = $service->check(Storage::disk('local')->get($this->path($kind, $token)));

        if ($result['errors'] !== []) {
            $this->forget($kind);

            return $this->page($kind, $service, $result);
        }

        $count = $service->import($result['rows']);
        $this->forget($kind);

        return redirect()->route(self::KINDS[$kind]['route'])->with('status', "تم استيراد {$count} من {$service->plural()}.");
    }

    public function sample(string $kind): StreamedResponse
    {
        $service = $this->service($kind);

        return response()->streamDownload(fn () => print ($service->sample()), "wms-{$kind}-sample.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function service(string $kind): CatalogImportService
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        Gate::authorize('create', self::KINDS[$kind]['model']);

        return app(self::KINDS[$kind]['service']);
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function page(string $kind, CatalogImportService $service, ?array $result = null): View
    {
        return view('imports.show', [
            'kind' => $kind,
            'service' => $service,
            'result' => $result,
            'canConfirm' => $result !== null && $result['errors'] === [],
            'backRoute' => self::KINDS[$kind]['route'],
        ]);
    }

    private function path(string $kind, string $token): string
    {
        return 'imports/'.auth()->user()->company_id."/{$kind}-{$token}.csv";
    }

    /** Remove the waiting file of this user's company for this kind, and its token. */
    private function forget(string $kind): void
    {
        if ($token = session()->pull("import.{$kind}")) {
            Storage::disk('local')->delete($this->path($kind, $token));
        }
    }
}
