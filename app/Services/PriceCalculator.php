<?php

namespace App\Services;

class PriceCalculator
{
    /**
     * Calculate new price based on action.
     */
    public static function calculate(float $currentPrice, string $action, float $value): float
    {
        return match ($action) {
            'set_specific' => $value,
            'increase_amount' => $currentPrice + $value,
            'decrease_amount' => max(0, $currentPrice - $value),
            'increase_percent' => $currentPrice * (1 + $value / 100),
            'decrease_percent' => max(0, $currentPrice * (1 - $value / 100)),
            default => $currentPrice,
        };
    }

    /**
     * Apply rounding rule.
     */
    public static function round(float $price, string $rounding, ?float $customValue = null): float
    {
        return match ($rounding) {
            'none' => round($price, 2),
            'nearest_01' => round($price, 2),
            'nearest_whole' => round($price),
            'end_99' => floor($price) + 0.99,
            'end_custom' => floor($price) + ($customValue ?? 0.99),
            default => round($price, 2),
        };
    }
}
