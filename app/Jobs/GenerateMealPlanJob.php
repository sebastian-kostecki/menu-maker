<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LogsMealPlan;
use App\Models\MealPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMealPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MealPlan $mealPlan,
        public bool $regenerate = false
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = $this->createLog();

        try {
            // Update status to processing
            $this->mealPlan->update(['status' => 'processing']);

            // TODO: Implement actual meal plan generation logic
            // This is a placeholder implementation that should be replaced with:
            // - MealPlanGeneratorService implementation
            // - Recipe selection logic
            // - Meal creation
            // - PDF generation

            Log::info('Meal plan generation started', [
                'meal_plan_id' => $this->mealPlan->id,
                'regenerate' => $this->regenerate,
            ]);

            // Simulate processing time (remove in production)
            sleep(2);

            // For now, just update status to done
            $this->mealPlan->update([
                'status' => 'done',
                'generation_meta' => [
                    'generated_at' => now(),
                    'regenerate' => $this->regenerate,
                    'version' => 'placeholder',
                ],
            ]);

            $this->finishLog($log, 'done');

            Log::info('Meal plan generation completed', [
                'meal_plan_id' => $this->mealPlan->id,
            ]);
        } catch (\Exception $e) {
            // Update status to error
            $this->mealPlan->update([
                'status' => 'error',
                'generation_meta' => [
                    'error_at' => now(),
                    'error_message' => $e->getMessage(),
                    'regenerate' => $this->regenerate,
                ],
            ]);

            $this->finishLog($log, 'error');

            Log::error('Meal plan generation failed', [
                'meal_plan_id' => $this->mealPlan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Create a log entry for this generation attempt.
     */
    private function createLog(): LogsMealPlan
    {
        return LogsMealPlan::create([
            'meal_plan_id' => $this->mealPlan->id,
            'started_at' => now(),
            'status' => 'processing',
        ]);
    }

    /**
     * Finish the log entry.
     */
    private function finishLog(LogsMealPlan $log, string $status): void
    {
        $log->update([
            'finished_at' => now(),
            'status' => $status,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateMealPlanJob failed permanently', [
            'meal_plan_id' => $this->mealPlan->id,
            'exception' => $exception->getMessage(),
        ]);

        // Update meal plan status to error if not already done
        if ($this->mealPlan->fresh()->status === 'processing') {
            $this->mealPlan->update([
                'status' => 'error',
                'generation_meta' => [
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ],
            ]);
        }
    }
}
