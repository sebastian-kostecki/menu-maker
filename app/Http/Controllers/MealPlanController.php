<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MealCategory;
use App\Http\Requests\ApproveMealPlanRequest;
use App\Http\Requests\GenerateMealPlanRequest;
use App\Http\Requests\RegenerateMealPlanRequest;
use App\Http\Requests\ReturnMealPlanToDraftRequest;
use App\Http\Resources\MealPlanCollection;
use App\Http\Resources\MealPlanResource;
use App\Http\Resources\ShoppingListResource;
use App\Jobs\GenerateMealPlanJob;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Services\InsufficientRecipesException;
use App\Services\MealPlanGenerator;
use App\Services\MealPlanPdf\MealPlanPdfExporter;
use App\Services\ShoppingList\ShoppingListBuilder;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MealPlanController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MealPlan::class, 'meal_plan');
    }

    /**
     * Display a listing of meal plans with filtering and pagination.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $query = MealPlan::ownedBy(Auth::user())
            ->withCount(['meals', 'logs']);

        // Apply status filter
        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSortFields = ['start_date', 'end_date', 'status', 'created_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        // Pagination
        $perPage = min(max((int) $request->get('perPage', 15), 5), 100);
        $mealPlans = $query->paginate($perPage);

        $data = new MealPlanCollection($mealPlans);

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('MealPlans/Index', [
            'mealPlans' => $data,
            'filters' => $request->only(['filter.status', 'sort', 'direction', 'perPage']),
            'statuses' => [
                ['value' => 'draft', 'label' => 'Szkic'],
                ['value' => 'pending', 'label' => 'Oczekuje'],
                ['value' => 'processing', 'label' => 'Generowanie'],
                ['value' => 'done', 'label' => 'Gotowy'],
                ['value' => 'error', 'label' => 'Błąd'],
            ],
        ]);
    }

    /**
     * Display the specified meal plan.
     */
    public function show(MealPlan $mealPlan, ShoppingListBuilder $shoppingListBuilder): InertiaResponse|JsonResponse
    {
        if (request()->expectsJson()) {
            $mealPlan->load([
                'meals.recipe',
                'meals.ingredients.ingredient',
                'meals.ingredients.unit',
                'logs' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
            ]);

            return response()->json(['data' => new MealPlanResource($mealPlan)]);
        }

        // The week view (polled every 3 s while generating) needs the recipe names, kcal and servings
        // plus each meal's scaled ingredients (empty while the plan is generating).
        $mealPlan->load('meals.recipe', 'meals.ingredients.ingredient', 'meals.ingredients.unit');

        return Inertia::render('MealPlans/Show', [
            'mealPlan' => (new MealPlanResource($mealPlan))->resolve(),
            'shoppingList' => fn (): ?array => $mealPlan->status === 'done'
                ? (new ShoppingListResource($shoppingListBuilder->forMealPlan($mealPlan)))->resolve()
                : null,
            'recipeOptions' => fn (): ?array => $mealPlan->status === 'draft'
                ? $this->recipeOptions($mealPlan)
                : null,
        ]);
    }

    /**
     * The owner's non-deleted recipes per meal category, for the swap dialog.
     *
     * @return array<string, list<array{id: int, name: string, calories: string|null}>>
     */
    private function recipeOptions(MealPlan $mealPlan): array
    {
        $options = [];

        foreach (MealCategory::cases() as $category) {
            $options[$category->value] = [];
        }

        Recipe::query()
            ->forUser($mealPlan->user_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'category', 'calories'])
            ->each(function (Recipe $recipe) use (&$options): void {
                $options[$recipe->category][] = [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'calories' => (string) $recipe->calories,
                ];
            });

        return $options;
    }

    /**
     * Create a meal plan draft: the week's recipes are drawn right away (DB only, no AI).
     */
    public function store(GenerateMealPlanRequest $request, MealPlanGenerator $generator): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        // Calculate end_date (start_date + 6 days for 7-day plan)
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = $startDate->copy()->addDays(6);

        // The request already checks the date and the recipe count; a concurrent submit (second tab) can still
        // hit the unique key, and recipes deleted in between can still cause a shortfall. Both roll the plan back.
        try {
            // Own transaction (a savepoint when nested), so a violation never poisons an outer one.
            $mealPlan = DB::transaction(function () use ($validated, $startDate, $endDate, $generator): MealPlan {
                $mealPlan = MealPlan::create([
                    'user_id' => $validated['user_id'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'draft',
                ]);

                $generator->generate($mealPlan);

                return $mealPlan;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'start_date' => GenerateMealPlanRequest::DUPLICATE_START_DATE_MESSAGE,
            ]);
        } catch (InsufficientRecipesException $e) {
            throw ValidationException::withMessages([
                'recipes' => $e->getMessage(),
            ]);
        }

        if (! $request->expectsJson()) {
            return redirect()->route('meal-plans.show', $mealPlan);
        }

        $mealPlan->load('meals.recipe');

        return response()->json([
            'message' => 'Meal plan draft created.',
            'data' => new MealPlanResource($mealPlan),
        ], 201);
    }

    /**
     * Redraw the week of a draft ("Losuj ponownie"). Only drafts can be redrawn.
     */
    public function update(RegenerateMealPlanRequest $request, MealPlan $mealPlan, MealPlanGenerator $generator): JsonResponse|RedirectResponse
    {
        try {
            DB::transaction(function () use ($mealPlan, $generator): void {
                // Lock the plan row, so an approval waits and then scales the new meals.
                $locked = MealPlan::query()->whereKey($mealPlan->getKey())->lockForUpdate()->first();

                if ($locked === null || ! in_array($locked->status, RegenerateMealPlanRequest::REGENERATABLE_STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'status' => RegenerateMealPlanRequest::NOT_REGENERATABLE_MESSAGE,
                    ]);
                }

                $generator->generate($locked);
            });
        } catch (InsufficientRecipesException $e) {
            throw ValidationException::withMessages([
                'recipes' => $e->getMessage(),
            ]);
        }

        if (! $request->expectsJson()) {
            return redirect()->route('meal-plans.show', $mealPlan);
        }

        $mealPlan->refresh()->load('meals.recipe');

        return response()->json([
            'message' => 'Meal plan redrawn.',
            'data' => new MealPlanResource($mealPlan),
        ]);
    }

    /**
     * Approve a draft: queue the scaling (the only path into the AI call).
     */
    public function approve(ApproveMealPlanRequest $request, MealPlan $mealPlan): JsonResponse|RedirectResponse
    {
        // One conditional update, so two concurrent approvals cannot both queue a job.
        $claimed = MealPlan::query()
            ->whereKey($mealPlan->getKey())
            ->where('status', 'draft')
            ->update([
                'status' => 'pending',
                'generation_meta' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'status' => ApproveMealPlanRequest::NOT_DRAFT_MESSAGE,
            ]);
        }

        $mealPlan->refresh();

        GenerateMealPlanJob::dispatch($mealPlan);

        if (! $request->expectsJson()) {
            return redirect()->route('meal-plans.show', $mealPlan);
        }

        return response()->json([
            'message' => 'Meal plan approved; scaling started.',
            'data' => new MealPlanResource($mealPlan),
        ], 202);
    }

    /**
     * Reopen a done or failed plan as a draft with the same recipes.
     */
    public function backToDraft(ReturnMealPlanToDraftRequest $request, MealPlan $mealPlan): JsonResponse|RedirectResponse
    {
        $claimed = DB::transaction(function () use ($mealPlan): int {
            $claimed = MealPlan::query()
                ->whereKey($mealPlan->getKey())
                ->whereIn('status', ReturnMealPlanToDraftRequest::RETURNABLE_STATUSES)
                ->update([
                    'status' => 'draft',
                    'generation_meta' => null,
                    'updated_at' => now(),
                ]);

            if ($claimed > 0) {
                MealIngredient::query()
                    ->whereIn('meal_id', $mealPlan->meals()->select('id'))
                    ->delete();
            }

            return $claimed;
        });

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'status' => ReturnMealPlanToDraftRequest::NOT_RETURNABLE_MESSAGE,
            ]);
        }

        if (! $request->expectsJson()) {
            return redirect()->route('meal-plans.show', $mealPlan);
        }

        $mealPlan->refresh()->load('meals.recipe');

        return response()->json([
            'message' => 'Meal plan returned to draft.',
            'data' => new MealPlanResource($mealPlan),
        ]);
    }

    /**
     * Render the PDF (plan table, then shopping list) of a finished meal plan and show it inline.
     */
    public function downloadPdf(MealPlan $mealPlan, MealPlanPdfExporter $exporter): Response
    {
        // Authorize first, so a non-owner cannot tell from a 404 whether the plan is ready.
        $this->authorize('downloadPdf', $mealPlan);

        if ($mealPlan->status !== 'done') {
            abort(404, 'PDF jest dostępny tylko dla gotowego jadłospisu.');
        }

        return response($exporter->render($mealPlan), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$exporter->filename($mealPlan).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Remove the specified meal plan from storage.
     */
    public function destroy(Request $request, MealPlan $mealPlan): Response|RedirectResponse
    {
        // Delete meal plan (cascade delete handles meals & logs via DB constraints)
        $mealPlan->delete();

        if (! $request->expectsJson()) {
            return redirect()->route('meal-plans.index');
        }

        return response()->noContent();
    }
}
