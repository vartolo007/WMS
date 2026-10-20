<?php

namespace App\Services;

use App\Enums\MovementStatus;
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
        private readonly ReturnService $returns,
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
        $this->rejectRepeatedProducts($rawItems);
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

            return $this->persist($type, $actor, $items, [
                'warehouse_id' => $warehouse->id,
                'document_no' => filled($data['document_no'] ?? null) ? $data['document_no'] : null,
                'party_id' => $party?->id,
                'date' => $data['date'] ?? today()->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => Money::subtract($subtotal, $discount),
            ]);
        });
    }

    /**
     * A return of a confirmed purchase or sale (FR-15). Party and warehouse are copied from the original,
     * prices are the original prices, and the discount is the proportional share of the original's.
     * `$data`: date?, items: list of {product_id, quantity}.
     *
     * Lock order: product rows (purchase return only) → the original movement row (so two returns of
     * one invoice cannot both take the same returnable quantity) → the company row (the number).
     *
     * @param  array<string, mixed>  $data
     */
    public function return(User $actor, Movement $original, array $data): Movement
    {
        $type = $original->type->returnType();

        if ($type === null || $original->company_id !== $actor->company_id) {
            throw ValidationException::withMessages(['original' => 'ما بتقدر ترجّع هالحركة.']);
        }

        $rawItems = array_values($data['items'] ?? []);

        if ($rawItems === []) {
            throw ValidationException::withMessages(['items' => 'أدخل كمية مرتجعة لبند واحد على الأقل.']);
        }

        $this->rejectRepeatedProducts($rawItems);

        // The same rule as a new movement: stopped warehouses, parties and products take no new movements (SRS 7).
        $warehouse = $this->warehouse($actor, $original->warehouse_id);
        $party = $original->party_id === null ? null : $this->party($type, $actor, $original->party_id);

        return DB::transaction(function () use ($type, $actor, $data, $original, $rawItems, $warehouse, $party) {
            if ($type->reducesSourceStock()) {
                $this->checkStock(
                    array_map(fn (array $item) => ['product_id' => (int) $item['product_id'], 'quantity' => Money::quantity((string) $item['quantity'])], $rawItems),
                    $warehouse->id,
                );
            }

            $locked = Movement::withoutGlobalScopes()->whereKey($original->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== MovementStatus::Confirmed) {
                throw ValidationException::withMessages(['original' => 'الفاتورة الأصلية ملغاة، ما بتقدر ترجّع منها.']);
            }

            $items = $this->returnLines($rawItems, $this->returns->lines($locked));
            $quote = $this->returns->quote($locked, Money::sum(array_column($items, 'line_total')));

            return $this->persist($type, $actor, $items, [
                'warehouse_id' => $warehouse->id,
                'party_id' => $party?->id,
                'related_movement_id' => $locked->id,
                'date' => $data['date'] ?? today()->toDateString(),
                ...$quote,
            ]);
        });
    }

    /**
     * Saves header and lines, takes the next number and posts. Runs inside the caller's transaction.
     *
     * @param  list<array{product_id: int, quantity: string, unit_price: string, line_total: string}>  $items
     * @param  array<string, mixed>  $header  warehouse_id, party_id?, document_no?, related_movement_id?, date, subtotal, discount, total
     */
    private function persist(MovementType $type, User $actor, array $items, array $header): Movement
    {
        $movement = new Movement([
            ...$header,
            'type' => $type,
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
    }

    /**
     * One line per product on an invoice (and on a return): the link between a return and its original
     * line is the product itself, so a repeated product would make "returnable" ambiguous.
     *
     * @param  list<array<string, mixed>>  $rawItems
     */
    private function rejectRepeatedProducts(array $rawItems): void
    {
        $seen = [];

        foreach ($rawItems as $index => $raw) {
            $productId = $raw['product_id'] ?? null;

            if ($productId !== null && isset($seen[$productId])) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'المادة مكررة بالفاتورة، ادمج الكميات بسطر واحد.']);
            }

            $seen[$productId] = true;
        }
    }

    /**
     * Return lines: the product must be on the original, the quantity within what is still returnable,
     * the price the original one. The product must also still be active (SRS 7).
     *
     * @param  list<array<string, mixed>>  $rawItems
     * @param  Collection<int, array{product: Product, product_id: int, quantity: string, unit_price: string, returned: string, returnable: string}>  $original
     * @return list<array{product_id: int, quantity: string, unit_price: string, line_total: string}>
     */
    private function returnLines(array $rawItems, Collection $original): array
    {
        $lines = [];

        foreach ($rawItems as $index => $raw) {
            $line = $original->get((int) ($raw['product_id'] ?? 0));

            if ($line === null) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'هالمادة مو بالفاتورة الأصلية.']);
            }

            if (! $line['product']->is_active) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => "المادة «{$line['product']->name}» موقوفة ولا تقبل حركات جديدة."]);
            }

            $quantity = Money::quantity((string) $raw['quantity']);

            if (Money::compare($quantity, $line['returnable']) > 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'المتاح للإرجاع '.$line['product']->displayQuantity($line['returnable']).' فقط.']);
            }

            $lines[] = [
                'product_id' => $line['product_id'],
                'quantity' => $quantity,
                'unit_price' => $line['unit_price'],
                'line_total' => Money::line($quantity, $line['unit_price']),
            ];
        }

        return $lines;
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
