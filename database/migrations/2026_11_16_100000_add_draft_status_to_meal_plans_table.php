<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allow the `draft` status. The column default stays `pending`.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE meal_plans DROP CONSTRAINT IF EXISTS meal_plans_status_check');
        DB::statement("ALTER TABLE meal_plans ADD CONSTRAINT meal_plans_status_check CHECK (status::text IN ('draft', 'pending', 'processing', 'done', 'error'))");
    }

    /**
     * Restore the four-value constraint, moving drafts to `error` first.
     */
    public function down(): void
    {
        DB::statement("UPDATE meal_plans SET status = 'error' WHERE status = 'draft'");
        DB::statement('ALTER TABLE meal_plans DROP CONSTRAINT IF EXISTS meal_plans_status_check');
        DB::statement("ALTER TABLE meal_plans ADD CONSTRAINT meal_plans_status_check CHECK (status::text IN ('pending', 'processing', 'done', 'error'))");
    }
};
