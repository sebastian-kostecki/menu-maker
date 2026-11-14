<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meal_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('recipe_quantity', 10, 2);
            $table->decimal('computed_quantity', 12, 2);
            $table->decimal('quantity', 12, 2);
            $table->string('source', 20);
            $table->timestamps();

            $table->unique(['meal_id', 'ingredient_id']);
        });

        // Existing plans keep the amounts of their recipes as written.
        DB::statement(
            "INSERT INTO meal_ingredients
                (meal_id, ingredient_id, unit_id, recipe_quantity, computed_quantity, quantity, source, created_at, updated_at)
             SELECT m.id, ri.ingredient_id, ri.unit_id, ri.quantity, ri.quantity, ri.quantity, 'as_written', NOW(), NOW()
             FROM meals m
             JOIN recipe_ingredients ri ON ri.recipe_id = m.recipe_id
             ORDER BY m.id, ri.id"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_ingredients');
    }
};
