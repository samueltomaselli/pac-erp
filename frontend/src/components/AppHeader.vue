<script setup>
import { ArrowLeft, Menu } from '@lucide/vue'

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  back: { type: [String, Object], default: null },
})

defineEmits(['toggle'])
</script>
<template>
  <div
    class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-3 border-b border-gray-200 bg-white/95 px-3 backdrop-blur sm:px-4 lg:px-6"
  >
    <button
      type="button"
      class="-ml-1 inline-flex size-9 shrink-0 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/30 lg:hidden"
      @click="$emit('toggle')"
    >
      <span class="sr-only">Abrir menu lateral</span>
      <Menu class="size-5" />
    </button>
    <router-link
      v-if="back"
      :to="back"
      class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/30"
      title="Voltar"
    >
      <span class="sr-only">Voltar</span>
      <ArrowLeft class="size-4" />
    </router-link>
    <div class="min-w-0 flex-1">
      <h1 v-if="title" class="truncate font-display text-lg font-semibold leading-tight text-gray-900">
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
