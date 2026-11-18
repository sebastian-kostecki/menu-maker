<template>
  <Head :title="pageTitle" />

  <AuthenticatedLayout>
    <div class="flex w-full max-w-205 flex-col gap-6">
      <PageHeader :title="pageTitle">
        <template #breadcrumbs>
          <Link
            :href="route('recipes.index')"
            class="inline-flex items-center gap-1.5 rounded-sm text-label text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Przepisy
          </Link>
        </template>
      </PageHeader>

      <RecipeForm
        :form="form"
        :categories="categories"
        :ingredients="ingredients"
        :units="units"
        :is-editing="!!recipe?.data"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PageHeader from '@/Components/Common/PageHeader.vue'
import RecipeForm from '@/Components/recipes/RecipeForm.vue'

// Define props interface
const props = defineProps({
  recipe: {
    type: Object,
    default: null
  },
  categories: {
    type: Array,
    required: true
  },
  ingredients: {
    type: Array,
    required: true
  },
  units: {
    type: Array,
    required: true
  }
})

const pageTitle = computed(() => (props.recipe?.data ? 'Edytuj przepis' : 'Nowy przepis'))

// Initialize form with useForm hook
const form = useForm({
  name: props.recipe?.data?.name ?? '',
  category: props.recipe?.data?.category ?? null,
  instructions: props.recipe?.data?.instructions ?? '',
  calories: props.recipe?.data?.calories ?? '',
  servings: props.recipe?.data?.servings ?? '',
  ingredients: props.recipe?.data?.ingredients?.map(ingredient => ({
    name: ingredient.name,
    quantity: ingredient.quantity,
    unit_id: ingredient.unit.id,
  })) ?? []
})

// Handle form submission
const handleSubmit = () => {
  if (props.recipe) {
    // Update existing recipe
    form.put(route('recipes.update', props.recipe.data.id), {
      onSuccess: () => {
        // Success handled by redirect from controller
      }
    })
  } else {
    // Create new recipe
    form.post(route('recipes.store'), {
      onSuccess: () => {
        // Success handled by redirect from controller
      }
    })
  }
}

// Handle cancel action - navigate to recipes list
const handleCancel = () => {
  router.visit(route('recipes.index'))
}
</script>
