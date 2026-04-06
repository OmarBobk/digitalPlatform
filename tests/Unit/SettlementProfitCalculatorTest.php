<?php

use App\Enums\ProductAmountMode;
use App\Models\OrderItem;
use App\Services\SettlementProfitCalculator;

beforeEach(function (): void {
    $this->calculator = new SettlementProfitCalculator;
});

test('fixed mode uses per unit retail minus per unit entry', function (): void {
    $item = new OrderItem([
        'unit_price' => 15,
        'entry_price' => 10,
        'line_total' => 15,
        'amount_mode' => ProductAmountMode::Fixed,
    ]);

    expect($this->calculator->forOrderItem($item))->toBe(5.0);
});

test('custom mode uses line total minus computed entry total from pricing meta', function (): void {
    $item = new OrderItem([
        'unit_price' => 15,
        'entry_price' => 10,
        'line_total' => 150,
        'quantity' => 1,
        'requested_amount' => 10,
        'amount_mode' => ProductAmountMode::Custom,
        'pricing_meta' => [
            'computed_entry_total' => 100.0,
        ],
    ]);

    expect($this->calculator->forOrderItem($item))->toBe(50.0);
});

test('custom mode derives computed entry total from requested amount and entry when meta missing', function (): void {
    $item = new OrderItem([
        'unit_price' => 15,
        'entry_price' => 10,
        'line_total' => 150,
        'quantity' => 1,
        'requested_amount' => 10,
        'amount_mode' => ProductAmountMode::Custom,
        'pricing_meta' => null,
    ]);

    expect($this->calculator->forOrderItem($item))->toBe(50.0);
});

test('custom mode falls back to fixed formula when line entry total cannot be resolved', function (): void {
    $item = new OrderItem([
        'unit_price' => 15,
        'entry_price' => 10,
        'line_total' => 150,
        'quantity' => 1,
        'requested_amount' => null,
        'amount_mode' => ProductAmountMode::Custom,
        'pricing_meta' => null,
    ]);

    expect($this->calculator->forOrderItem($item))->toBe(5.0);
});

test('never returns negative profit', function (): void {
    $item = new OrderItem([
        'unit_price' => 5,
        'entry_price' => 10,
        'line_total' => 5,
        'amount_mode' => ProductAmountMode::Fixed,
    ]);

    expect($this->calculator->forOrderItem($item))->toBe(0.0);
});
