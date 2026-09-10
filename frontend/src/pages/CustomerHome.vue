<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { LogOut } from '@lucide/vue'
import BadgeTag from '@/components/BadgeTag.vue'
import { formatDate, formatDateTime } from '@/constants/domain'
import { useAuthStore } from '@/stores/useAuthStore'
import { getCustomerProfile } from '@/utils/api/profile'

const router = useRouter()
const auth = useAuthStore()

const customer = ref(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    customer.value = await getCustomerProfile()
  } catch (e) {
    error.value =
      e?.response?.status === 404
        ? 'Nenhum cadastro de cliente vinculado a este acesso.'
        : 'Não foi possível carregar seus dados.'
  } finally {
    loading.value = false
  }
})

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
<template>
  <div class="min-h-screen bg-gray-50">
    <header
      class="sticky top-0 z-30 flex h-14 items-center gap-x-3 border-b border-gray-200 bg-white px-4 lg:px-6"
    >
      <div
        class="flex size-7 shrink-0 items-center justify-center rounded-md bg-theme-light-500 text-[13px] font-semibold text-white"
      >
        R
      </div>
      <span class="font-display text-[15px] font-semibold text-gray-900">Área do cliente</span>
      <div class="ml-auto flex items-center gap-x-2">
        <span class="hidden max-w-[200px] truncate text-xs text-gray-500 sm:block">
          {{ auth.user?.email }}
        </span>
        <button
          type="button"
          class="inline-flex size-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/30"
          @click="onLogout"
        >
          <span class="sr-only">Sair</span>
          <LogOut class="size-4" />
        </button>
      </div>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-6 lg:px-6">
      <div v-if="loading" class="space-y-4">
        <div class="panel space-y-4 p-6">
          <div class="h-5 w-56 animate-pulse rounded bg-gray-200" />
          <div class="grid gap-4 sm:grid-cols-2">
            <div v-for="n in 4" :key="n" class="h-9 animate-pulse rounded bg-gray-200" />
          </div>
        </div>
      </div>
      <p
        v-else-if="error"
        class="rounded-lg bg-amber-100 px-4 py-3 text-sm text-yellow-700 ring-1 ring-amber-200"
      >
        {{ error }}
      </p>
      <div v-else-if="customer" class="space-y-4">
        <section class="panel p-6">
          <h1 class="font-display text-xl font-semibold text-gray-900">{{ customer.name }}</h1>
          <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <dt class="text-[11px] font-medium text-gray-500">CNPJ/CPF</dt>
              <dd class="mono mt-1 text-sm text-gray-900">{{ customer.document_formatted }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-medium text-gray-500">Segmento</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ customer.segment_label }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-medium text-gray-500">E-mail</dt>
              <dd class="mt-1 truncate text-sm text-gray-900">{{ customer.email }}</dd>
            </div>
            <div>
              <dt class="text-[11px] font-medium text-gray-500">Telefone</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ customer.phone || '—' }}</dd>
            </div>
          </dl>
        </section>
        <section class="panel overflow-hidden">
          <h2 class="border-b border-gray-200 bg-gray-50 px-6 py-3 text-sm font-semibold text-gray-900">
            Suas tarefas
          </h2>
          <p v-if="!customer.tasks.length" class="px-6 py-10 text-center text-sm text-gray-500">
            Nenhuma tarefa no momento.
          </p>
          <ul v-else class="divide-y divide-gray-200">
            <li
              v-for="task in customer.tasks"
              :key="task.id"
              class="flex flex-wrap items-center justify-between gap-3 px-6 py-3"
              :class="task.is_overdue ? 'bg-red-50' : ''"
            >
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                <p class="mt-0.5 text-xs text-gray-500">
                  Prazo: <span class="tabular-nums">{{ formatDate(task.due_date) }}</span>
                  <span v-if="task.completed_at">
                    · Concluída em {{ formatDateTime(task.completed_at) }}
                  </span>
                </p>
              </div>
              <div class="flex shrink-0 items-center gap-1.5">
                <BadgeTag v-if="task.is_overdue" label="Vencida" tone="high" />
                <BadgeTag :label="task.priority_label" :tone="task.priority" />
                <BadgeTag :label="task.status_label" :tone="task.status" />
              </div>
            </li>
          </ul>
        </section>
        <p class="text-xs text-gray-500">
          Propostas e chamados ficarão disponíveis aqui nas próximas sprints.
        </p>
      </div>
    </main>
  </div>
</template>