<?php

declare(strict_types=1);

use App\Services\IngredientScaling\ScalingCalculator;

it('computes factor 2 for the worked example', function (): void {
    expect((new ScalingCalculator)->factor('7200', 'supper', '1440'))->toStartWith('2.0000');
});

it('uses the 30/40/30 split mapped by stored category', function (): void {
    $calculator = new ScalingCalculator;

    expect(bccomp($calculator->factor('1000', 'breakfast', '300'), '1', 6))->toBe(0)
        ->and(bccomp($calculator->factor('1000', 'supper', '400'), '1', 6))->toBe(0)
        ->and(bccomp($calculator->factor('1000', 'dinner', '300'), '1', 6))->toBe(0);
});

it('returns null for recipes without calories', function (): void {
    $calculator = new ScalingCalculator;

    expect($calculator->factor('7200', 'supper', '0'))->toBeNull()
        ->and($calculator->factor('7200', 'supper', '0.00'))->toBeNull()
        ->and($calculator->factor('7200', 'supper', '-5'))->toBeNull();
});

it('scales a quantity and rounds half up to 2 decimals', function (): void {
    $calculator = new ScalingCalculator;

    expect($calculator->scale('100', '2'))->toBe('200.00')
        ->and($calculator->scale('1.005', '1'))->toBe('1.01')
        ->and($calculator->scale('1.0049', '1'))->toBe('1.00')
        ->and($calculator->scale('3', '0.333333333333'))->toBe('1.00');
});
