<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenerateMealPlanRequest;
use App\Http\Requests\RegenerateMealPlanRequest;
use App\Http\Resources\MealPlanCollection;
use App\Http\Resources\MealPlanResource;
use App\Http\Resources\ShoppingListResource;
use App\Jobs\GenerateMealPlanJob;
use App\Models\MealPlan;
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
                'meals.recipe.recipeIngredients.ingredient',
                'meals.recipe.recipeIngredients.unit',
                'logs' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
            ]);

            return response()->json(['data' => new MealPlanResource($mealPlan)]);
        }

        // The week view (polled every 3 s while generating) needs the recipe names, kcal and servings;
        // the shopping list also needs the ingredients, so it loads them itself, only for a done plan.
        $mealPlan->load('meals.recipe');

        return Inertia::render('MealPlans/Show', [
            'mealPlan' => (new MealPlanResource($mealPlan))->resolve(),
            'shoppingList' => fn (): ?array => $mealPlan->status === 'done'
                ? (new ShoppingListResource($shoppingListBuilder->forMealPlan($mealPlan)))->resolve()
                : null,
        ]);
    }

    /**
     * Generate a new meal plan.
     */
    public function store(GenerateMealPlanRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        // Calculate end_date (start_date + 6 days for 7-day plan)
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = $startDate->copy()->addDays(6);

        // Create meal plan with pending status. The request already checks the date; a concurrent
        // submit (second tab) can still hit the unique key, which gets the same validation error.
        try {
            // Own transaction (a savepoint when nested), so a violation never poisons an outer one.
            $mealPlan = DB::transaction(fn (): MealPlan => MealPlan::create([
                'user_id' => $validated['user_id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'pending',
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'start_date' => GenerateMealPlanRequest::DUPLICATE_START_DATE_MESSAGE,
            ]);
        }

        // Dispatch generation job
        GenerateMealPlanJob::dispatch($mealPlan, false); // regenerate = false

        if (! $request->expectsJson()) {
            return redirect()->route('meal-plans.show', $mealPlan);
        }

        $data = new MealPlanResource($mealPlan);

        return response()->json([
            'message' => 'Meal plan generation started.',
            'data' => $data,
        ], 201);
    }

    /**
     * Regenerate an existing meal plan.
     */
    public function update(RegenerateMealPlanRequest $request, MealPlan $mealPlan): JsonResponse|RedirectResponse
    {
        // Reset status and clear generation metadata in one conditional update, so two concurrent
        // requests that both passed validation cannot both queue a job.
        $claimed = MealPlan::query()
            ->whereKey($mealPlan->getKey())
            ->whereIn('status', RegenerateMealPlanRequest::REGENERATABLE_STATUSES)
            ->update([
                'status' => 'pending',
                'generation_meta' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'status' => RegenerateMealPlanRequest::NOT_REGENERATABLE_MESSAGE,
            ]);
        }

        $mealPlan->refresh();

        // Dispatch regeneration job
        GenerateMealPlanJob::dispatch($mealPlan, true); // regenerate = true

        if (! $request->expectsJson()) {
            return redirect()->route('meal-plans.show', $mealPlan);
        }

        $data = new MealPlanResource($mealPlan->fresh());

        return response()->json([
            'message' => 'Meal plan regeneration started.',
            'data' => $data,
        ], 202);
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
