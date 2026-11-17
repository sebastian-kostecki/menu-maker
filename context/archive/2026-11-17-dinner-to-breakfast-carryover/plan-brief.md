# Kolacja Carried Over to the Next Day's Śniadanie — Plan Brief

> Full plan: `context/changes/dinner-to-breakfast-carryover/plan.md`

## What & Why

Families often eat the leftovers of a Kolacja for the next breakfast. S-20 (MS-05, PRD US-017) lets the user link the Kolacja of day D to the Śniadanie of day D+1 in the draft. The breakfast shows the Kolacja dish marked "Z kolacji". The amounts and the shopping list then match cooking once.

## Starting Point

S-19 built the "cooked once, eaten twice" link: `meals.feeds_meal_id`, `MealLinkRules::PAIRS` (only Obiad → next-day Obiad) and a JS mirror. The scaler, the shopping list, the PDF recipe pages and the endpoint already work for any pair. Seven user-facing strings and the test fixtures still name only the Obiad pair.

## Desired End State

In a draft, the Śniadanie of day D+1 offers "Z kolacji: <Kolacja of day D>". After it is picked, the Kolacja reads "Też na śniadanie" and the Śniadanie reads "Z kolacji", in the Śniadanie colour. After approval:
- the Kolacja's ingredients are doubled;
- the Śniadanie has none;
- the shopping list holds the dish once in double quantity;
- the PDF shows both markers and one recipe-page use, "(też na śniadanie)".

Changing the Kolacja empties the Śniadanie, and changing the Śniadanie only drops the link. The Obiad pair looks exactly as before.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Amounts | Kolacja scaled for both meals, Śniadanie without ingredients | Same mechanism as S-19; exact because breakfast and dinner both get a 0.30 kcal share. | Roadmap 2026-11-16 |
| Colour and note | Śniadanie colour with "Z kolacji", on screen and in the PDF | Already free: grid and PDF colour by the cell's category. | Roadmap 2026-11-16 / PRD US-017 |
| Where the link is set | Only from the Śniadanie dialog of day D+1 ("Z kolacji: …") | Reuses the S-19 endpoint body, `linkSourceFor()` and the dialog item unchanged. | Plan |
| Wording | Kolacja "Też na śniadanie"; item hint "Gotujemy raz, zostaje na śniadanie"; recipe page "(też na śniadanie)" | Says exactly which meal eats the leftovers, and pairs with "Z kolacji". | Plan |
| Where labels live | Per pair, in `MealLinkRules::PAIRS` and `MEAL_LINK_PAIRS`, looked up by category | No category branches in readers; Obiad strings stay byte-identical. | Plan |
| Shared copy | Generic error, notice and redraw confirm | They now cover both pairs. | Plan |
| Browser coverage | E2E link → approve → unlink, plus a seeded Mon Kolacja → Tue Śniadanie link in the visual draft | Same bar as S-19; proves the cross-category path through the queue. | Plan |

## Scope

**In scope:**
- The `dinner → breakfast, +1` pair with labels.
- PDF markers and the recipe-page label.
- Generic copy.
- Grid badges, the dialog item and the warning per pair.
- The kitchen-sink demo (Fri Kolacja → Sat Śniadanie).
- The seeder link shape, the E2E step and the visual waits and baselines.

**Out of scope:**
- Linking from the Kolacja dialog.
- Other pairs and chains.
- Links across plans.
- Any schema, endpoint, scaler, shopping-list, resource or job change.
- A new dialog baseline.
- Draws that create links.

## Architecture / Approach

The link mechanism grows by data, not by branches. Each pair entry carries its labels, and each side gets two small lookups by category: cooked labels by `from`, carried labels by `to`. A unit test guarantees that each category is the source of at most one pair and the target of at most one. The PDF reads the PHP labels, and the grid and dialog read the JS mirror.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. The pair and its wording (backend) | New pair with labels, PDF labels, generic copy, feature/unit tests | Drifting the Obiad strings that S-19 tests and baselines rely on |
| 2. Badges, dialog item and kitchen sink (UI) | Per-pair texts in the grid and dialog, "Z kolacji" item, redraw copy, demo | PHP/JS label mirrors falling out of sync |
| 3. E2E and visual gate | Seeder link with a target category, E2E link/approve/unlink, refreshed baselines | Strict locators and exact counts in the S-19 specs and seeder test |

**Prerequisites:** S-19 merged into `develop` (done). A worktree environment with its own `.env` ports, `vendor/` and `node_modules/` (see the plan's Environment section).
**Estimated effort:** about 1 session across 3 phases.

## Open Risks & Assumptions

- Assumes the kcal shares of breakfast and dinner stay equal. A test pins this. If they diverge, the ×2 rule must become "factor of the Kolacja + factor of the Śniadanie".
- `meal-plan-draft-swap-dialog` may change if the grid shows through the dialog overlay. The baseline review decides.
- In a Kolacja dialog, the linked recipe reads "jest już: wt." (Tuesday's breakfast). This is accepted as accurate.

## Success Criteria (Summary)

- The user can carry a Kolacja to the next breakfast in the draft and see it clearly marked, in the breakfast colour, on screen and in the PDF.
- The amounts and the shopping list reflect cooking the Kolacja once for both meals.
- The Obiad "Na 2 dni" feature is unchanged, and the whole CI gate (Pest, Larastan, Pint, build, `lint:tokens`, E2E, visual) is green.
