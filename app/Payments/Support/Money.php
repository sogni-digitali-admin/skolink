<?php

namespace App\Payments\Support;

class Money
{
    /**
     * Resolve the active ISO currency, falling back to MUR.
     *
     * @param  string|null  $currency
     * @return string
     */
    public static function currency($currency = null)
    {
        return $currency
            ?: (config('school.currency') ?: config('payments.currency_fallback', 'MUR'));
    }

    /**
     * Format integer cents for display, e.g. "MUR 1,250.00".
     *
     * @param  int  $cents
     * @param  string|null  $currency
     * @return string
     */
    public static function format($cents, $currency = null)
    {
        return static::currency($currency).' '.number_format(((int) $cents) / 100, 2);
    }

    /**
     * Convert a decimal amount (as entered by a user) to integer cents.
     *
     * @param  string|int|float  $amount
     * @return int
     */
    public static function toCents($amount)
    {
        return (int) round(((float) $amount) * 100);
    }
}
