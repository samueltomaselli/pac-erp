<script setup>
import { ref } from 'vue'
import { X } from '@lucide/vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])
const toast = useToast()
const input = ref(null)

const allowedExtensions = new Set([
  'pdf',
  'png',
  'jpg',
  'jpeg',
  'webp',
  'txt',
  'csv',
  'doc',
  'docx',
  'xls',
  'xlsx',
])
const maxFiles = 5
const maxSize = 10 * 1024 * 1024

function formatSize(bytes) {
  return bytes < 1024 * 1024
    ? `${(bytes / 1024).toFixed(0)} KB`
    : `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function addFiles(event) {
  const candidates = Array.from(event.target.files ?? [])
  const valid = []

  for (const file of candidates) {
    const extension = file.name.split('.').pop()?.toLowerCase()

    if (!extension || !allowedExtensions.has(extension)) {
      toast.error(`O tipo do arquivo "${file.name}" não é aceito.`)
      continue
    }

    if (file.size > maxSize) {
      toast.error(`O arquivo "${file.name}" excede o limite de 10 MB.`)
      continue
    }

    if (props.modelValue.length + valid.length >= maxFiles) {
      toast.error('É possível anexar até 5 arquivos por envio.')
      break
    }

    valid.push(file)
  }

  if (valid.length) {
    emit('update:modelValue', [...props.modelValue, ...valid])
  }

  event.target.value = ''
}

function removeFile(index) {
  emit(
    'update:modelValue',
    props.modelValue.filter((_, fileIndex) => fileIndex !== index),
  )
}
</script>

<template>
  <div class="space-y-2">
    <input
      ref="input"
      type="file"
      multiple
      accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx"
      class="sr-only"
      @change="addFiles"
    />
    <button
      type="button"
      class="inline-flex h-8 items-center rounded-md border border-gray-300 bg-white px-3 text-[13px] font-medium text-gray-700 hover:bg-gray-50"
      @click="input?.click()"
    >
      Adicionar anexos
    </button>
    <p class="text-xs text-gray-500">Até 5 arquivos, 10 MB cada. PDF, imagens, texto, CSV, Word ou Excel.</p>
    <ul v-if="modelValue.length" class="space-y-1.5">
      <li
        v-for="(file, index) in modelValue"
        :key="`${file.name}-${file.lastModified}-${index}`"
        class="flex items-center gap-2 rounded-md bg-gray-50 px-2.5 py-1.5 text-[13px]"
      >
        <span class="min-w-0 flex-1 truncate text-gray-700">{{ file.name }}</span>
        <span class="shrink-0 text-xs text-gray-500">{{ formatSize(file.size) }}</span>
        <button
          type="button"
          class="inline-flex size-8 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-700"
          :aria-label="`Remover ${file.name}`"
          @click="removeFile(index)"
        >
          <X class="size-3.5" />
        </button>
      </li>
    </ul>
  </div>
</template>
