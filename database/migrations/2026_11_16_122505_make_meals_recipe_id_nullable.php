<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow a meal without a recipe; a hard-deleted recipe now leaves its meals empty.
     */
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            $table->dropForeign('meals_recipe_id_foreign');
        });

        Schema::table('meals', function (Blueprint $table): void {
            $table->unsignedBigInteger('recipe_id')->nullable()->change();
        });

        Schema::table('meals', function (Blueprint $table): void {
            $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
        });
    }

    /**
     * Drop the empty meals and restore NOT NULL with the cascading foreign key.
     */
    public function down(): void
    {
        DB::table('meals')->whereNull('recipe_id')->delete();

        Schema::table('meals', function (Blueprint $table): void {
            $table->dropForeign('meals_recipe_id_foreign');
        });

        Schema::table('meals', function (Blueprint $table): void {
            $table->unsignedBigInteger('recipe_id')->nullable(false)->change();
        });

        Schema::table('meals', function (Blueprint $table): void {
            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
        });
    }
};
