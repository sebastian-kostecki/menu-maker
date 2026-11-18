<template>
  <div class="flex flex-wrap items-center gap-2">
    <Button v-if="canEdit" variant="outline" as-child>
      <Link :href="editHref">
        <Pencil class="size-4" aria-hidden="true" />
        Edytuj
      </Link>
    </Button>

    <Button
      v-if="canEdit"
      variant="destructive"
      @click="$emit('delete-request')"
    >
      <Trash2 class="size-4" aria-hidden="true" />
      Usuń
    </Button>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

defineProps({
  canEdit: {
    type: Boolean,
    required: true,
  },
  editHref: {
    type: String,
    required: true,
  },
});

defineEmits(['delete-request']);
</script>
