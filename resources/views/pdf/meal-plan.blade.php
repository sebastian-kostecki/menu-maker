<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 10px; }
        h2 { font-size: 13pt; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 5px; vertical-align: top; text-align: left; }
        th { background: #e8e8e8; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        tr.day-start td { border-top: 2px solid #444; }
        .day { font-weight: bold; }
        td.day-continued { border-top: none; }
        .note { margin-top: 10px; }
        .notices { margin: 0 0 10px; padding: 6px 8px; border: 1px solid #c9a227; background: #fff8e1; }
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
                <th style="width: 11%">Dzień</th>
                <th style="width: 9%">Posiłek</th>
                <th style="width: 16%">Nazwa</th>
                <th style="width: 24%">Składniki</th>
                <th style="width: 40%">Przygotowanie</th>
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
                        <td>{{ $meal->category }}</td>
                        <td>{{ $meal->recipeName }}@if ($meal->recipeDeleted) (usunięty)@endif</td>
                        <td>
                            @forelse ($meal->ingredients as $line)
                                {{ $line }}<br>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{!! nl2br(e($meal->instructions)) !!}</td>
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
                            <td>{{ $item->display }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (count($document->shoppingList->recipesWithoutIngredients) > 0)
            <p class="note">Bez składników (nie ma ich na liście): {{ implode(', ', $document->shoppingList->recipesWithoutIngredients) }}.</p>
        @endif
    </section>
</body>
</html>
