<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RecipeImport\MarkdownRecipeParser;
use App\Services\RecipeImport\RecipeImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recipes:import
    {path : Katalog z plikami .md (jeden przepis na plik)}
    {--user= : ID albo e-mail właściciela przepisów}
    {--calories= : Kaloryczność całego przepisu (kcal), gdy plik nie ma „kalorie:”}
    {--servings= : Liczba porcji, gdy plik nie ma „porcje:”}
    {--dry-run : Tylko pokaż, co zostałoby zaimportowane}')]
#[Description('Importuje przepisy z plików Markdown (nagłówek, tabela składników, sposób przygotowania)')]
class ImportRecipesCommand extends Command
{
    public function handle(MarkdownRecipeParser $parser, RecipeImporter $importer): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Katalog „{$path}” nie istnieje.");

            return self::FAILURE;
        }

        $user = $this->owner();

        if ($user === null) {
            return self::FAILURE;
        }

        $defaultCalories = $this->defaultCalories();
        $defaultServings = $this->defaultServings();

        if ($defaultCalories === false || $defaultServings === false) {
            return self::FAILURE;
        }

        $files = glob($path.'/*.md') ?: [];
        sort($files);

        if ($files === []) {
            $this->warn("Brak plików .md w „{$path}”.");

            return self::SUCCESS;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $counts = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        $rows = [];

        foreach ($files as $file) {
            $parsed = $parser->parse(basename($file, '.md'), (string) file_get_contents($file), $defaultCalories, $defaultServings);
            $notes = [...$parsed->errors, ...$parsed->warnings];

            if (! $parsed->isImportable()) {
                $status = 'błąd';
                $counts['failed']++;
            } elseif ($importer->existsFor($user, $parsed->name)) {
                $status = 'pominięto';
                $notes = ['przepis o tej nazwie już istnieje'];
                $counts['skipped']++;
            } else {
                if (! $isDryRun) {
                    $importer->import($user, $parsed);
                }

                $status = $isDryRun ? 'do importu' : 'zaimportowano';
                $counts['imported']++;
            }

            $rows[] = [basename($file), $status, count($parsed->ingredients), implode('; ', $notes)];
        }

        $this->table(['Plik', 'Status', 'Składniki', 'Uwagi'], $rows);

        $this->info(sprintf(
            '%s: %d, pominięto: %d, błędy: %d.%s',
            $isDryRun ? 'Do importu' : 'Zaimportowano',
            $counts['imported'],
            $counts['skipped'],
            $counts['failed'],
            $isDryRun ? ' Nic nie zapisano (--dry-run).' : '',
        ));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function owner(): ?User
    {
        $identifier = trim((string) $this->option('user'));

        if ($identifier === '') {
            $this->error('Podaj właściciela przepisów: --user=<id albo e-mail>.');

            return null;
        }

        $user = ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->where('email', $identifier)->first();

        if ($user === null) {
            $this->error("Nie znaleziono użytkownika „{$identifier}”.");
        }

        return $user;
    }

    /**
     * @return numeric-string|null|false False when the option is invalid.
     */
    private function defaultCalories(): string|null|false
    {
        $value = $this->option('calories');

        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '.', (string) $value);

        if (! is_numeric($value) || bccomp($value, '50', 2) < 0) {
            $this->error('--calories musi być liczbą co najmniej 50 (kcal całego przepisu).');

            return false;
        }

        return $value;
    }

    private function defaultServings(): int|null|false
    {
        $value = $this->option('servings');

        if ($value === null) {
            return null;
        }

        if (! ctype_digit((string) $value) || (int) $value < 1) {
            $this->error('--servings musi być dodatnią liczbą całkowitą.');

            return false;
        }

        return (int) $value;
    }
}
