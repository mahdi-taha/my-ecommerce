<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['name' => 'Standard Tax', 'rate' => 11],
            ['name' => 'Reduced Tax', 'rate' => 5],
            ['name' => 'Zero Tax', 'rate' => 0],
        ];

        foreach ($taxes as $tax) {
            Tax::updateOrCreate(
                ['name' => $tax['name']],
                [
                    'rate' => $tax['rate'],
                    'status' => true,
                ]
            );
        }
    }
}
