<?php

declare(strict_types=1);

use App\Services\IngredientScaling\EnergyNorms;
use Illuminate\Support\Carbon;

function kcal(string $gender, string $birth, string $on, ?string $weight = null, ?string $height = null): string
{
    return (new EnergyNorms)->dailyKcal($gender, Carbon::parse($birth), Carbon::parse($on), $weight, $height);
}

it('separates infants at 6 and 7 months', function (): void {
    expect(kcal('male', '2026-01-15', '2026-07-14'))->toBe('550.00')
        ->and(kcal('male', '2026-01-15', '2026-07-15'))->toBe('550.00')
        ->and(kcal('male', '2026-01-15', '2026-08-15'))->toBe('700.00')
        ->and(kcal('female', '2025-07-20', '2026-07-19'))->toBe('700.00')
        ->and(kcal('female', '2025-07-20', '2026-07-20'))->toBe('1000.00');
});

it('counts a birth date after the plan date as 0 months', function (): void {
    expect(kcal('male', '2026-12-01', '2026-11-01'))->toBe('550.00');
});

it('uses Table 2 for children and adolescents regardless of body data', function (): void {
    expect(kcal('male', '2019-01-01', '2026-06-01', '20', '110'))->toBe('1800.00')
        ->and(kcal('male', '2014-01-01', '2026-06-01'))->toBe('2350.00')
        ->and(kcal('female', '2014-01-01', '2026-06-01'))->toBe('2100.00')
        ->and(kcal('male', '2011-01-01', '2026-06-01'))->toBe('3000.00')
        ->and(kcal('female', '2011-01-01', '2026-06-01'))->toBe('2450.00')
        ->and(kcal('male', '2008-01-01', '2026-06-01'))->toBe('3400.00')
        ->and(kcal('female', '2008-01-01', '2026-06-01'))->toBe('2500.00');
});

it('switches from the youth table to the adult table at 19', function (): void {
    expect(kcal('male', '2008-06-01', '2026-05-31'))->toBe('3400.00')
        ->and(kcal('male', '2007-06-01', '2026-06-01', '75', '180'))->toBe('3200.00')
        ->and(kcal('female', '2007-06-01', '2026-05-31'))->toBe('2500.00')
        ->and(kcal('female', '2007-06-01', '2026-06-01', '65', '165'))->toBe('2550.00');
});

it('switches adult age bands at 30/31, 50/51, 65/66 and 75/76', function (): void {
    $on = '2026-06-01';
    $man = fn (string $birth): string => kcal('male', $birth, $on, '75', '180');

    expect($man('1996-06-01'))->toBe('3200.00')  // 30
        ->and($man('1995-06-01'))->toBe('3000.00') // 31
        ->and($man('1976-06-01'))->toBe('3000.00') // 50
        ->and($man('1975-06-01'))->toBe('2850.00') // 51
        ->and($man('1961-06-01'))->toBe('2850.00') // 65
        ->and($man('1960-06-01'))->toBe('2550.00') // 66
        ->and($man('1951-06-01'))->toBe('2550.00') // 75
        ->and($man('1950-06-01'))->toBe('2450.00'); // 76
});

it('returns the table value for a man of 180 cm and 75 kg in the 31-50 band', function (): void {
    expect(kcal('male', '1986-06-01', '2026-06-01', '75', '180'))->toBe('3000.00');
});

it('interpolates between neighbouring weight rows', function (): void {
    expect(kcal('male', '1986-06-01', '2026-06-01', '80', '180'))->toBe('3125.00')
        ->and(kcal('female', '1986-06-01', '2026-06-01', '60', '165'))->toBe('2325.00');
});

it('clamps the weight to the normal BMI range for the height', function (): void {
    // 120 kg at 165 cm clamps to 24.9 * 1.65^2 = 67.79 kg: 2400 + 0.279 * 150 = 2441.85
    expect(kcal('female', '1986-06-01', '2026-06-01', '120', '165'))->toBe('2441.85')
        // 40 kg at 180 cm clamps to 18.5 * 1.8^2 = 59.94 kg: 2600 + 0.494 * 250 = 2723.5
        ->and(kcal('male', '1986-06-01', '2026-06-01', '40', '180'))->toBe('2723.50');
});

it('uses the first or last weight row outside the table', function (): void {
    // 140 cm clamps 30 kg up to 36.26 kg (below the first row); 210 cm clamps 300 kg down to 109.8 kg (above the last)
    expect(kcal('female', '1986-06-01', '2026-06-01', '30', '140'))->toBe('2100.00')
        ->and(kcal('female', '1986-06-01', '2026-06-01', '300', '210'))->toBe('2900.00')
        ->and(kcal('male', '1986-06-01', '2026-06-01', '300', '210'))->toBe('3550.00');
});

it('uses the reference row for adults missing body data and flags it', function (): void {
    $norms = new EnergyNorms;
    $birth = Carbon::parse('1986-06-01');
    $on = Carbon::parse('2026-06-01');

    expect($norms->dailyKcal('male', $birth, $on, null, null))->toBe('3000.00')
        ->and($norms->dailyKcal('female', $birth, $on, '70', null))->toBe('2400.00')
        ->and($norms->usesReferenceRow($birth, $on, null, null))->toBeTrue()
        ->and($norms->usesReferenceRow($birth, $on, '70', null))->toBeTrue()
        ->and($norms->usesReferenceRow($birth, $on, '70', '170'))->toBeFalse()
        ->and($norms->usesReferenceRow(Carbon::parse('2016-06-01'), $on, null, null))->toBeFalse();
});
