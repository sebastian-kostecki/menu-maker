<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A meal can point to the one meal that eats it ("cooked once, eaten twice").
     */
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            $table->foreignId('feeds_meal_id')->nullable()->unique()->constrained('meals')->nullOnDelete();
        });
    }

    /**
     * Drop the link: carried-over meals become ordinary meals with the same recipe.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            $table->dropForeign(['feeds_meal_id']);
            $table->dropUnique(['feeds_meal_id']);
            $table->dropColumn('feeds_meal_id');
        });
    }
};
