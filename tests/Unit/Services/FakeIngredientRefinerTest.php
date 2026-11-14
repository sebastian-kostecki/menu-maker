<?php

declare(strict_types=1);

use App\Services\IngredientScaling\Drivers\FakeIngredientRefiner;
use App\Services\IngredientScaling\RefinementItem;

it('rounds deterministically by unit', function (): void {
    $items = [
        new RefinementItem(1, 'Jajka', 'szt', '2.50'),
        new RefinementItem(2, 'Jajka', 'szt', '0.20'),
        new RefinementItem(3, 'Mąka', 'g', '203.49'),
        new RefinementItem(4, 'Mleko', 'ml', '99.50'),
        new RefinementItem(5, 'Mąka', 'kg', '1.25'),
    ];

    $result = (new FakeIngredientRefiner)->refine($items);

    expect(array_values($result))->toBe(['3', '1', '203', '100', '1.25']);
});
