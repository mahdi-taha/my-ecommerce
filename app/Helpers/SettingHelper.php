<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            [$group, $settingKey] = array_pad(
                explode('.', $key, 2),
                2,
                null
            );

            if (! $group || ! $settingKey) {
                return $default;
            }

            $setting = Setting::where('group', $group)
                ->where('key', $settingKey)
                ->first();

            if (! $setting) {
                return $default;
            }

            return match ($setting->type) {
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $setting->value,
                'decimal', 'number' => (float) $setting->value,
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }
}

if (! function_exists('store_currency_symbol')) {
    function store_currency_symbol(?string $currencyCode = null): string
    {
        return match ($currencyCode ?? setting('currency.default_currency', 'USD')) {
            'USD' => '$',
            'LBP' => 'L.L.',
            default => $currencyCode ?? setting('currency.default_currency', 'USD'),
        };
    }
}

if (! function_exists('format_store_price')) {
    function format_store_price(string|int|float $amount, ?string $currencyCode = null): string
    {
        return store_currency_symbol($currencyCode).' '.number_format((float) $amount, 2);
    }
}
