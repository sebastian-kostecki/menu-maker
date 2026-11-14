<?php

declare(strict_types=1);

use App\Http\Requests\FamilyMemberRequest;
use Illuminate\Support\Facades\Validator;

/**
 * @param  array<string, mixed>  $overrides
 */
function validateFamilyMember(array $overrides): Illuminate\Validation\Validator
{
    $data = ['first_name' => 'Ola', 'birth_date' => '1990-01-01', 'gender' => 'female', 'weight_kg' => 60, 'height_cm' => 165, ...$overrides];
    $request = FamilyMemberRequest::create('/family-members', 'POST', $data);

    $validator = Validator::make($data, $request->rules(), $request->messages());
    $validator->passes();

    return $validator;
}

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 14)->startOfDay());
});

it('requires weight and height from the 19th birthday on, not the day before', function (): void {
    $turnsNineteenToday = validateFamilyMember(['birth_date' => '2007-11-14', 'weight_kg' => null, 'height_cm' => null]);
    $turnsNineteenTomorrow = validateFamilyMember(['birth_date' => '2007-11-15', 'weight_kg' => null, 'height_cm' => null]);

    expect($turnsNineteenToday->errors()->first('weight_kg'))->toBe('Podaj wagę (wymagana od 19 lat).')
        ->and($turnsNineteenToday->errors()->first('height_cm'))->toBe('Podaj wzrost (wymagany od 19 lat).')
        ->and($turnsNineteenTomorrow->errors()->has('weight_kg'))->toBeFalse()
        ->and($turnsNineteenTomorrow->errors()->has('height_cm'))->toBeFalse();
});

it('accepts weight and height at the range edges and rejects values just outside', function (string $field, float $value, bool $valid): void {
    expect(validateFamilyMember([$field => $value])->errors()->has($field))->toBe(! $valid);
})->with([
    'weight 30' => ['weight_kg', 30, true],
    'weight 29.9' => ['weight_kg', 29.9, false],
    'weight 300' => ['weight_kg', 300, true],
    'weight 300.1' => ['weight_kg', 300.1, false],
    'height 100' => ['height_cm', 100, true],
    'height 99.9' => ['height_cm', 99.9, false],
    'height 250' => ['height_cm', 250, true],
    'height 250.1' => ['height_cm', 250.1, false],
]);

it('uses the Polish range messages', function (): void {
    expect(validateFamilyMember(['weight_kg' => 10])->errors()->first('weight_kg'))->toBe('Waga musi wynosić od 30 do 300 kg.')
        ->and(validateFamilyMember(['height_cm' => 'abc'])->errors()->first('height_cm'))->toBe('Wzrost musi wynosić od 100 do 250 cm.');
});
