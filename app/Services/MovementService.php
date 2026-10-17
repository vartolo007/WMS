<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\Role;
use App\Exceptions\InsufficientStockException;
use App\Models\Movement;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates saving a movement: rules that need database state, totals, number, items, posting.
 * Data shape (required, numeric, decimals per unit) is the FormRequest's job; permission is the Policy's.
 * Every business-rule failure is a ValidationException keyed to the field, so the form keeps its input.
 *
 * Expected `$data`: warehouse_id, party_id?, date?, document_no?, discount?,
 * items: list of {product_id, quantity, unit_price?}.
 */
class MovementService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly PricingService $pricing,
        private readonly NumberGenerator $numbers,
        private readonly PostingService $posting,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function purchase(User $actor, array $data): Movement
    {
        return $this->create(MovementType::Purchase, $actor, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sale(User $actor, array $data): Movement
    {
        return $this->create(MovementType::Sale, $actor, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(MovementType $type, User $actor, array $data): Movement
    {
        $rawItems = array_values($data['items'] ?? []);

        if ($rawItems === []) {
            throw ValidationException::withMessages(['items' => 'أضف بندًا واحدًا على الأقل.']);
        }

        $warehouse = $this->warehouse($actor, $data['warehouse_id'] ?? null);
        $party = $this->party($type, $actor, $data['party_id'] ?? null);
        $items = $this->lines($type, $rawItems, $this->products($rawItems));

        $subtotal = Money::sum(array_column($items, 'line_total'));
        $discount = Money::money((string) ($data['discount'] ?? '0'));

        if (Money::compare($discount, $subtotal) > 0) {
            throw ValidationException::withMessages(['discount' => 'الحسم أكبر من مجموع الفاتورة.']);
        }

        return DB::transaction(function () use ($type, $actor, $data, $warehouse, $party, $items, $subtotal, $discount) {
            if ($type->reducesSourceStock()) {
                $this->checkStock($items, $warehouse->id);
            }

            $movement = new Movement([
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'document_no' => filled($data['document_no'] ?? null) ? $data['document_no'] : null,
                'party_id' => $party?->id,
                'date' => $data['date'] ?? today()->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => Money::subtract($subtotal, $discount),
                'created_by' => $actor->id,
            ]);
            $movement->company_id = $actor->company_id;
            $movement->number = $this->numbers->next($actor->company_id, $type);
            $movement->save();

            foreach ($items as $item) {
                $movement->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);
            }

            $this->posting->post($movement);

            return $movement->load('items');
        });
    }

    /**
     * The movement's warehouse: an active warehouse account of the actor's company (SRS 7).
     */
    private function warehouse(User $actor, mixed $warehouseId): User
    {
        $warehouse = $warehouseId === null ? null : User::query()
            ->where('company_id', $actor->company_id)
            ->where('role', Role::Warehouse)
            ->where('is_active', true)
            ->find($warehouseId);

        return $warehouse ?? throw ValidationException::withMessages(['warehouse_id' => 'المستودع المحدد غير صالح أو موقوف.']);
    }

    private function party(MovementType $type, User $actor, mixed $partyId): ?Party
    {
        if ($partyId === null || $partyId === '') {
            if ($type->requiresParty()) {
                throw ValidationException::withMessages(['party_id' => 'اختر المورد.']);
            }

            return null;
        }

        $party = Party::query()->where('company_id', $actor->company_id)->find($partyId);

        if ($party === null) {
            throw ValidationException::withMessages(['party_id' => 'الطرف المحدد غير موجود.']);
        }

        if ($party->type !== $type->partyType()) {
            throw ValidationException::withMessages(['party_id' => $type->partyType()?->value === 'supplier'
                ? 'طرف فاتورة الشراء يجب أن يكون موردًا.'
                : 'طرف فاتورة البيع يجب أن يكون عميلًا.']);
        }

        if (! $party->is_active) {
            throw ValidationException::withMessages(['party_id' => 'الطرف موقوف ولا يقبل حركات جديدة.']);
        }

        return $party;
    }

    /**
     * @param  list<array<string, mixed>>  $rawItems
     * @return Collection<int, Product>
     */
    private function products(array $rawItems): Collection
    {
        return Product::query()->whereIn('id', array_column($rawItems, 'product_id'))->get()->keyBy('id');
    }

    /**
     * Normalised lines with the price fixed at saving time (FR-13): the submitted price wins,
     * otherwise a sale takes the suggested price (FR-14).
     *
     * @param  list<array<string, mixed>>  $rawItems
     * @param  Collection<int, Product>  $products
     * @return list<array{product_id: int, quantity: string, unit_price: string, line_total: string}>
     */
    private function lines(MovementType $type, array $rawItems, Collection $products): array
    {
        $lines = [];

        foreach ($rawItems as $index => $raw) {
            $product = $products->get($raw['product_id'] ?? null);

            if ($product === null) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'المادة غير موجودة.']);
            }

            if (! $product->is_active) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => "المادة «{$product->name}» موقوفة ولا تقبل حركات جديدة."]);
            }

            $price = filled($raw['unit_price'] ?? null)
                ? (string) $raw['unit_price']
                : ($type === MovementType::Sale ? $this->pricing->suggestedPrice($product) : null);

            if ($price === null) {
                throw ValidationException::withMessages(["items.{$index}.unit_price" => "لا يوجد سعر للمادة «{$product->name}»، أدخل السعر."]);
            }

            $quantity = Money::quantity((string) $raw['quantity']);
            $price = Money::money($price);

            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'line_total' => Money::line($quantity, $price),
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{product_id: int, quantity: string, unit_price: string, line_total: string}>  $items
     */
    private function checkStock(array $items, int $warehouseId): void
    {
        try {
            $this->stock->lockAndCheck($items, $warehouseId);
        } catch (InsufficientStockException $e) {
            $index = array_search($e->productId, array_column($items, 'product_id'), true);
            $name = Product::query()->whereKey($e->productId)->value('name');

            throw ValidationException::withMessages(["items.{$index}.quantity" => sprintf(
                'الكمية المطلوبة من «%s» (%s) أكبر من الرصيد المتاح (%s) في هذا المستودع.',
                $name,
                Format::quantity($e->requested),
                Format::quantity($e->available),
            )]);
        }
    }
}
