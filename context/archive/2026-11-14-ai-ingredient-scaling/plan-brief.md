# AI Ingredient Scaling (S-04) — Plan Brief

> Full plan: `context/changes/ai-ingredient-scaling/plan.md`

## What & Why

Every meal in a generated week gets ingredient amounts sized to the family (US-012, FR-005):
1. Code computes each person's daily kcal from the Polish norms.
2. Code splits it 30/40/30 across Śniadanie/Obiad/Kolacja and scales each recipe by (family kcal ÷ recipe kcal).
3. AI only rounds the result to practical amounts.

Without this, the list and the PDF show recipe amounts as written, so a big family under-buys and a small one wastes food. This is the problem the product exists to solve.

## Starting Point

A done plan has 21 `meals`, and those meals store no quantities. The shopping list (S-05), the PDF (S-06) and the resource all read `recipe_ingredients` directly. The family profile holds only name, birth date and sex. There is no AI client, and the worker kills a job after 60 s.

## Desired End State

- **Plan view.** Each grid cell has a "Składniki" toggle with that meal's scaled amounts ("Mąka — 200 g", "Jajka — 2 szt."). The shopping list and the PDF show the same numbers.
- **Snapshot.** A plan keeps its amounts until "Regeneruj".
- **Degradation is explained.** An amber notice covers these cases:
  - no family yet: amounts as written;
  - AI failed: amounts computed by code;
  - 0-kcal recipes;
  - adults missing weight or height;
  - a plan generated before this change.
- **Profile.** The family form asks adults (19+) for weight and height.
- **Configuration.** `AI_SCALING_DRIVER` switches between OpenRouter, the Anthropic API and a deterministic fake for CI.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Energy table | NIZP-PZH 2020, "umiarkowana" values (adults PAL 1.75) | A Polish source with every value checked against the primary PDF and one consistent activity label. |
| Adult body data | Store weight and height. Clamp weight to BMI 18.5–24.9 for the height, then interpolate between weight rows. | Follows the norms' "normal weight for height" rule without jumps between rows. |
| Rows above the source | Men 90–100 kg, women 80–100 kg from a linear fit per age band, rounded to 50 kcal, marked *derived* | Tall adults' normal weight reaches about 100 kg, and the fit uses only published numbers. |
| Body data required | For age 19+; existing adults without data use 75/65 kg and are named in a notice | Accurate for new data without blocking existing users. |
| "kcal przepisu" | Whole-recipe total; label becomes "Kaloryczność całkowita" | Matches PRD §3.3 and db-plan literally. |
| No family | Amounts as written, no AI call, plus a notice linking to the profile | Generation never gets a new blocker. |
| 0-kcal recipe | `gt:0` for new edits; old ones are left as written and named | No division by zero, and the user knows why. |
| Storage | `meal_ingredients` snapshot (recipe / computed / final amount plus `source`), with an `as_written` backfill | One source for the grid, list and PDF, and it matches "latest generated version". |
| Display | Native `<details>` per grid cell | Shows amounts on screen (US-012.2) with no new component. |
| AI call | One batch per plan; 3 attempts ≤20 s each; job `$timeout = 80` | Fits a 60 s worker under `--tries=1`; one success or fallback state per plan. |
| Guardrails | Quantity only, same unit; `szt.` whole (floor or ceil); others within ±25%; an invalid item falls back to computed | The AI cannot break units, list groups or totals. |
| AI drivers | OpenRouter (Http) and Anthropic (`anthropic-ai/sdk`, `claude-opus-5-5`, effort `low`) plus a fake | Switch provider by env var. A Claude subscription can't back a deployed app. |
| Failure reporting | One `report(AiRefinementFailedException)` after the last attempt; no family data | F-01: one failure gives one entry, mailed at most hourly. |
| Testing | `Http::fake` / mocked gateway in Pest; fake driver plus a pinned family and kcal in E2E | No network or secrets in CI, and E2E totals are deterministic. |

## Scope

**In scope:**
- body fields and validation on the family profile, plus `FamilyMemberPolicyTest`;
- the norms table and the calculator;
- `meal_ingredients` with a backfill;
- the scaler job step, the refiner drivers and the guard;
- the consumers switching to the snapshot;
- notices on screen and in the PDF;
- the per-cell ingredient list;
- the recipe kcal rule and label;
- seeder, E2E, CI env and doc updates.

**Out of scope:**
- the 2024 norms;
- per-person activity, pregnancy or breastfeeding;
- the AI changing units or ingredients;
- live recalculation after edits;
- editing amounts;
- partial per-meal AI success;
- PRD edits;
- Claude subscription auth.

## Architecture / Approach

`GenerateMealPlanJob` runs `generate()` (the existing draw, in a transaction), then `MealIngredientScaler::scale()`:
1. `EnergyNorms` gives each member's daily kcal.
2. `ScalingCalculator` gives each meal's factor and computed amounts.
3. `IngredientRefiner` (OpenRouter, Anthropic or fake) proposes rounded amounts.
4. `RefinementGuard` checks each proposal.
5. The rows are written, and `generation_meta.scaling` is set.

`ShoppingListBuilder`, `MealPlanPdfExporter` and `MealPlanResource` read `meal.ingredients`. `ScalingNotices` turns the meta into Polish sentences for the view and the PDF.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Scaling rules and family body data | Norms table, calculator, weight/height fields, calories `gt:0`, policy test | Transcription errors in the table (a manual check against the PDF) |
| 2. Per-meal quantities | `meal_ingredients` and backfill, code-only scaling in the job, consumers switched | Many existing tests build plans without snapshot rows |
| 3. AI refinement | Refiner interface, OpenRouter, Anthropic and fake drivers, guard, retries, single report | The latency budget inside the 80 s job; SDK call shapes |
| 4. UI and notices | `<details>` per cell, notice box, PDF notice, the "cały przepis" label | Poll payload growth; notice wording |
| 5. E2E and docs | Pinned family and kcal, fake driver in CI, scaled totals asserted, specs and CLAUDE.md updated | Needs Sail, a build, the seed and the queue service |

**Prerequisites:**
- Sail up with the `queue` service.
- Network access for `composer require anthropic-ai/sdk`.
- For the manual checks in Phase 3: an OpenRouter key and model, and an Anthropic Console API key.

**Estimated effort:** about 3–4 sessions across 5 phases.

## Open Risks & Assumptions

- **Table values.** The norms values come from text extracted from the PDF; a visual check is a Phase 1 manual step. Rows above 85/75 kg are an app extrapolation, not printed norms.
- **Latency.** The worst case is about 61 s of AI time. It stays under the 80 s job timeout, but the PRD's average under 1 min relies on most calls succeeding on the first attempt.
- **Behaviour change.** Plans become snapshots: a recipe edit no longer changes an existing plan's list or PDF until regeneration. This is a deliberate change from S-05/S-06's "live" reading.
- **Old plans.** Plans created before deploy show recipe amounts plus a "zregeneruj" notice. They are not rescaled automatically.

## Success Criteria (Summary)

- A family's plan shows amounts sized to them in the grid, the list and the PDF, and they match each other.
- When there's no family, or the AI is down, the user still gets a plan, sees why its amounts are not refined, and an AI outage shows up as one error entry.
- CI stays green with no network AI calls; E2E proves the scaled totals ("42 szt.", "4,2 kg", "2,1 l") after generating and after regenerating.
