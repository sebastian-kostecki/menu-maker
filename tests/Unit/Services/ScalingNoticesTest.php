<?php

declare(strict_types=1);

use App\Models\MealPlan;
use App\Services\IngredientScaling\ScalingNotices;

/**
 * @param  array<string, mixed>  $meta
 */
function planWithMeta(array $meta, string $status = 'done'): MealPlan
{
    return (new MealPlan)->forceFill(['status' => $status, 'generation_meta' => $meta]);
}

const OLD_PLAN_NOTICE = 'Ten jadłospis powstał przed przeliczaniem składników — zregeneruj go, aby dopasować ilości do rodziny.';

it('shows no notices for a refined plan without caveats', function (): void {
    $plan = planWithMeta(['version' => 'scaled-v1', 'scaling' => ['status' => 'refined', 'unscaled_recipes' => [], 'members_without_body' => []]]);

    expect(ScalingNotices::for($plan))->toBe(['status' => 'refined', 'notices' => []]);
});

it('explains amounts as written when there is no family', function (): void {
    $plan = planWithMeta(['version' => 'scaled-v1', 'scaling' => ['status' => 'as_written']]);

    expect(ScalingNotices::for($plan))->toBe([
        'status' => 'as_written',
        'notices' => ['Dodaj członków rodziny, aby przeliczyć składniki. Ilości są podane jak w przepisach.'],
    ]);
});

it('explains computed amounts after an AI failure', function (): void {
    $plan = planWithMeta(['version' => 'scaled-v1', 'scaling' => ['status' => 'computed']]);

    expect(ScalingNotices::for($plan)['notices'])
        ->toBe(['Nie udało się dopracować ilości przez AI — pokazujemy ilości przeliczone automatycznie.']);
});

it('names unscaled recipes and members without body data', function (): void {
    $plan = planWithMeta(['version' => 'scaled-v1', 'scaling' => [
        'status' => 'refined',
        'unscaled_recipes' => ['Sałatka', 'Zupa'],
        'members_without_body' => ['Anna', 'Jan'],
    ]]);

    expect(ScalingNotices::for($plan)['notices'])->toBe([
        'Przepisy z brakującą lub zbyt niską kalorycznością nie zostały przeliczone: Sałatka, Zupa.',
        'Uzupełnij wagę i wzrost, aby dokładniej przeliczyć porcje: Anna, Jan.',
    ]);
});

it('suggests regenerating a plan from before scaling', function (?string $version): void {
    $meta = $version === null ? [] : ['version' => $version];

    expect(ScalingNotices::for(planWithMeta($meta)))->toBe(['status' => null, 'notices' => [OLD_PLAN_NOTICE]]);
})->with([null, 'random-v1']);

it('orders the notices as specified', function (): void {
    $plan = planWithMeta(['version' => 'random-v1', 'scaling' => [
        'status' => 'computed',
        'unscaled_recipes' => ['Zupa'],
        'members_without_body' => ['Anna'],
    ]]);

    expect(ScalingNotices::for($plan)['notices'])->toBe([
        'Nie udało się dopracować ilości przez AI — pokazujemy ilości przeliczone automatycznie.',
        'Przepisy z brakującą lub zbyt niską kalorycznością nie zostały przeliczone: Zupa.',
        'Uzupełnij wagę i wzrost, aby dokładniej przeliczyć porcje: Anna.',
        OLD_PLAN_NOTICE,
    ]);
});

it('returns no notices for a plan that is not done', function (string $status): void {
    expect(ScalingNotices::for(planWithMeta([], $status)))->toBe(['status' => null, 'notices' => []]);
})->with(['pending', 'processing', 'error']);
