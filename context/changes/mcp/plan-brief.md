# Recipes over MCP (add-recipe-tool) — Plan Brief

> Full plan: `context/changes/mcp/plan.md`

## What & Why

Recipes come mostly from web pages and notes, and typing them into the form field by field is the biggest barrier to building the recipe base. Menu Maker gets an MCP server so that Claude (Claude Code locally, or claude.ai, Desktop and the phone in production) can read a recipe from pasted text or a page and save it to the user's account after the user approves it in the chat.

## Starting Point

`RecipeImporter` already saves a parsed recipe with its ingredients in one transaction and can check for a duplicate name. The form's limits live in `StoreRecipeRequest` and `ValidatesRecipeIngredients`. The app has no machine interface at all: Sanctum is not wired up, there is no Passport, and `laravel/mcp` is only a dev dependency. PRD §4 still excludes URL import.

## Desired End State

The user says "dodaj ten przepis: <url or text>". Claude shows a preview (total kcal, marked as estimated when guessed; units converted to g/kg/ml/l/szt), asks for approval and calls `add-recipe-tool`. The recipe is saved for the signed-in user with the same limits as the form, and Claude returns its link. A name the user already has is refused with the existing recipe's link.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Who extracts | The MCP client (Claude); the app only validates and saves | No server-side AI, no URL fetching, no SSRF guard and no queue job, and #40 and #41 stay separate. |
| Auth and clients | Passport OAuth at `/mcp` plus a local stdio server | The connector works from the phone and claude.ai; this is household-treasurer's proven setup, and Sanctum works only in Claude Code. |
| Approval | Confirm in the chat, then save directly (`requiresUserInteraction`) | One step meets the "under a minute" goal; mistakes are fixed in the normal edit form. |
| Duplicates | Refuse a name the user already has (case-insensitive) and return its link | A retried call never creates a second copy. |
| Units | Strict codes `g/kg/ml/l/szt` in the schema; the conversion table goes in the instructions | The schema rejects bad units and the tested Markdown parser stays untouched. |
| Missing kcal | Multiply per-serving kcal by servings; otherwise estimate and say so in the preview | Every page can be imported in one go, and scaling only needs a reasonable total. |
| Tool scope | `add-recipe-tool` only | The smallest surface that meets the goal. |
| Validation | Extract the form's rules into shared concerns and use them in a new `StructuredRecipeImporter` | The MCP input and the form cannot drift apart. |

## Scope

**In scope:**
- `laravel/mcp` as a production dependency, `RecipeServer`, `LocalRecipeServer` (`MCP_LOCAL_USER`) and `AddRecipeTool`.
- `StructuredRecipeImporter` on top of `RecipeImporter`, with the form's rules shared through concerns.
- Passport 13: the `mcp:use` scope, dynamic registration limited to claude.ai, claude.com and loopback, a Polish Inertia consent page, and the login fix that ends at `/oauth/authorize` with `Inertia::location`.
- JSON 401 on `/mcp`, `dontReport(OAuthServerException)`, limits of 60 calls a minute and 10 OAuth requests a minute, and an `mcp` log channel that never records recipe content.
- PRD §4, the roadmap, a README connect guide and `.env.prod.example` keys.

**Out of scope:** server-side fetching or AI extraction (#40, #41); read, search and update tools; drafts or a review page; server-side unit words; a stored "kcal estimated" flag; idempotency keys; Sanctum; pruning OAuth clients; new E2E or visual specs.

## Architecture / Approach

```
Claude (reads text/page, converts, previews, user approves)
  └─ tools/call add-recipe-tool ──► stdio: LocalRecipeServer (MCP_LOCAL_USER, local env only)
                                    HTTP:  /mcp → LogMcpCall → auth:api → CheckToken(mcp:use) → throttle:mcp → RecipeServer
                                             └─ AddRecipeTool → Gate create → StructuredRecipeImporter
                                                  (shared form rules → duplicate check → ParsedRecipe) → RecipeImporter::import (transaction)
```

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Local MCP server with add-recipe-tool | Claude Code adds recipes through Sail, validated and refused like the form | Extracting the shared rules quietly changes the form's validation; `RecipeRequestValidationTest` guards against that. |
| 2. Remote /mcp behind Passport OAuth | claude.ai and the phone can connect, with the consent page and the login redirect fix | OAuth details: scope enforcement, the Inertia redirect, and the test keys. |
| 3. Docs and rollout | PRD, roadmap and README are in line; the production connector is verified end to end | Passport keys missing from `.env.prod` make `/mcp` answer 500. |

**Prerequisites:** Sail running; access to `.env.prod` on the VPS for the Passport keys; a claude.ai account that can add custom connectors.
**Estimated effort:** ~2–3 sessions across 3 phases (Phase 2 is the largest).

## Open Risks & Assumptions

- Extraction quality depends on the client, and an estimated kcal skews family scaling until the user corrects it in the form.
- Assumes claude.ai's connector keeps using dynamic client registration with the `https://claude.ai/api/mcp/auth_callback` redirect.

## Success Criteria (Summary)

- A recipe from a Polish recipe site lands in Przepisy from the phone in under a minute, after one approval in the chat.
- Every saved recipe passes the form's limits and belongs only to the signed-in user, and a repeat is refused with a link.
- `/mcp` refuses guests (401), tokens without the scope (403) and floods (429), and logs calls without recipe content.
