# MealPlanController – Implementation Plan

## Overview
The `MealPlanController` manages 7-day meal-plan life-cycle for an authenticated user.

Responsibilities:
1. CRUD-like endpoints (index, show, store – generate, update – regenerate, destroy).
2. Delegates heavy business logic (random recipe pick, scaling ingredients, PDF generation, etc.) to dedicated Services & Jobs.
3. Authorises every action with `MealPlanPolicy`.
4. Returns Inertia pages (browser) **and** JSON (tests/API) via Laravel Resources.

Route definition (already registered in `routes/web.php`):
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('meal-plans', MealPlanController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);
});
```

## Request Specification
| Action  | Method & URI                     | FormRequest (DTO)           | Key Fields (validation rules)                                  |
| ------- | -------------------------------- | --------------------------- | -------------------------------------------------------------- |
| index   | `GET /meal-plans`                | —                           | `page` *(int)*, `filter[status]` *(enum)*, `perPage` *(5-100)* |
| show    | `GET /meal-plans/{meal_plan}`    | — *(route-model binding)*   | —                                                              |
| store   | `POST /meal-plans`               | `GenerateMealPlanRequest`   | `start_date:date                                               | after_or_equal:today`, `regenerate:boolean` *(default false)* |
| update  | `PUT /meal-plans/{meal_plan}`    | `RegenerateMealPlanRequest` | `regenerate:boolean                                            | required                                                      | accepted`, `force:boolean | nullable` |
| destroy | `DELETE /meal-plans/{meal_plan}` | —                           | —                                                              |

### DTO / FormRequest
* **GenerateMealPlanRequest** – validates initial generation.
* **RegenerateMealPlanRequest** – allows refreshing an existing plan (**status must be done or error**) and optional `force` flag to bypass 5/h rate-limit.

Both FormRequests:
* Authorise via `MealPlanPolicy` (`create` or `update`).
* Inject `user()` id automatically.

### Serialization
* **MealPlanResource** – single plan with relationships:
  * meals (with recipe & ingredients) ordered by `meal_date`.
  * logs (latest first).
* **MealPlanCollection** – paginated list used in `index`.
* Lazy-loads to keep queries minimal; eager-load counts for performance.

## Response Examples
```jsonc
// 201 Created (store)
{
  "data": {
    "id": 42,
    "start_date": "2025-09-01",
    "end_date": "2025-09-07",
    "status": "pending",
    "links": { "self": "/meal-plans/42" }
  }
}

// 202 Accepted (update – regeneration queued)
{
  "message": "Meal plan regeneration started.",
  "data": { "id": 42, "status": "processing" }
}
```

## Controller Internal Flow
1. **index**
   1. Authorise `viewAny`.
   2. Apply filters/sorts, eager-load minimal fields.
   3. Return Inertia `MealPlans/Index.vue` with `MealPlanCollection` (for tests JSON if request expectsJson()).

2. **show**
   1. Authorise `view`.
   2. Load relations (`meals.recipe`, `logs`).
   3. Return Inertia `MealPlans/Show.vue` with `MealPlanResource`.

3. **store** (generate)
   1. Validate via `GenerateMealPlanRequest`.
   2. Authorise in FormRequest (`create`).
   3. Persist meal_plan record with `status=pending` + calculated `end_date = start_date->addDays(6)`.
   4. Dispatch **GenerateMealPlanJob** which uses **MealPlanGeneratorService**.
   5. Return `MealPlanResource` (HTTP 201) – light payload.

4. **update** (regenerate)
   1. Validate via `RegenerateMealPlanRequest`.
   2. Authorise (`update`).
   3. Guard: cannot regenerate while `status=processing` *(409 Conflict)*.
   4. Update `status` to `pending`, clear `generation_meta`.
   5. Dispatch **GenerateMealPlanJob** with `regenerate=true`.
   6. Respond `202 Accepted`.

5. **destroy**
   1. Authorise `delete` (policy forbids when `status=processing`).
   2. Delete PDF from storage if exists (`Storage::delete`).
   3. Cascade delete meals & logs via database FK `ON DELETE CASCADE` or model events.
   4. Return `204 No Content`.

## Security & Authorisation
* **Authentication** – middleware `auth`, `verified`.
* **Authorisation** – `MealPlanPolicy` actions.
* **Rate-limit** – specific `GenerateMealPlanRequest` (5 per hour) using Laravel’s RateLimiter.
* **CSRF** – default Laravel for web; Sanctum for SPA.
* **Data Integrity** – use FormRequests & database constraints (unique `user_id,start_date`).
* **Job Queue** – run heavy generation async to prevent request timeouts.

## Error Scenarios
| HTTP | When                                  | Message key                     |
| ---- | ------------------------------------- | ------------------------------- |
| 400  | Validation fails                      | `errors` array from FormRequest |
| 401  | Not authenticated                     | —                               |
| 403  | Policy denies (view/update/delete)    | `Forbidden`                     |
| 404  | Meal plan id not found / not owned    | —                               |
| 409  | Cannot regenerate ‑ status processing | `meal_plan_processing`          |
| 422  | Unique `start_date` collision         | `start_date_taken`              |
| 429  | Rate limit exceeded (store/update)    | `Too many requests`             |
| 500  | Service/Job throws unhandled error    | logged via Sentry               |

## Implementation Steps
1. **Model**: ensure relationships & casts in `MealPlan`, implement `scopeOwnedBy($user)`.
2. **FormRequests**: `GenerateMealPlanRequest`, `RegenerateMealPlanRequest` (rules + authorise + rate-limit).
3. **Resources**: `MealPlanResource`, `MealPlanCollection`.
4. **Policy**: verify `delete` rule denies when `status=processing`.
5. **Service**: `MealPlanGeneratorService` (idempotent, no recipe duplicates) & `GenerateMealPlanJob`.
6. **Controller**: scaffold with methods above, handle Inertia/JSON duality.
7. **Routes**: ensure grouped middleware, add rate-limit to `store`, `update` if needed.
8. **Testing**: Feature tests for each endpoint (happy path + each error scenario).
9. **Docs**: update API reference & Inertia page contracts.
10. **Code Style & Static Analysis**: run Pint & Larastan.

