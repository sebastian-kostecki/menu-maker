<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecipeRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{name: string, quantity: int, unit_id: int}>
     */
    private function validIngredients(): array
    {
        return [['name' => 'Mąka', 'quantity' => 200, 'unit_id' => (int) Unit::where('code', 'g')->value('id')]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(StoreRecipeRequest|UpdateRecipeRequest $request, array $data): \Illuminate\Validation\Validator
    {
        $validator = Validator::make($data, $request->rules(), $request->messages());
        $request->withValidator($validator);
        $validator->passes();

        return $validator;
    }

    /**
     * @param  array<int, mixed>  $ingredients
     * @return array<string, mixed>
     */
    private function recipeWith(array $ingredients): array
    {
        return [
            'name' => 'Test Recipe',
            'category' => 'breakfast',
            'instructions' => 'Mix.',
            'calories' => 350,
            'servings' => 2,
            'ingredients' => $ingredients,
        ];
    }

    public function test_store_recipe_request_passes_with_valid_data(): void
    {
        $data = [
            'name' => 'Test Recipe',
            'category' => 'breakfast',
            'instructions' => 'Mix all ingredients and cook.',
            'calories' => 350,
            'servings' => 2,
            'ingredients' => $this->validIngredients(),
        ];

        $request = new StoreRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_store_recipe_request_fails_without_required_fields(): void
    {
        $data = [];

        $request = new StoreRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('category', $validator->errors()->toArray());
        $this->assertArrayHasKey('instructions', $validator->errors()->toArray());
        $this->assertArrayHasKey('calories', $validator->errors()->toArray());
        $this->assertArrayHasKey('servings', $validator->errors()->toArray());
    }

    public function test_store_recipe_request_validates_category(): void
    {
        $data = [
            'name' => 'Test Recipe',
            'category' => 'invalid_category',
            'instructions' => 'Mix all ingredients.',
            'calories' => 350,
            'servings' => 2,
        ];

        $request = new StoreRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category', $validator->errors()->toArray());
    }

    public function test_store_recipe_request_validates_positive_calories(): void
    {
        $data = [
            'name' => 'Test Recipe',
            'category' => 'breakfast',
            'instructions' => 'Mix all ingredients.',
            'calories' => -10,
            'servings' => 2,
        ];

        $request = new StoreRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('calories', $validator->errors()->toArray());
    }

    public function test_store_recipe_request_validates_positive_servings(): void
    {
        $data = [
            'name' => 'Test Recipe',
            'category' => 'breakfast',
            'instructions' => 'Mix all ingredients.',
            'calories' => 350,
            'servings' => 0,
        ];

        $request = new StoreRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('servings', $validator->errors()->toArray());
    }

    public function test_store_recipe_request_validates_max_name_length(): void
    {
        $data = [
            'name' => str_repeat('a', 256), // 256 characters, exceeds max of 255
            'category' => 'breakfast',
            'instructions' => 'Mix all ingredients.',
            'calories' => 350,
            'servings' => 2,
        ];

        $request = new StoreRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_update_recipe_request_passes_with_partial_data(): void
    {
        $data = [
            'name' => 'Updated Recipe Name',
            'ingredients' => $this->validIngredients(),
        ];

        $request = new UpdateRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_update_recipe_request_validates_category_when_provided(): void
    {
        $data = [
            'category' => 'invalid_category',
        ];

        $request = new UpdateRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category', $validator->errors()->toArray());
    }

    public function test_update_recipe_request_validates_calories_when_provided(): void
    {
        $data = [
            'calories' => -50,
        ];

        $request = new UpdateRecipeRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('calories', $validator->errors()->toArray());
    }

    public function test_valid_categories_are_accepted(): void
    {
        $validCategories = ['breakfast', 'supper', 'dinner'];

        foreach ($validCategories as $category) {
            $data = [
                'name' => 'Test Recipe',
                'category' => $category,
                'instructions' => 'Mix all ingredients.',
                'calories' => 350,
                'servings' => 2,
                'ingredients' => $this->validIngredients(),
            ];

            $request = new StoreRecipeRequest;
            $validator = Validator::make($data, $request->rules());

            $this->assertTrue($validator->passes(), "Category '{$category}' should be valid");
        }
    }

    public function test_ingredients_are_required_and_non_empty_on_store_and_update(): void
    {
        foreach ([new StoreRecipeRequest, new UpdateRecipeRequest] as $request) {
            $missing = $this->validate($request, ['name' => 'Test']);
            $empty = $this->validate($request, ['name' => 'Test', 'ingredients' => []]);

            $this->assertSame('Dodaj co najmniej jeden składnik.', $missing->errors()->first('ingredients'));
            $this->assertSame('Dodaj co najmniej jeden składnik.', $empty->errors()->first('ingredients'));
        }
    }

    public function test_ingredient_rows_reject_unknown_unit_and_zero_quantity(): void
    {
        $unitId = $this->validIngredients()[0]['unit_id'];
        $validator = $this->validate(new StoreRecipeRequest, $this->recipeWith([
            ['name' => 'Mąka', 'quantity' => 200, 'unit_id' => 999999],
            ['name' => 'Cukier', 'quantity' => 0, 'unit_id' => $unitId],
        ]));

        $this->assertArrayHasKey('ingredients.0.unit_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('ingredients.1.quantity', $validator->errors()->toArray());
    }

    public function test_unit_outside_the_five_allowed_codes_is_rejected(): void
    {
        $legacyUnitId = DB::table('units')->insertGetId([
            'code' => 'cup', 'conversion_factor_to_base' => 250, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $validator = $this->validate(new StoreRecipeRequest, $this->recipeWith([
            ['name' => 'Mąka', 'quantity' => 1, 'unit_id' => $legacyUnitId],
        ]));

        $this->assertArrayHasKey('ingredients.0.unit_id', $validator->errors()->toArray());
    }

    public function test_duplicate_ingredient_name_is_flagged_on_every_row_after_the_first(): void
    {
        $unitId = $this->validIngredients()[0]['unit_id'];

        foreach ([new StoreRecipeRequest, new UpdateRecipeRequest] as $request) {
            $validator = $this->validate($request, $this->recipeWith([
                ['name' => 'Mąka', 'quantity' => 1, 'unit_id' => $unitId],
                ['name' => ' mąka ', 'quantity' => 2, 'unit_id' => $unitId],
            ]));

            $errors = $validator->errors();
            $this->assertFalse($errors->has('ingredients.0.name'));
            $this->assertSame('Ten składnik już jest na liście.', $errors->first('ingredients.1.name'));
        }
    }

    public function test_calories_below_50_are_rejected_and_50_is_accepted(): void
    {
        foreach ([new StoreRecipeRequest, new UpdateRecipeRequest] as $request) {
            foreach ([0, 49.99] as $tooLow) {
                $rejected = $this->validate($request, ['calories' => $tooLow] + $this->recipeWith($this->validIngredients()));
                $this->assertSame('Kaloryczność całkowita musi wynosić co najmniej 50 kcal.', $rejected->errors()->first('calories'));
            }

            $accepted = $this->validate($request, ['calories' => 50] + $this->recipeWith($this->validIngredients()));
            $this->assertFalse($accepted->errors()->has('calories'));
        }
    }
}
