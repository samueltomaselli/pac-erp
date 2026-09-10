<script setup>
import { Menu } from '@lucide/vue'

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})

defineEmits(['toggle'])
</script>
<template>
  <div
    class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-x-3 border-b border-gray-200 bg-white px-3 sm:px-4 lg:px-6"
  >
    <button
      type="button"
      class="-ml-2 inline-flex size-8 shrink-0 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/30 lg:hidden"
      @click="$emit('toggle')"
    >
      <span class="sr-only">Abrir menu lateral</span>
      <Menu class="size-4" />
    </button>
    <div class="flex min-w-0 flex-1 items-baseline gap-x-2">
      <h1 v-if="title" class="truncate font-display text-lg font-semibold text-gray-900">
        {{ title }}
      </h1>
      <p v-if="subtitle" class="hidden truncate text-xs text-gray-500 sm:block">
        {{ subtitle }}
      </p>
    </div>
    <div class="flex shrink-0 items-center gap-x-2">
      <slot name="actions" />
    </div>
  </div>
</template>