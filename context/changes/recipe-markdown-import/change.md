---
change_id: recipe-markdown-import
title: Import recipes from Markdown files (recipes:import)
status: in-progress
created: 2026-11-15
updated: 2026-11-15
---

## Notes

Source data: the owner's Obsidian-style recipe notes (`D:\Menu Maker Project\Przepisy`, 36 files). Import-ready copies with estimated `kalorie`/`porcje` live in `D:\Menu Maker Project\Przepisy-import` (33 files, plus `RAPORT.txt` with every estimate and data fix). The recipe data is not committed: the repository is public, and production reads the files from the mounted `storage/` directory.

Decisions taken without a plan (proposal to review before merge):

- Categories: śniadanie → `breakfast`, obiad → `supper`, kolacja → `dinner` (as in `resources/js/lib/mealCategories.js`).
- Units: łyżeczka 5 ml, łyżka 15 ml, szklanka 250 ml (as the 2026_11_13 unit migration), szczypta 0.5 g, a bare number is `szt`.
- `źródło` is appended to the instructions as "Źródło: …"; `typ` is ignored (no column).
- Placeholder steps ("Według Lidlomixa") are imported as written, with a warning.
- Calories and servings are estimates from the ingredients (kcal/100 g table, piece weights); servings target ~450 kcal (śniadanie), ~650 kcal (obiad), ~500 kcal (kolacja) per serving.

Open: only 6 kolacja recipes are importable, and `MealPlanGenerator` needs 7 per category.

Production steps: `import-runbook.md`.
