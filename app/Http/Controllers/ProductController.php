<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\PricingService;
use App\Services\ProductService;
use App\Services\StockService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The shared product catalogue (FR-07). No delete: deactivate instead (T12).
 */
class ProductController extends Controller
{
    public function index(Request $request, CurrentWarehouse $current, StockService $stock, PricingService $pricing): View
    {
        Gate::authorize('viewAny', Product::class);

        $showInactive = $request->boolean('inactive');

        $products = Product::query()
            ->search($request->query('q'))
            ->unless($showInactive, fn ($query) => $query->active())
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Balance of the warehouse in view (own warehouse, or the owner's top-bar choice); the whole
        // company when the owner looks at all warehouses. Always computed from operations (rule 8).
        $warehouse = $current->get();
        $ids = $products->getCollection()->pluck('id')->all();

        return view('products.index', [
            'products' => $products,
            'balances' => $stock->balances($ids, $warehouse?->id),
            'suggestedPrices' => $pricing->suggestedPrices($products->getCollection()),
            'balanceScope' => $warehouse?->name ?? 'كل المستودعات',
            'search' => (string) $request->query('q'),
            'showInactive' => $showInactive,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('products.index')->with('status', 'تمت إضافة المادة.');
    }

    public function update(UpdateProductRequest $request, Product $product, ProductService $products): RedirectResponse
    {
        $products->update($product, $request->validated());

        return redirect()->route('products.index')->with('status', 'تم حفظ المادة.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('status', $product->is_active ? 'تم تفعيل المادة.' : 'تم إيقاف المادة.');
    }
}
