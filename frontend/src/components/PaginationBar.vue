<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import BaseButton from '@/components/BaseButton.vue'

const props = defineProps({
  meta: { type: Object, default: null },
})

const emit = defineEmits(['change'])

function go(page) {
  if (!props.meta || page < 1 || page > props.meta.last_page) {
    return
  }

  emit('change', page)
}
</script>
<template>
  <div
    v-if="meta && meta.total > 0"
    class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-4 py-2.5"
  >
    <span class="text-xs text-gray-500">
      <template v-if="meta.from">
        <span class="tabular-nums">{{ meta.from }}–{{ meta.to }}</span> de
      </template>
      <span class="tabular-nums">{{ meta.total }}</span> registro(s)
    </span>
    <div v-if="meta.last_page > 1" class="flex items-center gap-1">
      <BaseButton
        variant="ghost"
        size="sm"
        icon
        label="Página anterior"
        :disabled="meta.current_page <= 1"
        @click="go(meta.current_page - 1)"
      >
        <ChevronLeft class="size-4" />
      </BaseButton>
      <span class="px-2 text-xs tabular-nums text-gray-600">
        {{ meta.current_page }} / {{ meta.last_page }}
      </span>
      <BaseButton
        variant="ghost"
        size="sm"
        icon
        label="Próxima página"
        :disabled="meta.current_page >= meta.last_page"
        @click="go(meta.current_page + 1)"
      >
        <ChevronRight class="size-4" />
      </BaseButton>
    </div>
  </div>
</template>
