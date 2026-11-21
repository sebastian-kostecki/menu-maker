<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $menu->title }}</title>
    <style>
        /*
         * Mirror of resources/css/app.css :root (dompdf cannot read CSS variables). Keep in sync;
         * guarded by tests/Unit/Services/MealPlanPdfPaletteTest.php. Hexes are the sRGB-clipped
         * values from context/foundation/design-system.md 10.2.
         * --foreground  oklch(0.234 0.036 288.1)  #1d1b2e
         * --muted  oklch(0.952 0.017 76.1)  #f6eee3
         * --border  oklch(0.9 0.026 76.8)  #e8dccb
         * --meal-breakfast  oklch(0.885 0.129 86.1)  #ffd36e
         * --meal-breakfast-foreground  oklch(0.297 0.061 84.8)  #3b2a00
         * --meal-supper  oklch(0.755 0.151 38.3)  #ff8a65
         * --meal-supper-foreground  oklch(0.245 0.067 37.6)  #3a1206
         * --meal-dinner  oklch(0.697 0.136 279.0)  #8c93f0
         * --meal-dinner-foreground  oklch(0.225 0.073 276.5)  #14163d
         */
        @page { margin: 28pt 30pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1d1b2e; margin: 0; }
        h1 { font-size: 15pt; font-weight: bold; margin: 0 0 10pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #e8dccb; padding: 3pt 4pt; text-align: center; vertical-align: middle; word-wrap: break-word; overflow: hidden; }
        th { background: #f6eee3; font-size: 9pt; font-weight: bold; height: 26pt; }
        td.cell { height: 140pt; font-size: 9pt; }
        td.category { width: 8%; font-size: 9pt; font-weight: bold; }
        td.meal-breakfast { background: #ffd36e; color: #3b2a00; }
        td.meal-supper { background: #ff8a65; color: #3a1206; }
        td.meal-dinner { background: #8c93f0; color: #14163d; }
    </style>
</head>
<body>
    <h1>{{ $menu->title }}</h1>

    <table>
        <thead>
            <tr>
                <th style="width: 8%"></th>
                @foreach ($menu->dayLabels as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($menu->rows as $row)
                <tr>
                    <td class="category meal-{{ $row->categoryValue }}">{{ $row->category }}</td>
                    @foreach ($row->cells as $cell)
                        <td class="cell">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
