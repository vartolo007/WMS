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
