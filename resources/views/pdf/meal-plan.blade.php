<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        /*
         * Mirror of resources/css/app.css :root (dompdf cannot read CSS variables). Keep in sync;
         * guarded by tests/Unit/Services/MealPlanPdfPaletteTest.php. Hexes are the sRGB-clipped
         * values from context/foundation/design-system.md 10.2.
         * --foreground  oklch(0.234 0.036 288.1)  #1d1b2e
         * --muted  oklch(0.952 0.017 76.1)  #f6eee3
         * --muted-foreground  oklch(0.48 0.032 296.6)  #5f5a6e
         * --border  oklch(0.9 0.026 76.8)  #e8dccb
         * --warning  oklch(0.932 0.09 90.9)  #ffe7a3
         * --warning-foreground  oklch(0.344 0.071 84.0)  #4a3500
         * --meal-breakfast  oklch(0.885 0.129 86.1)  #ffd36e
         * --meal-breakfast-foreground  oklch(0.297 0.061 84.8)  #3b2a00
         * --meal-supper  oklch(0.755 0.151 38.3)  #ff8a65
         * --meal-supper-foreground  oklch(0.245 0.067 37.6)  #3a1206
         * --meal-dinner  oklch(0.697 0.136 279.0)  #8c93f0
         * --meal-dinner-foreground  oklch(0.225 0.073 276.5)  #14163d
         */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1d1b2e; }
        h1 { font-size: 15pt; font-weight: bold; margin: 0 0 10px; }
        h2 { font-size: 12pt; font-weight: bold; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e8dccb; padding: 4px 5px; vertical-align: top; text-align: left; }
        th { background: #f6eee3; font-size: 9pt; font-weight: bold; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        tr.day-start td { border-top: 2px solid #5f5a6e; }
        .day { font-size: 9pt; font-weight: bold; }
        td.day-continued { border-top: none; }
        td.category { font-size: 8pt; font-weight: bold; }
        td.meal-breakfast { background: #ffd36e; color: #3b2a00; }
        td.meal-supper { background: #ff8a65; color: #3a1206; }
        td.meal-dinner { background: #8c93f0; color: #14163d; }
        td.quantity { text-align: right; }
        .meta, .note { font-size: 8pt; color: #5f5a6e; }
        .note { margin-top: 10px; }
        .notices { margin: 0 0 10px; padding: 6px 8px; border: 1px solid #e8dccb; border-radius: 4px; background: #ffe7a3; color: #4a3500; }
        .notices p { margin: 0 0 3px; }
    </style>
</head>
<body>
    <h1>{{ $document->title }}</h1>

    @if (count($document->notices) > 0)
        <div class="notices">
            @foreach ($document->notices as $notice)
                <p>{{ $notice }}</p>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 12%">Dzień</th>
                <th style="width: 10%">Posiłek</th>
                <th style="width: 30%">Nazwa</th>
                <th style="width: 48%">Składniki</th>
            </tr>
        </thead>
        @foreach ($document->days as $day)
            <tbody>
                @foreach ($day->meals as $meal)
                    <tr @class(['day-start' => $loop->first])>
                        @if ($loop->first)
                            <td class="day">{{ $day->label }}</td>
                        @else
                            <td class="day-continued"></td>
                        @endif
                        <td class="category meal-{{ $meal->categoryValue }}">{{ $meal->category }}</td>
                        @if ($meal->empty)
                            <td><span class="meta">{{ $meal->recipeName }}</span></td>
                            <td></td>
                        @elseif ($meal->carriedOver)
                            <td>{{ $meal->recipeName }}@if ($meal->recipeDeleted) <span class="meta">(usunięty)</span>@endif <span class="meta">{{ $meal->marker }}</span></td>
                            <td></td>
                        @else
                            <td>{{ $meal->recipeName }}@if ($meal->recipeDeleted) <span class="meta">(usunięty)</span>@endif @if ($meal->marker) <span class="meta">{{ $meal->marker }}</span>@endif</td>
                            <td>
                                @forelse ($meal->ingredients as $line)
                                    {{ $line }}<br>
                                @empty
                                    —
                                @endforelse
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>

    <section style="page-break-before: always">
        <h2>Lista zakupów</h2>

        @if (count($document->shoppingList->items) === 0)
            <p>Przepisy w tym jadłospisie nie mają składników.</p>
        @else
            <table>
                <tbody>
                    @foreach ($document->shoppingList->items as $item)
                        <tr>
                            <td style="width: 70%">{{ $item->name }}</td>
                            <td class="quantity">{{ $item->display }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (count($document->shoppingList->recipesWithoutIngredients) > 0)
            <p class="note">Bez składników (nie ma ich na liście): {{ implode(', ', $document->shoppingList->recipesWithoutIngredients) }}.</p>
        @endif
    </section>

    @foreach ($document->recipes as $recipe)
        <section style="page-break-before: always">
            <h2>{{ $recipe->name }}@if ($recipe->deleted) <span class="meta">(usunięty)</span>@endif</h2>

            <table style="width: 15%; margin: 0 0 10px">
                <tr><td class="category meal-{{ $recipe->categoryValue }}">{{ $recipe->category }}</td></tr>
            </table>

            <p><strong>Składniki</strong></p>
            @if (collect($recipe->ingredientGroups)->every(fn ($group) => count($group->lines) === 0))
                <p>Bez składników</p>
            @else
                @foreach ($recipe->ingredientGroups as $group)
                    <p>
                        <span class="meta">{{ implode(' · ', $group->dayLabels) }}</span><br>
                        @forelse ($group->lines as $line)
                            {{ $line }}<br>
                        @empty
                            —
                        @endforelse
                    </p>
                @endforeach
            @endif

            <p><strong>Przygotowanie</strong></p>
            <p>{!! nl2br(e($recipe->instructions)) !!}</p>
        </section>
    @endforeach
</body>
</html>
