<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecipeRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_recipe_request_passes_with_valid_data(): void
    {
        $data = [
            'name' => 'Test Recipe',
            'category' => 'breakfast',
            'instructions' => 'Mix all ingredients and cook.',
            'calories' => 350,
            'servings' => 2,
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
            ];

            $request = new StoreRecipeRequest;
            $validator = Validator::make($data, $request->rules());

            $this->assertTrue($validator->passes(), "Category '{$category}' should be valid");
        }
    }
}
