<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Head, Link } from '@inertiajs/vue3'
import GenerateMealPlanDialog from '@/Components/MealPlans/GenerateMealPlanDialog.vue'
import { Plus, ChefHat, Calendar, FileText } from 'lucide-vue-next'
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Dashboard
            </h1>
        </template>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
            <!-- Recipes Count -->
            <div class="bg-white rounded-lg shadow-xs border border-gray-200 p-6 dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <ChefHat class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Liczba przepisów
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            12
                        </p>
                    </div>
                </div>
            </div>

            <!-- Meal Plans Count -->
            <div class="bg-white rounded-lg shadow-xs border border-gray-200 p-6 dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <Calendar class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Jadłospisy
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            3
                        </p>
                    </div>
                </div>
            </div>

            <!-- Last Generated PDF -->
            <div class="bg-white rounded-lg shadow-xs border border-gray-200 p-6 dark:bg-gray-800 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                        <FileText class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Ostatni PDF
                        </p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            2 dni temu
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-xs border border-gray-200 p-6 dark:bg-gray-800 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
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
        <div class="mt-8 bg-linear-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6 dark:from-blue-900/20 dark:to-indigo-900/20 dark:border-blue-800">
            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100">
                Witaj w Menu Maker! 👋
            </h3>
            <p class="mt-2 text-blue-700 dark:text-blue-200">
                Twój osobisty asystent kulinarny jest gotowy do działania. Zacznij od dodania przepisów, a następnie wygeneruj swój pierwszy jadłospis.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
