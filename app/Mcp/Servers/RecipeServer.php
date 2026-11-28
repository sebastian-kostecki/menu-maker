<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddRecipeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Menu Maker')]
#[Version('1.0.0')]
#[Instructions('Menu Maker saves recipes to the user\'s own recipe book; answer in Polish. Read the recipe yourself from the text the user pastes or from the web page they name. Categories: śniadanie = `breakfast`, obiad = `supper`, kolacja = `dinner`. `calories` is the total for the whole recipe (multiply a per-serving value by the number of servings); when the source has none, estimate it from the ingredients and say in the preview that it is an estimate. Use only the units g, kg, ml, l and szt, converting with: łyżeczka = 5 ml, łyżka = 15 ml, szklanka = 250 ml, dag = 10 g, szczypta = 0.5 g; countable items are in szt. Merge an ingredient that appears twice into one row. Pass the page address as `source_url`. Always show the full preview (name, category, total kcal, servings, ingredients with converted units, steps) and get the user\'s approval before calling `add-recipe-tool`. On a duplicate-name error, offer a different name or the link to the existing recipe.')]
class RecipeServer extends Server
{
    protected array $tools = [
        AddRecipeTool::class,
    ];
}
