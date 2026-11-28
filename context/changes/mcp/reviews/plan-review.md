<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Recipes over MCP (add-recipe-tool)

- **Plan**: context/changes/mcp/plan.md
- **Mode**: Deep
- **Date**: 2026-11-28
- **Verdict**: SOUND
- **Findings**: 0 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
12/12 paths ✓, 6/6 symbols ✓, brief↔plan ✓, Progress↔Phase 27/27 ✓

## Findings

### F1 — Duplicate check can't return a link and only half-normalizes whitespace

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §3 (StructuredRecipeImporter, duplicate) and the Desired End State
- **Detail**: The refusal needs the existing Recipe for its URL, but `RecipeImporter::existsFor()` returns a bool (`RecipeImporter.php:22`), and the plan names no lookup method. It also compares `lower(name) = lower(?)`, so only the incoming name is normalized. A stored "Zupa  pomidorowa" (two spaces) would not block "Zupa pomidorowa", contrary to the End State.
- **Fix**: Add `RecipeImporter::findFor(User, string): ?Recipe`, which compares against `lower(regexp_replace(btrim(name), '\s+', ' ', 'g'))`, and make `existsFor()` delegate to it. Add a test with a stored double-space name.
- **Decision**: FIXED

### F2 — calories has no upper bound before the bcmath rounding and the decimal(10,2) column

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §3 (Numbers) and §5 (schema)
- **Detail**: The shared rule `numeric|min:50` has no max, and the column is `decimal(10,2)`. A JSON float from an LLM, such as 1e12, overflows the column, and 1e20 becomes `"1.0E+20"` and throws a ValueError in bcmath. Either way the tool throws instead of returning a validation error.
- **Fix**: Add `max:99999999.99` to calories in `StructuredRecipeImporter` only, set `->max()` in the schema, and add a test that calories `1e12` is refused.
- **Decision**: FIXED

### F3 — The schema's "quantity > 0" can't be expressed and differs from the form's min:0.01

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §5 (schema)
- **Detail**: `Illuminate\JsonSchema` (in laravel/framework) has no `exclusiveMinimum` method, only an inclusive `->min()` (`NumberType.php:25`). The form requires at least 0.01. The other builder calls exist: `array()->items(object([...]))`, `enum()`, `format('uri')`.
- **Fix**: Change quantity to `->min(0.01)`, put the verified builder calls in the plan, and remove the assumption from the brief.
- **Decision**: FIXED

### F4 — Settle the "if needed" Passport details and the deny route

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §2 (tokensCan) and §4 (deny form, props)
- **Detail**:
  - `Mcp::oauthRoutes()` already calls `ensureMcpScope()` → `tokensCan`, and dynamic registration assigns `mcp:use` to the client.
  - The deny route has its own name, `passport.authorizations.deny`.
  - The `authorizationView` closure gets an array with `client`, `user`, `scopes`, `request` and `authToken`.
- **Fix**: Keep `defaultScopes`, and call `tokensCan` only for the Polish description. Point the deny form at `passport.authorizations.deny`, and build the props from the closure's keys.
- **Decision**: FIXED
