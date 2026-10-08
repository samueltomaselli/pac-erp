<script setup>
import { Check, LoaderCircle } from '@lucide/vue'

defineProps({
  done: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

defineEmits(['toggle'])
</script>
<template>
  <button
    type="button"
    role="checkbox"
    :aria-checked="done"
    :disabled="loading"
    :title="done ? 'Reabrir tarefa' : 'Marcar como concluída'"
    class="group/check inline-flex size-5 shrink-0 items-center justify-center rounded-full border-[1.5px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/40 focus-visible:ring-offset-1"
    :class="
      done
        ? 'border-green-600 bg-green-600 text-white hover:border-green-700 hover:bg-green-700'
        : 'border-gray-300 bg-white text-transparent hover:border-green-600 hover:text-green-600'
    "
    @click.stop="$emit('toggle')"
  >
    <span class="sr-only">{{ done ? 'Reabrir tarefa' : 'Concluir tarefa' }}</span>
    <LoaderCircle v-if="loading" class="size-3 animate-spin text-gray-400" />
    <Check v-else class="size-3" stroke-width="3" />
  </button>
</template>
