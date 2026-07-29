<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Checkout\CheckoutSummary;
use App\DTOs\Checkout\CheckoutValidationError;
use PHPUnit\Framework\TestCase;

class CheckoutSummaryTest extends TestCase
{
    public function test_invalid_summary_exposes_structured_errors_and_zero_totals(): void
    {
        $summary = CheckoutSummary::invalid([
            new CheckoutValidationError(
                code: 'insufficient_stock',
                field: 'items',
                message: 'Insufficient stock.',
                cartItemId: 12,
                productId: 34,
            ),
        ], 'USD', 'b2c');

        $this->assertFalse($summary->isValid());
        $this->assertSame('0.0000', $summary->subtotal);
        $this->assertSame('0.0000', $summary->taxTotal);
        $this->assertSame('0.0000', $summary->shippingAmount);
        $this->assertSame('0.0000', $summary->grandTotal);
        $this->assertSame('USD', $summary->currencyCode);
        $this->assertSame('b2c', $summary->taxMode);
        $this->assertSame([
            'code' => 'insufficient_stock',
            'field' => 'items',
            'message' => 'Insufficient stock.',
            'cart_item_id' => 12,
            'product_id' => 34,
        ], $summary->errors[0]);
    }
}
