<?php

namespace Database\Seeders;

use App\Models\NftPackage;
use Illuminate\Database\Seeder;

class NftPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            ['name' => '$50 Package', 'price' => 50, 'daily_bonus' => 0.30],
            ['name' => '$100 Package', 'price' => 100, 'daily_bonus' => 0.40],
            ['name' => '$250 Package', 'price' => 250, 'daily_bonus' => 0.50],
            ['name' => '$500 Package', 'price' => 500, 'daily_bonus' => 0.60],
            ['name' => '$1,000 Package', 'price' => 1000, 'daily_bonus' => 0.70],
            ['name' => '$2,500 Package', 'price' => 2500, 'daily_bonus' => 0.80],
            ['name' => '$5,000 Package', 'price' => 5000, 'daily_bonus' => 1.00],
        ];

        foreach ($tiers as $i => $tier) {
            NftPackage::updateOrCreate(
                ['price' => $tier['price']],
                [
                    'name' => $tier['name'],
                    'daily_bonus' => $tier['daily_bonus'],
                    'cap_multiplier' => 4,
                    'is_active' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }
}
