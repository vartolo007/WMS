<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised inside a movement transaction when a product's balance in the source warehouse is too small.
 * MovementService turns it into a validation error on the right line.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct("Insufficient stock for product {$productId}: {$available} available, {$requested} requested.");
    }
}
