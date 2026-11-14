<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            $table->decimal('weight_kg', 5, 1)->nullable()->after('gender');
            $table->decimal('height_cm', 5, 1)->nullable()->after('weight_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            $table->dropColumn(['weight_kg', 'height_cm']);
        });
    }
};
