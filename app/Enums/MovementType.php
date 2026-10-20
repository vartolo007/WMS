<?php

namespace App\Enums;

/**
 * Everything that varies by movement type lives here (golden rule 4, SRS 6.1).
 */
enum MovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case PurchaseReturn = 'purchase_return';
    case SaleReturn = 'sale_return';
    case Transfer = 'transfer';

    /**
     * Which warehouse column each operations row touches, and the sign of its effect on stock.
     * Returns copy `warehouse_id` from the original invoice, so ['warehouse_id', ±1] is right for them too.
     *
     * @return list<array{0: string, 1: int}>
     */
    public function effects(): array
    {
        return match ($this) {
            self::Purchase, self::SaleReturn => [['warehouse_id', 1]],
            self::Sale, self::PurchaseReturn => [['warehouse_id', -1]],
            self::Transfer => [['warehouse_id', -1], ['target_warehouse_id', 1]],
        };
    }

    /**
     * The party is mandatory (a purchase needs its supplier). Sales and sale returns have an optional customer.
     */
    public function requiresParty(): bool
    {
        return match ($this) {
            self::Purchase, self::PurchaseReturn => true,
            default => false,
        };
    }

    /**
     * The kind of party this type accepts; null for a transfer, which has none.
     */
    public function partyType(): ?PartyType
    {
        return match ($this) {
            self::Purchase, self::PurchaseReturn => PartyType::Supplier,
            self::Sale, self::SaleReturn => PartyType::Customer,
            self::Transfer => null,
        };
    }

    public function isReturn(): bool
    {
        return $this === self::PurchaseReturn || $this === self::SaleReturn;
    }

    /**
     * The invoice type a return belongs to; null for types that are not returns.
     */
    public function originalType(): ?self
    {
        return match ($this) {
            self::PurchaseReturn => self::Purchase,
            self::SaleReturn => self::Sale,
            default => null,
        };
    }

    /**
     * The return type of an invoice; null for types that cannot be returned (returns, transfers).
     */
    public function returnType(): ?self
    {
        return match ($this) {
            self::Purchase => self::PurchaseReturn,
            self::Sale => self::SaleReturn,
            default => null,
        };
    }

    /**
     * Takes stock out of the movement's warehouse, so the stock must be checked first (SRS 7).
     */
    public function reducesSourceStock(): bool
    {
        return match ($this) {
            self::Sale, self::PurchaseReturn, self::Transfer => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'فاتورة شراء',
            self::Sale => 'فاتورة بيع',
            self::PurchaseReturn => 'مرتجع شراء',
            self::SaleReturn => 'مرتجع بيع',
            self::Transfer => 'نقل بين مستودعين',
        };
    }

    /**
     * Badge colour of the type (design batch 4): one fixed tone per type, never the company colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Purchase => 'blue',
            self::Sale => 'success',
            self::PurchaseReturn => 'warning',
            self::SaleReturn => 'danger',
            self::Transfer => 'purple',
        };
    }

    /**
     * The screen that creates a movement of this type (returns start from their original invoice).
     */
    public function createUrl(): string
    {
        return $this->isReturn()
            ? route('returns.create', ['type' => $this->value])
            : route('movements.create', ['type' => $this->value]);
    }

    /**
     * Short label for the tabs of the new-movement screen.
     */
    public function tabLabel(): string
    {
        return match ($this) {
            self::Purchase => 'شراء',
            self::Sale => 'بيع',
            self::Transfer => 'نقل',
            default => $this->label(),
        };
    }

    /**
     * What the net amount means in cash terms (every movement is cash, FR-10).
     */
    public function netLabel(): string
    {
        return match ($this) {
            self::Purchase => 'المدفوع نقداً',
            self::Sale => 'المقبوض نقداً',
            self::PurchaseReturn, self::SaleReturn => 'المردود نقداً',
            self::Transfer => 'بدون قيمة نقدية',
        };
    }

    /**
     * Label of the party field: the supplier of a purchase, the customer of a sale.
     */
    public function partyLabel(): string
    {
        return match ($this->partyType()) {
            PartyType::Supplier => 'المورد',
            PartyType::Customer => 'العميل',
            null => 'الطرف',
        };
    }

    /**
     * What cancelling does to the stock, in words, for the confirmation dialog (design screens 8-9). The text
     * follows the real effect of each type, so the user knows before confirming.
     */
    public function cancelEffect(string $items, string $warehouse, ?string $target = null): string
    {
        $effect = match ($this) {
            self::Purchase, self::SaleReturn => "{$items} رح ينخصموا من مستودع «{$warehouse}».",
            self::Sale, self::PurchaseReturn => "{$items} رح يرجعوا لمستودع «{$warehouse}».",
            self::Transfer => "{$items} رح يرجعوا لمستودع «{$warehouse}» وينخصموا من مستودع «{$target}».",
        };

        return "الإلغاء نهائي. {$effect} والحركة بتضل بالسجل كـ «ملغاة»، وما فيك ترجّعها.";
    }

    /**
     * Who is on the other side, for lists and printouts: the party, "cash sale" for an anonymous sale,
     * or the receiving warehouse of a transfer.
     */
    public function counterpartLabel(?string $partyName, ?string $targetWarehouseName): string
    {
        return match ($this) {
            self::Transfer => '← '.($targetWarehouseName ?? '—'),
            self::Sale => $partyName ?? 'بيع نقدي',
            default => $partyName ?? '—',
        };
    }

    /**
     * The two signature lines at the bottom of the printed copy (design screen 10).
     *
     * @return array{0: string, 1: string}
     */
    public function signatures(): array
    {
        return match ($this) {
            self::Purchase => ['توقيع المورد', 'توقيع المستلم'],
            self::Sale => ['توقيع المستلم', 'توقيع البائع'],
            self::PurchaseReturn => ['توقيع المورد', 'توقيع المسلِّم'],
            self::SaleReturn => ['توقيع المسلِّم', 'توقيع المستلم'],
            self::Transfer => ['توقيع المستلم', 'توقيع المرسِل'],
        };
    }

    /**
     * The type behind a display prefix ("S" → sale), or null when no type uses it.
     */
    public static function fromPrefix(string $prefix): ?self
    {
        foreach (self::cases() as $type) {
            if (strcasecmp($type->prefix(), $prefix) === 0) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Display prefix only; the database keeps a plain integer number.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Purchase => 'P',
            self::Sale => 'S',
            self::PurchaseReturn => 'PR',
            self::SaleReturn => 'SR',
            self::Transfer => 'T',
        };
    }

    /**
     * "S-0142".
     */
    public function displayNumber(int $number): string
    {
        return $this->prefix().'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
