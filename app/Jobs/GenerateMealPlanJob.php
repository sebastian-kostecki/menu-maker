<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LogsMealPlan;
use App\Models\MealPlan;
use App\Services\IngredientScaling\MealIngredientScaler;
use App\Services\InsufficientRecipesException;
use App\Services\MealPlanGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
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
    public function __construct(
        public MealPlan $mealPlan,
        public bool $regenerate = false
    ) {
        //
    }

    /**
     * Never run two generations of the same plan at once. A duplicate is dropped (not released),
     * because with `--tries=1` a release would end as a reported MaxAttemptsExceededException.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->mealPlan->id))->dontRelease()->expireAfter(300),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(MealPlanGenerator $generator, MealIngredientScaler $scaler): void
    {
        Context::add('meal_plan_id', $this->mealPlan->id);

        $log = $this->createLog();

        try {
            // Update status to processing
            $this->mealPlan->update(['status' => 'processing']);

            Log::info('Meal plan generation started', [
                'meal_plan_id' => $this->mealPlan->id,
                'regenerate' => $this->regenerate,
            ]);

            $generator->generate($this->mealPlan);

            $scaling = $scaler->scale($this->mealPlan);

            $this->mealPlan->update([
                'status' => 'done',
                'generation_meta' => [
                    'generated_at' => now(),
                    'regenerate' => $this->regenerate,
                    'version' => 'scaled-v1',
                    'scaling' => $scaling->toArray(),
                ],
            ]);

            $this->finishLog($log, 'done');

            Log::info('Meal plan generation completed', [
                'meal_plan_id' => $this->mealPlan->id,
            ]);
        } catch (InsufficientRecipesException $e) {
            // User data shortfall, not an app fault: record it on the plan and do not report.
            $this->mealPlan->update([
                'status' => 'error',
                'generation_meta' => [
                    'error_at' => now(),
                    'error_message' => $e->getMessage(),
                    'missing' => $e->missing,
                    'regenerate' => $this->regenerate,
                ],
            ]);

            $this->finishLog($log, 'error');
        } catch (\Exception $e) {
            // Update status to error
            $this->mealPlan->update([
                'status' => 'error',
                'generation_meta' => [
                    'error_at' => now(),
                    'error_message' => self::GENERIC_ERROR_MESSAGE,
                    'regenerate' => $this->regenerate,
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
        }

        // Update meal plan status to error if not already done
        if ($this->mealPlan->fresh()->status === 'processing') {
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
