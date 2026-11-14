<?php

declare(strict_types=1);

use App\Services\IngredientScaling\RefinementGuard;
use App\Services\IngredientScaling\RefinementItem;

function guardAccepts(string $unit, string $computed, string $proposal): ?string
{
    return (new RefinementGuard)->accept(new RefinementItem(1, 'Mąka', $unit, $computed), $proposal);
}

it('accepts floor or ceil for pieces and rejects anything else', function (): void {
    expect(guardAccepts('szt', '2.37', '2'))->toBe('2.00')
        ->and(guardAccepts('szt', '2.37', '3'))->toBe('3.00')
        ->and(guardAccepts('szt', '2.37', '2.5'))->toBeNull()
        ->and(guardAccepts('szt', '2.37', '4'))->toBeNull()
        ->and(guardAccepts('szt', '2.37', '1'))->toBeNull()
        ->and(guardAccepts('szt', '2.00', '2'))->toBe('2.00')
        ->and(guardAccepts('szt', '2.00', '3'))->toBeNull();
});

it('allows 0 pieces only below 0.5', function (): void {
    expect(guardAccepts('szt', '0.3', '0'))->toBe('0.00')
        ->and(guardAccepts('szt', '0.3', '1'))->toBe('1.00')
        ->and(guardAccepts('szt', '0.5', '0'))->toBeNull()
        ->and(guardAccepts('szt', '0.7', '0'))->toBeNull();
});

it('allows 25 percent deviation for other units', function (): void {
    expect(guardAccepts('g', '100.00', '125'))->toBe('125.00')
        ->and(guardAccepts('g', '100.00', '126'))->toBeNull()
        ->and(guardAccepts('g', '100.00', '75'))->toBe('75.00')
        ->and(guardAccepts('g', '100.00', '74'))->toBeNull()
        ->and(guardAccepts('l', '1.50', '1.4'))->toBe('1.40');
});

it('rejects negative and non-numeric proposals', function (): void {
    expect(guardAccepts('g', '100.00', '-100'))->toBeNull()
        ->and(guardAccepts('g', '100.00', 'abc'))->toBeNull()
        ->and(guardAccepts('szt', '2.37', '2.0000'))->toBe('2.00');
});
