<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>
<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-10">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center gap-x-2">
        <div
          class="flex size-8 shrink-0 items-center justify-center rounded-md bg-theme-light-500 text-sm font-semibold text-white"
        >
          R
        </div>
        <span class="font-display text-base font-semibold text-gray-900">Rauzee ERP</span>
      </div>
      <div class="panel p-6">
        <h1 class="font-display text-xl font-semibold text-gray-900">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 text-xs text-gray-500">{{ subtitle }}</p>
        <div class="mt-5">
          <slot />
        </div>
      </div>
      <div class="mt-4 text-center text-xs text-gray-500">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>