<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Head, Link } from '@inertiajs/vue3'
import GenerateMealPlanDialog from '@/Components/MealPlans/GenerateMealPlanDialog.vue'
import { Plus, ChefHat, Calendar, FileText } from 'lucide-vue-next'
</script>

<template>
    <Head title="Pulpit" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                Pulpit
            </h1>
        </template>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
            <!-- Recipes Count -->
            <div class="rounded-lg border bg-card p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-accent p-3">
                        <ChefHat class="size-6 text-primary" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-muted-foreground">
                            Liczba przepisów
                        </p>
                        <p class="text-2xl font-semibold text-foreground">
                            12
                        </p>
                    </div>
                </div>
            </div>

            <!-- Meal Plans Count -->
            <div class="rounded-lg border bg-card p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-accent p-3">
                        <Calendar class="size-6 text-primary" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-muted-foreground">
                            Jadłospisy
                        </p>
                        <p class="text-2xl font-semibold text-foreground">
                            3
                        </p>
                    </div>
                </div>
            </div>

            <!-- Last Generated PDF -->
            <div class="rounded-lg border bg-card p-6">
                <div class="flex items-center">
                    <div class="rounded-full bg-accent p-3">
                        <FileText class="size-6 text-primary" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-muted-foreground">
                            Ostatni PDF
                        </p>
                        <p class="text-sm font-medium text-foreground">
                            2 dni temu
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="rounded-lg border bg-card p-6">
            <h2 class="mb-4 text-lg font-semibold text-foreground">
                Szybkie akcje
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Button size="lg" class="w-full justify-start" as-child>
                    <Link href="/recipes/create">
                        <Plus class="mr-2 h-5 w-5" />
                        Dodaj przepis
                    </Link>
                </Button>
                <GenerateMealPlanDialog>
                    <Button variant="outline" size="lg" class="w-full justify-start">
                        <Calendar class="mr-2 h-5 w-5" />
                        Wygeneruj jadłospis
                    </Button>
                </GenerateMealPlanDialog>
            </div>
        </div>

        <!-- Welcome Message -->
        <div class="mt-8 rounded-lg border bg-accent p-6 text-accent-foreground">
            <h3 class="text-lg font-semibold">
                Witaj w Menu Maker! 👋
            </h3>
            <p class="mt-2">
                Twój osobisty asystent kulinarny jest gotowy do działania. Zacznij od dodania przepisów, a następnie wygeneruj swój pierwszy jadłospis.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
