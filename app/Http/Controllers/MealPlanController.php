<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenerateMealPlanRequest;
use App\Http\Requests\RegenerateMealPlanRequest;
use App\Http\Resources\MealPlanCollection;
use App\Http\Resources\MealPlanResource;
use App\Jobs\GenerateMealPlanJob;
use App\Models\MealPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'processing', 'label' => 'Processing'],
                ['value' => 'done', 'label' => 'Done'],
                ['value' => 'error', 'label' => 'Error'],
            ],
        ]);
    }

    /**
     * Display the specified meal plan.
     */
    public function show(MealPlan $mealPlan): InertiaResponse|JsonResponse
    {
        $mealPlan->load([
            'meals.recipe.recipeIngredients.ingredient',
            'meals.recipe.recipeIngredients.unit',
            'logs' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
        ]);

        $data = new MealPlanResource($mealPlan);

        if (request()->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return Inertia::render('MealPlans/Show', [
            'mealPlan' => $data,
        ]);
    }

    /**
     * Generate a new meal plan.
     */
    public function store(GenerateMealPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Calculate end_date (start_date + 6 days for 7-day plan)
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = $startDate->copy()->addDays(6);

        // Create meal plan with pending status
        $mealPlan = MealPlan::create([
            'user_id' => $validated['user_id'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
        ]);

        // Dispatch generation job
        GenerateMealPlanJob::dispatch($mealPlan, false); // regenerate = false

        $data = new MealPlanResource($mealPlan);

        return response()->json([
            'message' => 'Meal plan generation started.',
            'data' => $data,
        ], 201);
    }

    /**
     * Regenerate an existing meal plan.
     */
    public function update(RegenerateMealPlanRequest $request, MealPlan $mealPlan): JsonResponse
    {
        // Guard: Check if already processing (should be caught by policy/validation but double-check)
        if ($mealPlan->status === 'processing') {
            return response()->json([
                'message' => 'Cannot regenerate meal plan while it is being processed.',
                'error' => 'meal_plan_processing',
            ], 409);
        }

        // Reset status and clear generation metadata
        $mealPlan->update([
            'status' => 'pending',
            'generation_meta' => null,
        ]);

        // Dispatch regeneration job
        GenerateMealPlanJob::dispatch($mealPlan, true); // regenerate = true

        $data = new MealPlanResource($mealPlan->fresh());

        return response()->json([
            'message' => 'Meal plan regeneration started.',
            'data' => $data,
        ], 202);
    }

    /**
     * Download the PDF file for the specified meal plan.
     */
    public function downloadPdf(MealPlan $mealPlan): Response
    {
        // Check if meal plan is in done status
        if ($mealPlan->status !== 'done') {
            abort(404, 'PDF is not available for this meal plan.');
        }

        // Check if PDF file exists
        if (!$mealPlan->pdf_path || !Storage::exists($mealPlan->pdf_path)) {
            abort(404, 'PDF file not found.');
        }

        // Get the file content
        $fileContent = Storage::get($mealPlan->pdf_path);
        $fileName = sprintf(
            'meal-plan-%s-%s.pdf',
            $mealPlan->start_date,
            $mealPlan->end_date
        );

        return response($fileContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    /**
     * Remove the specified meal plan from storage.
     */
    public function destroy(MealPlan $mealPlan): Response
    {
        // Delete PDF file if exists
        if ($mealPlan->pdf_path && Storage::exists($mealPlan->pdf_path)) {
            Storage::delete($mealPlan->pdf_path);
        }

        // Delete meal plan (cascade delete handles meals & logs via DB constraints)
        $mealPlan->delete();

        return response()->noContent();
    }
}
