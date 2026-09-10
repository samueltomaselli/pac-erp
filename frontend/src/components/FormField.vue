<script setup>
defineProps({
  label: { type: String, required: true },
  name: { type: String, required: true },
  errors: { type: Object, default: () => ({}) },
  hint: { type: String, default: '' },
})
</script>
<template>
  <div>
    <label class="field-label" :for="name">{{ label }}</label>
    <slot />
    <p v-if="errors[name]" class="mt-1 text-xs text-red-600">{{ errors[name][0] }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-gray-500">{{ hint }}</p>
  </div>
</template>