<script setup>
import { computed } from 'vue'
import { PRIORITY_CLASSES, STATUS_CLASSES } from '@/constants/domain'

const props = defineProps({
  label: { type: String, required: true },
  tone: { type: String, default: 'neutral' },
})

const classes = computed(
  () => PRIORITY_CLASSES[props.tone] ?? STATUS_CLASSES[props.tone] ?? 'bg-gray-100 text-gray-600',
)
</script>
<template>
  <span
    class="inline-flex h-5 items-center whitespace-nowrap rounded-md px-2 text-xs font-medium leading-none"
    :class="classes"
  >
    {{ label }}
  </span>
</template>
