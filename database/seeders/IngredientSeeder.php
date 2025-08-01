<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create units first
        $units = [
            ['code' => 'g', 'conversion_factor_to_base' => 1.0000],
            ['code' => 'kg', 'conversion_factor_to_base' => 1000.0000],
            ['code' => 'ml', 'conversion_factor_to_base' => 1.0000],
            ['code' => 'l', 'conversion_factor_to_base' => 1000.0000],
            ['code' => 'szt', 'conversion_factor_to_base' => 1.0000],
            ['code' => 'tbsp', 'conversion_factor_to_base' => 15.0000],
            ['code' => 'tsp', 'conversion_factor_to_base' => 5.0000],
            ['code' => 'cup', 'conversion_factor_to_base' => 240.0000],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }

        // Create common ingredients
        $ingredients = [
            'Flour',
            'Sugar',
            'Salt',
            'Black Pepper',
            'Olive Oil',
            'Butter',
            'Eggs',
            'Milk',
            'Chicken Breast',
            'Beef',
            'Pork',
            'Salmon',
            'Tomatoes',
            'Onions',
            'Garlic',
            'Potatoes',
            'Carrots',
            'Broccoli',
            'Bell Peppers',
            'Spinach',
            'Rice',
            'Pasta',
            'Bread',
            'Cheese',
            'Yogurt',
            'Lemon',
            'Lime',
            'Basil',
            'Oregano',
            'Thyme',
            'Paprika',
            'Cumin',
            'Ginger',
            'Honey',
            'Soy Sauce',
            'Vinegar',
            'Chicken Stock',
            'Vegetable Stock',
            'Coconut Milk',
            'Heavy Cream',
        ];

        foreach ($ingredients as $ingredient) {
            Ingredient::firstOrCreate(['name' => $ingredient]);
        }
    }
}
