<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [

            // Store
            ['group' => 'store', 'key' => 'store_name', 'value' => 'My Store', 'type' => 'text'],
            ['group' => 'store', 'key' => 'store_email', 'value' => '', 'type' => 'email'],
            ['group' => 'store', 'key' => 'store_phone', 'value' => '', 'type' => 'text'],
            ['group' => 'store', 'key' => 'store_address', 'value' => '', 'type' => 'textarea'],

            // Localization
            ['group' => 'localization', 'key' => 'default_locale', 'value' => 'en', 'type' => 'select'],
            ['group' => 'localization', 'key' => 'timezone', 'value' => 'Asia/Beirut', 'type' => 'text'],

            // Currency
            ['group' => 'currency', 'key' => 'default_currency', 'value' => 'USD', 'type' => 'select'],

            // Tax
            ['group' => 'tax', 'key' => 'tax_mode', 'value' => 'b2c', 'type' => 'select'],
            ['group' => 'tax', 'key' => 'default_tax_id', 'value' => null, 'type' => 'select'],

            // Cart
            ['group' => 'cart', 'key' => 'lifetime_days', 'value' => '30', 'type' => 'integer'],

            // Checkout
            ['group' => 'checkout', 'key' => 'allow_guest_checkout', 'value' => '1', 'type' => 'boolean'],

        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                [
                    'group' => $setting['group'],
                    'key' => $setting['key'],
                ],
                $setting
            );
        }

        foreach ([
            ['key' => 'store_logo_path', 'type' => 'text'],
            ['key' => 'facebook_url', 'type' => 'url'],
            ['key' => 'whatsapp_url', 'type' => 'url'],
            ['key' => 'instagram_url', 'type' => 'url'],
        ] as $setting) {
            Setting::firstOrCreate(
                ['group' => 'store', 'key' => $setting['key']],
                ['value' => '', 'type' => $setting['type']]
            );
        }

        $manualPaymentSettings = [
            ['key' => 'manual_whatsapp_number', 'type' => 'text'],
            ['key' => 'manual_wallet_title', 'type' => 'text'],
            ['key' => 'manual_wallet_name', 'type' => 'text'],
            ['key' => 'manual_wallet_number', 'type' => 'text'],
            ['key' => 'manual_wallet_instructions', 'type' => 'textarea'],
            ['key' => 'manual_bank_title', 'type' => 'text'],
            ['key' => 'manual_bank_name', 'type' => 'text'],
            ['key' => 'manual_bank_account_name', 'type' => 'text'],
            ['key' => 'manual_bank_account_number', 'type' => 'text'],
            ['key' => 'manual_bank_iban', 'type' => 'text'],
            ['key' => 'manual_bank_swift', 'type' => 'text'],
            ['key' => 'manual_bank_instructions', 'type' => 'textarea'],
        ];

        foreach ($manualPaymentSettings as $setting) {
            Setting::firstOrCreate(
                ['group' => 'payments', 'key' => $setting['key']],
                ['value' => '', 'type' => $setting['type']]
            );
        }
    }
}
