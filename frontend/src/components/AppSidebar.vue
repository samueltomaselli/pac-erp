<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { PanelLeftClose, PanelLeftOpen, X } from '@lucide/vue'

const props = defineProps({
  navigation: { type: Array, required: true },
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['update:open'])

const route = useRoute()

const STORAGE_KEY = 'erp.sidebar.expanded'

const expanded = ref(readStored())

function readStored() {
  try {
    return localStorage.getItem(STORAGE_KEY) !== 'false'
  } catch {
    return true
  }
}

watch(expanded, (value) => {
  try {
    localStorage.setItem(STORAGE_KEY, String(value))
  } catch {}
})

const width = computed(() => (expanded.value ? 232 : 56))

function isActive(item) {
  return item.match ? route.path.startsWith(item.match) : route.name === item.name
}

function closeOnMobile() {
  emit('update:open', false)
}
</script>
<template>
  <div
    v-if="open"
    class="fixed inset-0 z-40 bg-gray-500/75 lg:hidden"
    @click="closeOnMobile"
  />
  <aside
    class="fixed inset-y-0 left-0 z-50 flex shrink-0 flex-col border-r border-gray-200 bg-white transition-[width,transform] duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
    :class="open ? 'translate-x-0' : '-translate-x-full'"
    :style="{ width: `${width}px` }"
  >
    <div class="flex h-14 shrink-0 items-center gap-x-2 border-b border-gray-200 px-3">
      <div
        class="flex size-7 shrink-0 items-center justify-center rounded-md bg-theme-light-500 text-[13px] font-semibold text-white"
      >
        R
      </div>
      <span
        v-if="expanded"
        class="min-w-0 flex-1 truncate font-display text-[15px] font-semibold text-gray-900"
      >
        Rauzee
      </span>
      <button
        v-if="expanded"
        type="button"
        class="hidden size-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-light-500/35 lg:flex"
        @click="expanded = false"
      >
        <span class="sr-only">Recolher menu lateral</span>
        <PanelLeftClose class="size-4" />
      </button>
      <button
        type="button"
        class="flex size-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 lg:hidden"
        @click="closeOnMobile"
      >
        <span class="sr-only">Fechar menu lateral</span>
        <X class="size-4" />
      </button>
    </div>
    <button
      v-if="!expanded"
      type="button"
      class="mx-auto mt-2 hidden size-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-light-500/35 lg:flex"
      @click="expanded = true"
    >
      <span class="sr-only">Expandir menu lateral</span>
      <PanelLeftOpen class="size-4" />
    </button>
    <nav class="flex-1 overflow-y-auto overflow-x-hidden px-2 py-3">
      <p
        v-if="expanded"
        class="px-3 pt-2 pb-1 text-[11px] font-medium text-gray-500"
      >
        Operação
      </p>
      <ul class="space-y-0.5">
        <li v-for="item in navigation" :key="item.name">
          <router-link
            :to="{ name: item.name }"
            :title="expanded ? undefined : item.label"
            :aria-current="isActive(item) ? 'page' : undefined"
            class="group/item relative flex w-full items-center gap-x-2 rounded-md px-2.5 py-1.5 text-[13px] transition-colors duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-light-500/35"
            :class="
              isActive(item)
                ? 'bg-gray-100 font-medium text-gray-900 hover:bg-gray-100'
                : 'font-normal text-gray-700 hover:bg-gray-100 hover:text-gray-900'
            "
            @click="closeOnMobile"
          >
            <span
              aria-hidden="true"
              class="absolute left-0 top-1/2 h-4 w-0.5 origin-center -translate-y-1/2 rounded-[2px] bg-theme-light-500 transition-[scale] duration-[180ms] ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
              :class="isActive(item) ? 'scale-y-100' : 'scale-y-0'"
            />
            <component
              :is="item.icon"
              class="size-4 shrink-0 transition-colors duration-150"
              :class="
                isActive(item)
                  ? 'text-gray-700'
                  : 'text-gray-500 group-hover/item:text-gray-800'
              "
            />
            <span v-if="expanded" class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            <span
              v-if="expanded && item.badge"
              class="shrink-0 text-[11px] font-medium tabular-nums text-gray-500"
            >
              {{ item.badge }}
            </span>
          </router-link>
        </li>
      </ul>
    </nav>
  </aside>
</template>