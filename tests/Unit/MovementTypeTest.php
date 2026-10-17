<?php

use App\Enums\MovementType;
use App\Enums\PartyType;

test('effects follow SRS 6.1', function () {
    expect(MovementType::Purchase->effects())->toBe([['warehouse_id', 1]])
        ->and(MovementType::SaleReturn->effects())->toBe([['warehouse_id', 1]])
        ->and(MovementType::Sale->effects())->toBe([['warehouse_id', -1]])
        ->and(MovementType::PurchaseReturn->effects())->toBe([['warehouse_id', -1]])
        ->and(MovementType::Transfer->effects())->toBe([['warehouse_id', -1], ['target_warehouse_id', 1]]);
});

test('party rules per type', function () {
    expect(MovementType::Purchase->partyType())->toBe(PartyType::Supplier)
        ->and(MovementType::Sale->partyType())->toBe(PartyType::Customer)
        ->and(MovementType::Transfer->partyType())->toBeNull()
        ->and(MovementType::Purchase->requiresParty())->toBeTrue()
        ->and(MovementType::Sale->requiresParty())->toBeFalse();
});

test('returns point back to their invoice type', function () {
    expect(MovementType::PurchaseReturn->originalType())->toBe(MovementType::Purchase)
        ->and(MovementType::SaleReturn->originalType())->toBe(MovementType::Sale)
        ->and(MovementType::Sale->originalType())->toBeNull()
        ->and(MovementType::SaleReturn->isReturn())->toBeTrue()
        ->and(MovementType::Purchase->isReturn())->toBeFalse();
});

test('only types that take stock out check the source stock', function () {
    $checked = collect(MovementType::cases())->filter->reducesSourceStock()->all();

    expect($checked)->toHaveCount(3)
        ->and(MovementType::Purchase->reducesSourceStock())->toBeFalse()
        ->and(MovementType::SaleReturn->reducesSourceStock())->toBeFalse();
});

test('display numbers carry the prefix and are padded', function () {
    expect(MovementType::Sale->displayNumber(142))->toBe('S-0142')
        ->and(MovementType::PurchaseReturn->displayNumber(4))->toBe('PR-0004')
        ->and(MovementType::Transfer->displayNumber(25))->toBe('T-0025');
});
