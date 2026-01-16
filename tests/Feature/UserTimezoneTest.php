<?php

use App\Enums\Timezone;
use App\Models\User;

test('user can have a timezone assigned', function () {
    $user = User::factory()->create([
        'timezone' => Timezone::Syria,
    ]);

    expect($user->timezone)->toBeInstanceOf(Timezone::class);
    expect($user->timezone)->toBe(Timezone::Syria);
    expect($user->timezone->value)->toBe('Asia/Damascus');
});

test('user timezone is cast correctly from database', function () {
    $user = User::factory()->create([
        'timezone' => Timezone::Turkey,
    ]);

    $user->refresh();

    expect($user->timezone)->toBeInstanceOf(Timezone::class);
    expect($user->timezone)->toBe(Timezone::Turkey);
    expect($user->timezone->value)->toBe('Europe/Istanbul');
});

test('user timezone can be null', function () {
    $user = User::factory()->create([
        'timezone' => null,
    ]);

    expect($user->timezone)->toBeNull();
});

test('user factory assigns a random timezone', function () {
    $user = User::factory()->create();

    expect($user->timezone)->toBeInstanceOf(Timezone::class);
    expect($user->timezone)->toBeIn(Timezone::cases());
});

test('timezone enum has correct values', function () {
    expect(Timezone::Syria->value)->toBe('Asia/Damascus');
    expect(Timezone::Turkey->value)->toBe('Europe/Istanbul');
});

test('timezone enum values method returns all timezone strings', function () {
    $values = Timezone::values();

    expect($values)->toContain('Asia/Damascus');
    expect($values)->toContain('Europe/Istanbul');
    expect($values)->toHaveCount(2);
});

test('timezone enum display name method returns correct names', function () {
    expect(Timezone::Syria->displayName())->toBe('Syria');
    expect(Timezone::Turkey->displayName())->toBe('Turkey');
});
