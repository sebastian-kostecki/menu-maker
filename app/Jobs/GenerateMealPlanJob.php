<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LogsMealPlan;
use App\Models\MealPlan;
use App\Services\IngredientScaling\MealIngredientScaler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

class GenerateMealPlanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Drop the job silently when the plan was deleted before processing.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Seconds the worker lets one attempt run (below the queue's retry_after of 90).
     */
    public int $timeout = 80;

    /**
     * Shown to the user for app failures; the real exception goes to the logs and the error tracker only.
     */
    public const GENERIC_ERROR_MESSAGE = 'Nie udało się wygenerować jadłospisu. Spróbuj ponownie za chwilę.';

    /**
     * Create a new job instance.
     */
    public function __construct(public MealPlan $mealPlan) {}

    /**
     * Never run two scalings of the same plan at once. A duplicate is dropped (not released),
     * because with `--tries=1` a release would end as a reported MaxAttemptsExceededException.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [$this->overlapGuard()];
    }

    /**
     * The overlap middleware, built in one place so failed() can release the same lock key.
     */
    private function overlapGuard(): WithoutOverlapping
    {
        return (new WithoutOverlapping((string) $this->mealPlan->id))->dontRelease()->expireAfter(300);
    }

    /**
     * Execute the job.
     */
    public function handle(MealIngredientScaler $scaler): void
    {
        Context::add('meal_plan_id', $this->mealPlan->id);

        // Claim the approved plan. A re-run (queue:retry, retries) for a plan that has since gone back to draft
        // or finished must not scale it: that would skip approval and the hourly limit. Nothing but this job
        // moves a plan out of `processing`, so the later writes need no guard of their own.
        $claimed = MealPlan::query()
            ->whereKey($this->mealPlan->id)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            Log::warning('Meal plan scaling skipped: the plan is no longer pending', [
                'meal_plan_id' => $this->mealPlan->id,
                'status' => $this->mealPlan->fresh()?->status,
            ]);

            return;
        }

        $this->mealPlan->refresh();

        $log = $this->createLog();

        try {
            Log::info('Meal plan generation started', [
                'meal_plan_id' => $this->mealPlan->id,
            ]);

            $scaling = $scaler->scale($this->mealPlan);

            $this->mealPlan->update([
                'status' => 'done',
                'generation_meta' => [
                    'generated_at' => now(),
                    'version' => 'scaled-v1',
                    'scaling' => $scaling->toArray(),
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
                    'error_message' => self::GENERIC_ERROR_MESSAGE,
                ],
            ]);

            $this->finishLog($log, 'error');

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
        Context::add('meal_plan_id', $this->mealPlan->id);

        // The worker reports exceptions thrown out of handle() itself. Timeouts (a subclass of
        // MaxAttemptsExceededException) and exceeded attempts only reach failed(), so report those here exactly once.
        if ($exception instanceof MaxAttemptsExceededException) {
            report($exception);

            // A killed attempt never reaches the middleware's `finally`, so its lock would outlive it and
            // make the next regeneration of this plan be dropped as an overlap.
            Cache::lock($this->overlapGuard()->getLockKey($this))->forceRelease();
        }

        // Pending covers failures before handle() starts (e.g. a refiner driver that cannot be resolved).
        if (in_array($this->mealPlan->fresh()->status, ['pending', 'processing'], true)) {
            $this->mealPlan->update([
                'status' => 'error',
                'generation_meta' => [
                    'failed_at' => now(),
                    'error_message' => self::GENERIC_ERROR_MESSAGE,
                ],
            ]);
        }
    }
}
