<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

use Carbon\CarbonInterface;

/**
 * Daily energy needs from the NIZP-PZH 2020 nutrition norms (chapter "Energia", Tables 2-4,
 * https://ncez.pzh.gov.pl/wp-content/uploads/2021/03/normy_zywienia_2020web.pdf) at the
 * "umiarkowana" (moderate) activity level (PAL 1.75 for adults).
 *
 * Adult weight rows marked as derived (men 90/95/100 kg, women 80-100 kg) are an app extension,
 * not source data: a least-squares line through each age band's four source rows, extended every
 * 5 kg up to 100 kg and rounded to 50 kcal.
 */
final class EnergyNorms
{
    private const ADULT_FROM_YEARS = 19;

    private const REFERENCE_WEIGHT_KG = ['male' => '75', 'female' => '65'];

    private const MIN_NORMAL_BMI = '18.5';

    private const MAX_NORMAL_BMI = '24.9';

    private const SCALE = 12;

    /**
     * Children and adolescents (Table 2): [upper age in years inclusive, boys, girls].
     *
     * @var list<array{int, int, int}>
     */
    private const YOUTH_BANDS = [
        [3, 1000, 1000],
        [6, 1400, 1400],
        [9, 1800, 1800],
        [12, 2350, 2100],
        [15, 3000, 2450],
        [18, 3400, 2500],
    ];

    /**
     * Adult kcal by weight (Tables 3-4): [upper age inclusive (null = open), kcal per weight row].
     *
     * @var array<string, array{weights: list<int>, bands: list<array{int|null, list<int>}>}>
     */
    private const ADULT_TABLES = [
        'male' => [
            'weights' => [55, 65, 75, 85, 90, 95, 100],
            'bands' => [
                [30, [2650, 2900, 3200, 3450, 3600, 3750, 3850]],
                [50, [2600, 2850, 3000, 3250, 3350, 3450, 3550]],
                [65, [2450, 2650, 2850, 3050, 3150, 3250, 3350]],
                [75, [2150, 2350, 2550, 2750, 2850, 2950, 3050]],
                [null, [2050, 2250, 2450, 2650, 2750, 2850, 2950]],
            ],
        ],
        'female' => [
            'weights' => [45, 55, 65, 75, 80, 85, 90, 95, 100],
            'bands' => [
                [30, [2000, 2300, 2550, 2800, 2950, 3050, 3200, 3350, 3450]],
                [50, [2100, 2250, 2400, 2550, 2600, 2700, 2750, 2850, 2900]],
                [65, [2000, 2200, 2300, 2450, 2550, 2600, 2650, 2750, 2800]],
                [75, [1850, 2050, 2200, 2350, 2450, 2500, 2600, 2700, 2750]],
                [null, [1750, 1950, 2100, 2250, 2350, 2400, 2500, 2600, 2650]],
            ],
        ],
    ];

    /**
     * Daily kcal of one person on the given date, as a 2-decimal string.
     */
    public function dailyKcal(string $gender, CarbonInterface $birthDate, CarbonInterface $onDate, ?string $weightKg, ?string $heightCm): string
    {
        $months = $this->ageInMonths($birthDate, $onDate);

        if ($months < 7) {
            return '550.00';
        }

        if ($months < 12) {
            return '700.00';
        }

        $years = intdiv($months, 12);

        if ($years < self::ADULT_FROM_YEARS) {
            foreach (self::YOUTH_BANDS as [$upperAge, $boys, $girls]) {
                if ($years <= $upperAge) {
                    return $this->format((string) ($gender === 'female' ? $girls : $boys));
                }
            }
        }

        return $this->adultKcal($gender, $years, $weightKg, $heightCm);
    }

    /**
     * Whether dailyKcal() falls back to the reference row because an adult lacks weight or height.
     */
    public function usesReferenceRow(CarbonInterface $birthDate, CarbonInterface $onDate, ?string $weightKg, ?string $heightCm): bool
    {
        $isAdult = intdiv($this->ageInMonths($birthDate, $onDate), 12) >= self::ADULT_FROM_YEARS;

        return $isAdult && ! $this->hasBodyData($weightKg, $heightCm);
    }

    private function adultKcal(string $gender, int $years, ?string $weightKg, ?string $heightCm): string
    {
        $table = self::ADULT_TABLES[$gender === 'female' ? 'female' : 'male'];
        $weights = $table['weights'];
        $kcalRow = [];

        foreach ($table['bands'] as [$upperAge, $row]) {
            if ($upperAge === null || $years <= $upperAge) {
                $kcalRow = $row;
                break;
            }
        }

        if ($this->hasBodyData($weightKg, $heightCm)) {
            $weight = $this->clampToNormalBmi((string) $weightKg, (string) $heightCm);
        } else {
            $weight = self::REFERENCE_WEIGHT_KG[$gender === 'female' ? 'female' : 'male'];
        }

        $last = count($weights) - 1;

        if (bccomp($weight, (string) $weights[0], self::SCALE) <= 0) {
            return $this->format((string) $kcalRow[0]);
        }

        if (bccomp($weight, (string) $weights[$last], self::SCALE) >= 0) {
            return $this->format((string) $kcalRow[$last]);
        }

        for ($i = 0; $i < $last; $i++) {
            $lower = (string) $weights[$i];
            $upper = (string) $weights[$i + 1];

            if (bccomp($weight, $upper, self::SCALE) <= 0) {
                $share = bcdiv(bcsub($weight, $lower, self::SCALE), bcsub($upper, $lower, self::SCALE), self::SCALE);
                $span = bcsub((string) $kcalRow[$i + 1], (string) $kcalRow[$i], self::SCALE);

                return $this->format(bcadd((string) $kcalRow[$i], bcmul($span, $share, self::SCALE), self::SCALE));
            }
        }

        return $this->format((string) $kcalRow[$last]);
    }

    private function hasBodyData(?string $weightKg, ?string $heightCm): bool
    {
        return is_numeric($weightKg) && is_numeric($heightCm) && bccomp((string) $heightCm, '0', self::SCALE) > 0;
    }

    /**
     * Clamp the weight to the normal-BMI range [18.5, 24.9] x height(m)^2.
     */
    private function clampToNormalBmi(string $weightKg, string $heightCm): string
    {
        $heightM = bcdiv($heightCm, '100', self::SCALE);
        $squared = bcmul($heightM, $heightM, self::SCALE);
        $min = bcmul(self::MIN_NORMAL_BMI, $squared, self::SCALE);
        $max = bcmul(self::MAX_NORMAL_BMI, $squared, self::SCALE);

        if (bccomp($weightKg, $min, self::SCALE) < 0) {
            return $min;
        }

        if (bccomp($weightKg, $max, self::SCALE) > 0) {
            return $max;
        }

        return $weightKg;
    }

    /**
     * Completed months at $onDate; a birth date after $onDate counts as 0.
     */
    private function ageInMonths(CarbonInterface $birthDate, CarbonInterface $onDate): int
    {
        $birth = $birthDate->copy()->startOfDay();
        $on = $onDate->copy()->startOfDay();

        if ($birth->greaterThan($on)) {
            return 0;
        }

        $months = ($on->year - $birth->year) * 12 + ($on->month - $birth->month);

        if ($on->day < $birth->day) {
            $months--;
        }

        return max(0, $months);
    }

    /**
     * Round half up to 2 decimals.
     */
    private function format(string $kcal): string
    {
        return bcadd(bcadd($kcal, '0.005', self::SCALE), '0', 2);
    }
}
