<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { LogOut, Plus } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import CustomerTicketFormDrawer from '@/components/drawers/CustomerTicketFormDrawer.vue'
import { formatDate, formatDateTime } from '@/constants/domain'
import { errorMessage } from '@/composables/useToast'
import { useAuthStore } from '@/stores/useAuthStore'
import { listCustomerTickets } from '@/utils/api/customerTickets'
import { getCustomerProfile } from '@/utils/api/profile'

const router = useRouter()
const auth = useAuthStore()

const customer = ref(null)
const loading = ref(true)
const error = ref('')
const tickets = ref([])
const ticketsMeta = ref(null)
const ticketsPage = ref(1)
const ticketsLoading = ref(true)
const ticketsError = ref('')
const ticketDrawerOpen = ref(false)

async function fetchCustomer() {
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
}

async function fetchTickets() {
  ticketsLoading.value = true
  ticketsError.value = ''
  try {
    const response = await listCustomerTickets({ page: ticketsPage.value })
    tickets.value = response.data
    ticketsMeta.value = response.meta
  } catch (e) {
    ticketsError.value = errorMessage(e, 'Não foi possível carregar seus chamados.')
  } finally {
    ticketsLoading.value = false
  }
}

function onTicketSaved() {
  if (ticketsPage.value !== 1) {
    ticketsPage.value = 1
    return
  }
  fetchTickets()
}

watch(ticketsPage, fetchTickets)

onMounted(() => {
  fetchCustomer()
  fetchTickets()
})

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
<template>
  <div class="min-h-screen bg-gray-50">
    <header
      class="sticky top-0 z-30 flex h-16 items-center gap-x-3 border-b border-gray-200 bg-white px-4 lg:px-6"
    >
      <div
        class="flex size-7 shrink-0 items-center justify-center rounded-md bg-theme-light-500 text-[13px] font-semibold text-white"
      >
        R
      </div>
      <span class="font-display text-[15px] font-semibold text-gray-900">Área do cliente</span>
      <div class="ml-auto flex items-center gap-x-2">
        <span class="hidden max-w-50 truncate text-xs text-gray-500 sm:block">
          {{ auth.user?.email }}
        </span>
        <button
          type="button"
          class="inline-flex size-9 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/30"
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
      <AlertBanner v-else-if="error" tone="warning">{{ error }}</AlertBanner>
      <div v-else-if="customer" class="space-y-4">
        <section class="panel p-6">
          <h1 class="font-display text-xl font-semibold text-gray-900">{{ customer.name }}</h1>
          <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <dt class="stat-label">CNPJ/CPF</dt>
              <dd class="mono mt-1 text-sm text-gray-900">{{ customer.document_formatted }}</dd>
            </div>
            <div>
              <dt class="stat-label">Segmento</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ customer.segment_label }}</dd>
            </div>
            <div>
              <dt class="stat-label">E-mail</dt>
              <dd class="mt-1 truncate text-sm text-gray-900">{{ customer.email }}</dd>
            </div>
            <div>
              <dt class="stat-label">Telefone</dt>
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
      </div>
      <section v-if="!loading && !error" class="panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-3">
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Meus chamados</h2>
            <p class="mt-0.5 text-xs text-gray-500">Acompanhe suas solicitações e fale com nossa equipe.</p>
          </div>
          <BaseButton size="sm" class="!shadow-none" @click="ticketDrawerOpen = true">
            <Plus class="size-4" />
            Abrir chamado
          </BaseButton>
        </div>
        <AlertBanner v-if="ticketsError" class="m-4">{{ ticketsError }}</AlertBanner>
        <div v-if="ticketsLoading" class="space-y-3 px-5 py-5">
          <div v-for="row in 3" :key="row" class="h-10 animate-pulse rounded bg-gray-100" />
        </div>
        <p v-else-if="!tickets.length" class="px-5 py-10 text-center text-[13px] text-gray-500">
          Você ainda não tem chamados.
        </p>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="ticket in tickets" :key="ticket.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
            <router-link
              :to="{ name: 'customer.tickets.show', params: { id: ticket.id } }"
              class="min-w-0 flex-1"
            >
              <span class="block truncate text-[13px] font-medium text-gray-900 hover:text-theme-light-700">
                {{ ticket.reference }} · {{ ticket.title }}
              </span>
              <span class="mt-0.5 block text-xs text-gray-500">
                Atualizado em {{ formatDateTime(ticket.updated_at) }}
              </span>
            </router-link>
            <BadgeTag :label="ticket.status_label" :tone="ticket.status" />
          </li>
        </ul>
        <PaginationBar :meta="ticketsMeta" @change="ticketsPage = $event" />
      </section>
    </main>
    <CustomerTicketFormDrawer
      :open="ticketDrawerOpen"
      @close="ticketDrawerOpen = false"
      @saved="onTicketSaved"
    />
  </div>
</template>