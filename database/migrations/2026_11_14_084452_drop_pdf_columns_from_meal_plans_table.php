<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PDF is rendered on request, so nothing stores a path or size.
     */
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table): void {
            $table->dropColumn(['pdf_path', 'pdf_size']);
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table): void {
            $table->string('pdf_path')->nullable();
            $table->unsignedBigInteger('pdf_size')->nullable();
        });
    }
};
