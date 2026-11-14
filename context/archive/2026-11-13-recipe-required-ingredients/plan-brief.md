# Recipe Required Ingredients (S-02) — Plan Brief

> Full plan: `context/changes/recipe-required-ingredients/plan.md`

## What & Why

A recipe must be savable only with every PRD-required field (FR-003), including at least one ingredient with a name, a quantity and a unit from g | kg | ml | l | szt. The shopping list (S-05) and scaling (S-04) are useless without ingredients in consistent units, so this slice has to land before S-05.

## Starting Point

Ingredients are optional on store and update, duplicates are silently dropped (the second row's quantity is lost), and the DB has 9 units (including both `pcs` and `szt`) that exist only through seeders. Ingredient names come from a fixed English list. The "Add" button for a new name is wired to nothing, and the form and messages are in English.

## Desired End State

A Polish recipe form where each ingredient row is a free-text name with suggestions (Polish catalog plus the user's own ingredients), a quantity and one of five units. Saving needs ≥1 complete ingredient. A duplicate name ("Mąka" / " mąka ") shows a Polish error on that row. A name maps to one global ingredient regardless of case and whitespace. Old ingredient-less recipes stay usable until their next edit.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Ingredient name entry | Free text + suggestions, server firstOrCreate on normalized name in the global table | User can type anything, S-05 gets one id per name, and suggestions show only catalog + own names (no cross-user leak). |
| Catalog vs user names | New `ingredients.is_catalog` flag | Lets suggestions tell the seed catalog apart from other users' custom names. |
| Units | Migration leaves exactly g/kg/ml/l/szt; pcs→szt, tsp/tbsp/cup→ml (×5/15/250) | One source of truth in the schema, consistent conversion factors, production needs no seeder. |
| Old recipes without ingredients | Stay as they are and feed generation; editing requires an ingredient | No data loss and one consistent rule. |
| Duplicates (US-005.3) | Validation error on the repeated row | No silent data loss; the user merges them. |
| Polish copy | Form, its components, messages and the seed catalog | User sees Polish everywhere in this slice; Index/Show stay out. |
| Testing | Pest (rules, resolver, scoping, controller) + one Playwright spec | Catches broken-form bugs Pest can't see. |

## Scope

**In scope:** unit/catalog migration, name-based write contract, required ingredients on store/update, per-row duplicate errors, Polish form + messages + catalog, scoped suggestions, Pest tests, one E2E spec with its own seeded user.

**Out of scope:** per-user ingredient table or ingredient endpoint; merging duplicates or unit conversion on save; excluding/badging ingredient-less recipes; translating Recipes Index/Show; cleaning English seed rows from dev DBs.

## Architecture / Approach

The migration makes the DB the source of truth (five units, `is_catalog`, unique `lower(name)` index, Polish catalog). The Form Requests validate `ingredients[]: {name, quantity, unit_id}` with a shared rule set. The controller resolves names to ids (insert-or-ignore, then select on `lower(name)`) and syncs the pivot as today. The form receives `Ingredient::suggestedFor($userId)` as an Inertia prop and renders a text input with suggestions.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Data | 5 units with legacy rows converted, `is_catalog`, unique normalized name, Polish catalog, model helpers | Dev DB name collisions blocking the unique index (handled by a merge step) |
| 2. Backend | Required ingredients by name, per-row duplicates, Polish messages, scoped suggestions + Pest | Existing tests assume optional ingredients and need updating |
| 3. Frontend | Polish form, text field with suggestions, submit gated on a complete ingredient | Row component's local-copy watchers when changing the model shape |
| 4. E2E | Seeded cook user + Playwright create-recipe spec | Needs Sail, the build and a seeder run |

**Prerequisites:** Sail running (`vendor/bin/sail up -d`); none from the roadmap.
**Estimated effort:** ~2–3 sessions across 4 phases.

## Open Risks & Assumptions

- Assumes no production data exists (F-02 not deployed); the unit conversion in `down()` is irreversible.
- English seed ingredients left in dev DBs become non-catalog rows and are suggested only to users whose recipes use them.
- `lower()` normalization doesn't fold diacritics ("maka" ≠ "mąka"): intentional, these are different spellings.

## Success Criteria (Summary)

- A recipe cannot be saved (create or edit) without at least one complete ingredient in one of the five units.
- Typing an existing name in any casing reuses one ingredient; a duplicate row shows a Polish error instead of losing data.
- Pest, Larastan, Pint, build and the new E2E spec are green.
