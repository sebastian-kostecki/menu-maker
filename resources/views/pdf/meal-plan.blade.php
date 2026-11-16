<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        /*
         * Mirror of resources/css/app.css :root (dompdf cannot read CSS variables). Keep in sync;
         * guarded by tests/Unit/Services/MealPlanPdfPaletteTest.php. Hexes are the sRGB-clipped
         * values from context/foundation/design-system.md 4.2-4.4.
         * --foreground  oklch(0.24 0.02 60)  #261d16
         * --muted  oklch(0.955 0.012 85)  #f4f0e7
         * --muted-foreground  oklch(0.50 0.02 60)  #6c6158
         * --border  oklch(0.90 0.012 85)  #e2ded5
         * --warning  oklch(0.96 0.06 90)  #fff1c4
         * --warning-foreground  oklch(0.45 0.10 75)  #754b00
         * --meal-breakfast  oklch(0.95 0.06 85)  #ffecc1
         * --meal-breakfast-foreground  oklch(0.45 0.10 70)  #784900
         * --meal-supper  oklch(0.94 0.045 40)  #ffe2d5
         * --meal-supper-foreground  oklch(0.47 0.14 35)  #99351b
         * --meal-dinner  oklch(0.94 0.035 280)  #e6e9ff
         * --meal-dinner-foreground  oklch(0.45 0.12 280)  #4a4b96
         */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #261d16; }
        h1 { font-size: 15pt; font-weight: bold; margin: 0 0 10px; }
        h2 { font-size: 12pt; font-weight: bold; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2ded5; padding: 4px 5px; vertical-align: top; text-align: left; }
        th { background: #f4f0e7; font-size: 9pt; font-weight: bold; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        tr.day-start td { border-top: 2px solid #6c6158; }
        .day { font-size: 9pt; font-weight: bold; }
        td.day-continued { border-top: none; }
        td.category { font-size: 8pt; font-weight: bold; }
        td.meal-breakfast { background: #ffecc1; color: #784900; }
        td.meal-supper { background: #ffe2d5; color: #99351b; }
        td.meal-dinner { background: #e6e9ff; color: #4a4b96; }
        td.quantity { text-align: right; }
        .meta, .note { font-size: 8pt; color: #6c6158; }
        .note { margin-top: 10px; }
        .notices { margin: 0 0 10px; padding: 6px 8px; border: 1px solid #e2ded5; border-radius: 4px; background: #fff1c4; color: #754b00; }
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
                        @else
                            <td>{{ $meal->recipeName }}@if ($meal->recipeDeleted) <span class="meta">(usunięty)</span>@endif</td>
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
