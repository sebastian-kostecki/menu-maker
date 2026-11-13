<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NORMALIZED = "trim(regexp_replace(name, '\\s+', ' ', 'g'))";

    /**
     * Polish ingredient catalog shown to every user.
     *
     * @var list<string>
     */
    private const CATALOG = [
        'Mąka', 'Cukier', 'Sól', 'Pieprz czarny', 'Oliwa z oliwek', 'Masło', 'Jajka', 'Mleko',
        'Pierś z kurczaka', 'Wołowina', 'Wieprzowina', 'Łosoś', 'Pomidory', 'Cebula', 'Czosnek',
        'Ziemniaki', 'Marchew', 'Brokuł', 'Papryka', 'Szpinak', 'Ryż', 'Makaron', 'Chleb',
        'Ser żółty', 'Jogurt naturalny', 'Cytryna', 'Bazylia', 'Oregano', 'Tymianek',
        'Papryka słodka', 'Kmin rzymski', 'Imbir', 'Miód', 'Sos sojowy', 'Ocet',
        'Bulion drobiowy', 'Bulion warzywny', 'Mleko kokosowe', 'Śmietana 30%',
    ];

    /**
     * Irreversible data conversion: units are reduced to the five PRD units
     * (pcs becomes szt; tsp, tbsp and cup become ml) and ingredient names are normalized.
     */
    public function up(): void
    {
        $this->normalizeUnits();

        Schema::table('ingredients', function (Blueprint $table): void {
            $table->boolean('is_catalog')->default(false);
        });

        $this->normalizeIngredientNames();

        DB::statement('CREATE UNIQUE INDEX ingredients_name_normalized_unique ON ingredients (lower(name))');

        $this->seedCatalog();
    }

    /**
     * Drops the index and column only; unit and name conversion is not reverted.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ingredients_name_normalized_unique');

        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropColumn('is_catalog');
        });
    }

    private function normalizeUnits(): void
    {
        $now = now();

        DB::table('units')->upsert([
            ['code' => 'g', 'conversion_factor_to_base' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'kg', 'conversion_factor_to_base' => 1000, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ml', 'conversion_factor_to_base' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'l', 'conversion_factor_to_base' => 1000, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'szt', 'conversion_factor_to_base' => 1, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['conversion_factor_to_base', 'updated_at']);

        $ids = DB::table('units')->pluck('id', 'code');

        if (isset($ids['pcs'])) {
            DB::table('recipe_ingredients')->where('unit_id', $ids['pcs'])->update(['unit_id' => $ids['szt']]);
        }

        foreach (['tsp' => 5, 'tbsp' => 15, 'cup' => 250] as $code => $factor) {
            if (! isset($ids[$code])) {
                continue;
            }

            DB::table('recipe_ingredients')
                ->where('unit_id', $ids[$code])
                ->update([
                    'unit_id' => $ids['ml'],
                    'quantity' => DB::raw('quantity * '.$factor),
                ]);
        }

        DB::table('units')->whereNotIn('code', ['g', 'kg', 'ml', 'l', 'szt'])->delete();
    }

    private function normalizeIngredientNames(): void
    {
        $normalized = self::NORMALIZED;

        $groups = DB::table('ingredients')
            ->selectRaw("lower({$normalized}) as key, min(id) as keep_id")
            ->groupByRaw("lower({$normalized})")
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $duplicateIds = DB::table('ingredients')
                ->whereRaw("lower({$normalized}) = ?", [$group->key])
                ->where('id', '!=', $group->keep_id)
                ->pluck('id');

            DB::table('recipe_ingredients')
                ->whereIn('ingredient_id', $duplicateIds)
                ->whereIn('recipe_id', function ($query) use ($group): void {
                    $query->select('recipe_id')->from('recipe_ingredients')->where('ingredient_id', $group->keep_id);
                })
                ->delete();

            // Several duplicates may share a recipe: keep the first row per recipe.
            DB::statement(
                'DELETE FROM recipe_ingredients r USING recipe_ingredients o
                 WHERE r.recipe_id = o.recipe_id AND r.ingredient_id IN ('.$duplicateIds->implode(',').')
                 AND o.ingredient_id IN ('.$duplicateIds->implode(',').') AND r.id > o.id',
            );

            DB::table('recipe_ingredients')
                ->whereIn('ingredient_id', $duplicateIds)
                ->update(['ingredient_id' => $group->keep_id]);

            DB::table('ingredients')->whereIn('id', $duplicateIds)->delete();
        }

        DB::statement("UPDATE ingredients SET name = {$normalized} WHERE name <> {$normalized}");
    }

    private function seedCatalog(): void
    {
        $now = now();

        foreach (self::CATALOG as $name) {
            DB::statement(
                'INSERT INTO ingredients (name, is_catalog, created_at, updated_at) VALUES (?, true, ?, ?)
                 ON CONFLICT ((lower(name))) DO UPDATE SET is_catalog = true, updated_at = EXCLUDED.updated_at',
                [$name, $now, $now],
            );
        }
    }
};
