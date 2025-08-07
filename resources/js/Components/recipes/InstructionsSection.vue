<template>
  <div class="space-y-4">
    <h2 class="text-2xl font-semibold text-gray-900">Instructions</h2>
    <div class="prose prose-gray max-w-none">
      <div
        v-if="instructions && instructions.trim()"
        class="bg-gray-50 rounded-lg p-6 whitespace-pre-line text-gray-700 leading-relaxed"
      >
        {{ instructions }}
      </div>
      <div
        v-else
        class="text-center py-8 text-gray-500 bg-gray-50 rounded-lg"
      >
        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="text-lg font-medium">No instructions provided</p>
        <p class="text-sm">Instructions for preparing this recipe haven't been added yet.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  instructions: {
    type: String,
    default: '',
  },
});
</script>
