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

            // Inventory
            ['group' => 'inventory', 'key' => 'manage_stock', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'inventory', 'key' => 'allow_backorders', 'value' => '0', 'type' => 'boolean'],

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
    }
}
