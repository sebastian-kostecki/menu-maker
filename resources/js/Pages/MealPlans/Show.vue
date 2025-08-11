<template>
  <Head :title="`Meal Plan - ${formatDateRange(mealPlan.start_date, mealPlan.end_date)}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Meal Plan Details
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ formatDateRange(mealPlan.start_date, mealPlan.end_date) }}
            ({{ calculateDays(mealPlan.start_date, mealPlan.end_date) }} days)
          </p>
        </div>
        <div class="flex items-center gap-3">
          <StatusTag :value="mealPlan.status as any" />
          <ActionDropdown :item="mealPlan" />
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <ChefHat class="h-6 w-6 text-gray-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">
                    Total Meals
                  </dt>
                  <dd class="text-lg font-medium text-gray-900">
                    {{ mealPlan.meals_count }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <FileText class="h-6 w-6 text-gray-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">
                    Generation Logs
                  </dt>
                  <dd class="text-lg font-medium text-gray-900">
                    {{ mealPlan.logs_count }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <Calendar class="h-6 w-6 text-gray-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">
                    Created
                  </dt>
                  <dd class="text-sm font-medium text-gray-900">
                    {{ formatDateTime(mealPlan.created_at) }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <Clock class="h-6 w-6 text-gray-400" />
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 truncate">
                    Last Updated
                  </dt>
                  <dd class="text-sm font-medium text-gray-900">
                    {{ formatDateTime(mealPlan.updated_at) }}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Meals Section -->
      <div class="bg-white shadow overflow-hidden sm:rounded-md" v-if="mealPlan.meals && mealPlan.meals.length > 0">
        <div class="px-4 py-5 sm:px-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900">Meals</h3>
          <p class="mt-1 max-w-2xl text-sm text-gray-500">
            Planned meals for this period
          </p>
        </div>
        <ul class="divide-y divide-gray-200">
          <li v-for="meal in mealPlan.meals" :key="meal.id" class="px-4 py-4">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="flex items-center justify-between">
                  <h4 class="text-sm font-medium text-gray-900">
                    {{ meal.recipe.title }}
                  </h4>
                  <div class="ml-2 flex-shrink-0 flex">
                    <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                      {{ meal.meal_type }}
                    </p>
                  </div>
                </div>
                <div class="mt-2 flex justify-between">
                  <div class="sm:flex">
                    <p class="flex items-center text-sm text-gray-500">
                      <Calendar class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" />
                      {{ formatDate(meal.planned_date) }}
                    </p>
                    <p class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0 sm:ml-6" v-if="meal.recipe.prep_time">
                      <Clock class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" />
                      {{ meal.recipe.prep_time }} min prep
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Generation Logs Section -->
      <div class="bg-white shadow overflow-hidden sm:rounded-md" v-if="mealPlan.logs && mealPlan.logs.length > 0">
        <div class="px-4 py-5 sm:px-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900">Generation Logs</h3>
          <p class="mt-1 max-w-2xl text-sm text-gray-500">
            History of meal plan generation process
          </p>
        </div>
        <ul class="divide-y divide-gray-200">
          <li v-for="log in mealPlan.logs" :key="log.id" class="px-4 py-4">
            <div class="flex space-x-3">
              <div class="flex-shrink-0">
                <div class="h-8 w-8 rounded-full flex items-center justify-center"
                     :class="getLogTypeClass(log.level)">
                  <component :is="getLogIcon(log.level)" class="h-4 w-4" />
                </div>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">
                  {{ log.message }}
                </p>
                <p class="text-sm text-gray-500">
                  {{ formatDateTime(log.created_at) }}
                </p>
                <div v-if="log.context" class="mt-2">
                  <pre class="text-xs bg-gray-50 rounded p-2 overflow-x-auto">{{ JSON.stringify(log.context, null, 2) }}</pre>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Empty States -->
      <div v-if="!mealPlan.meals || mealPlan.meals.length === 0" class="text-center py-12">
        <ChefHat class="mx-auto h-12 w-12 text-gray-400" />
        <h3 class="mt-2 text-sm font-medium text-gray-900">No meals planned</h3>
        <p class="mt-1 text-sm text-gray-500">This meal plan doesn't have any meals yet.</p>
      </div>

      <!-- Back to List -->
      <div class="flex justify-start">
        <Link :href="route('meal-plans.index')"
              class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
          <ArrowLeft class="mr-2 h-4 w-4" />
          Back to Meal Plans
        </Link>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import {
  ChefHat,
  FileText,
  Calendar,
  Clock,
  ArrowLeft,
  AlertCircle,
  CheckCircle,
  Info,
  XCircle
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'

// Local type definitions
interface Recipe {
  id: number
  title: string
  prep_time?: number
}

interface Meal {
  id: number
  meal_type: string
  planned_date: string
  recipe: Recipe
}

interface Log {
  id: number
  level: string
  message: string
  context?: any
  created_at: string
}

interface MealPlan {
  id: number
  start_date: string
  end_date: string
  status: string
  created_at: string
  updated_at: string
  meals_count: number
  logs_count: number
  meals?: Meal[]
  logs?: Log[]
  links: {
    self: string
  }
}

interface Props {
  mealPlan: MealPlan
}

const props = defineProps<Props>()

// Date formatting utilities
const formatDateRange = (startDate: string, endDate: string): string => {
  const start = new Date(startDate).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
  const end = new Date(endDate).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
  return `${start} - ${end}`
}

const formatDate = (date: string): string => {
  return new Date(date).toLocaleDateString('en-GB', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

const formatDateTime = (dateTime: string): string => {
  return new Date(dateTime).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const calculateDays = (startDate: string, endDate: string): number => {
  const start = new Date(startDate)
  const end = new Date(endDate)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
}

// Log utilities
const getLogTypeClass = (level: string): string => {
  switch (level.toLowerCase()) {
    case 'error':
      return 'bg-red-100 text-red-600'
    case 'warning':
      return 'bg-yellow-100 text-yellow-600'
    case 'info':
      return 'bg-blue-100 text-blue-600'
    case 'success':
      return 'bg-green-100 text-green-600'
    default:
      return 'bg-gray-100 text-gray-600'
  }
}

const getLogIcon = (level: string) => {
  switch (level.toLowerCase()) {
    case 'error':
      return XCircle
    case 'warning':
      return AlertCircle
    case 'info':
      return Info
    case 'success':
      return CheckCircle
    default:
      return Info
  }
}
</script>
