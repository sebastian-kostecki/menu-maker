<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['code' => 'g', 'conversion_factor_to_base' => 1.0000],
            ['code' => 'kg', 'conversion_factor_to_base' => 1000.0000],
            ['code' => 'ml', 'conversion_factor_to_base' => 1.0000],
            ['code' => 'l', 'conversion_factor_to_base' => 1000.0000],
            ['code' => 'pcs', 'conversion_factor_to_base' => 1.0000],
            ['code' => 'tsp', 'conversion_factor_to_base' => 5.0000],
            ['code' => 'tbsp', 'conversion_factor_to_base' => 15.0000],
            ['code' => 'cup', 'conversion_factor_to_base' => 250.0000],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }
    }
}
