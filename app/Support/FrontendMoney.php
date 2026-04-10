<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Models\WebsiteSetting;

final class FrontendMoney
{
    private function __construct(
        private readonly string $preferredCurrency,
        private readonly ?float $usdTryRate
    ) {}

    public static function for(?User $user): self
    {
        $preferredCurrency = in_array((string) ($user?->preferred_currency ?? 'USD'), ['USD', 'TRY'], true)
            ? (string) $user?->preferred_currency
            : 'USD';

        return new self($preferredCurrency, WebsiteSetting::getUsdTryRate());
    }

    public function format(float|int|string $amount, string $currency = 'USD', int $decimals = 2): string
    {
        $normalizedCurrency = strtoupper($currency);
        $normalizedAmount = (float) $amount;

        if ($normalizedCurrency === 'USD' && $this->preferredCurrency === 'TRY' && $this->usdTryRate !== null && $this->usdTryRate > 0) {
            $normalizedAmount *= $this->usdTryRate;
            $normalizedCurrency = 'TRY';
        }

        [$minFraction, $maxFraction] = $this->fractionDigitBounds($decimals);

        return $this->formatCurrency($normalizedAmount, $normalizedCurrency, $minFraction, $maxFraction);
    }

    /**
     * Mirrors JS `displayCurrency.format` (2,2) and `formatPerUnit` (2,6) fraction rules.
     *
     * @return array{0: int, 1: int}
     */
    private function fractionDigitBounds(int $decimals): array
    {
        if ($decimals <= 0) {
            return [0, 0];
        }

        if ($decimals === 1) {
            return [1, 1];
        }

        if ($decimals === 2) {
            return [2, 2];
        }

        return [2, min(6, $decimals)];
    }

    private function formatCurrency(float $amount, string $currencyCode, int $minFraction, int $maxFraction): string
    {
        if (! extension_loaded('intl')) {
            return $this->formatCurrencyFallback($amount, $currencyCode, $maxFraction);
        }

        $locale = $currencyCode === 'TRY' ? 'tr-TR' : 'en-US';
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $minFraction);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxFraction);
        $formatted = $formatter->formatCurrency($amount, $currencyCode);

        if ($formatted !== false) {
            return $formatted;
        }

        return $this->formatCurrencyFallback($amount, $currencyCode, $maxFraction);
    }

    private function formatCurrencyFallback(float $amount, string $currencyCode, int $fractionDigits): string
    {
        $value = number_format($amount, $fractionDigits, '.', ',');

        return match ($currencyCode) {
            'USD' => '$'.$value,
            'TRY' => $value.' TRY',
            default => $value.' '.$currencyCode,
        };
    }
}
