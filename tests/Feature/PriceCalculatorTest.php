<?php

use App\Models\PricingRule;
use App\Services\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculates retail and wholesale from entry price using active rule', function () {
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 1000,
        'wholesale_percentage' => 2,
        'retail_percentage' => 10,
        'priority' => 0,
        'is_active' => true,
    ]);

    $calculator = app(PriceCalculator::class);
    $result = $calculator->calculate(100.0);

    expect($result['retail_price'])->toBe(110.0);
    expect($result['wholesale_price'])->toBe(102.0);
});

test('applies bankers rounding to prices', function () {
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 999999.99,
        'wholesale_percentage' => 33.33,
        'retail_percentage' => 33.33,
        'priority' => 0,
        'is_active' => true,
    ]);

    $calculator = app(PriceCalculator::class);
    $result = $calculator->calculate(10.0);

    expect($result['retail_price'])->toBe(13.33);
    expect($result['wholesale_price'])->toBe(13.33);
});

test('selects first matching rule by priority', function () {
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 1000,
        'wholesale_percentage' => 0,
        'retail_percentage' => 5,
        'priority' => 10,
        'is_active' => true,
    ]);
    PricingRule::create([
        'min_price' => 0,
        'max_price' => 1000,
        'wholesale_percentage' => 0,
        'retail_percentage' => 10,
        'priority' => 0,
        'is_active' => true,
    ]);

    $calculator = app(PriceCalculator::class);
    $result = $calculator->calculate(100.0);

    expect($result['retail_price'])->toBe(110.0);
});

test('throws when no rule matches entry price', function () {
    PricingRule::query()->delete();
    PricingRule::create([
        'min_price' => 100,
        'max_price' => 200,
        'wholesale_percentage' => 0,
        'retail_percentage' => 0,
        'priority' => 0,
        'is_active' => true,
    ]);

    $calculator = app(PriceCalculator::class);

    $calculator->calculate(50.0);
})->throws(\InvalidArgumentException::class);
