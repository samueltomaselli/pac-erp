<script setup>
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, CheckSquare, MessageSquareWarning, Plus, Ticket } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import CustomerTicketFormDrawer from '@/components/drawers/CustomerTicketFormDrawer.vue'
import { formatDate, formatDateTime } from '@/constants/domain'
import { errorMessage } from '@/composables/useToast'
import { listCustomerTickets } from '@/utils/api/customerTickets'
import { getCustomerProfile } from '@/utils/api/profile'

const customer = ref(null)
const loading = ref(true)
const error = ref('')
const tickets = ref([])
const ticketsLoading = ref(true)
const ticketsError = ref('')
const ticketDrawerOpen = ref(false)

const FINISHED = ['resolved', 'closed']

const tasks = computed(() => customer.value?.tasks ?? [])
const openTickets = computed(() => tickets.value.filter((ticket) => !FINISHED.includes(ticket.status)))
const counts = computed(() => ({
  openTickets: openTickets.value.length,
  waiting: tickets.value.filter((ticket) => ticket.status === 'waiting_customer').length,
  pending: tasks.value.filter((task) => !task.completed_at).length,
  overdue: tasks.value.filter((task) => task.is_overdue).length,
}))
const cards = computed(() => [
  { label: 'Chamados em andamento', value: counts.value.openTickets, icon: Ticket },
  {
    label: 'Aguardando sua resposta',
    value: counts.value.waiting,
    icon: MessageSquareWarning,
    warning: counts.value.waiting > 0,
  },
  { label: 'Tarefas pendentes', value: counts.value.pending, icon: CheckSquare },
  { label: 'Com prazo vencido', value: counts.value.overdue, icon: AlertTriangle, danger: counts.value.overdue > 0 },
])
const recentTickets = computed(() => tickets.value.slice(0, 5))

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
    const response = await listCustomerTickets({ page: 1 })
    tickets.value = response.data
  } catch (e) {
    ticketsError.value = errorMessage(e, 'Não foi possível carregar seus chamados.')
  } finally {
    ticketsLoading.value = false
  }
}

onMounted(() => {
  fetchCustomer()
  fetchTickets()
})
</script>
<template>
  <AppLayout title="Início" :subtitle="customer?.name ?? 'Acompanhe suas tarefas e chamados'">
    <template #actions>
      <BaseButton :disabled="!!error" @click="ticketDrawerOpen = true">
        <Plus class="size-4" />
        <span class="hidden sm:inline">Abrir chamado</span>
        <span class="sm:hidden">Abrir</span>
      </BaseButton>
    </template>

    <AlertBanner v-if="error" tone="warning">{{ error }}</AlertBanner>

    <template v-else>
      <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div v-for="card in cards" :key="card.label" class="panel flex items-start justify-between gap-3 p-4">
          <div>
            <p class="stat-label">{{ card.label }}</p>
            <p
              class="mt-1 text-2xl font-semibold tabular-nums"
              :class="card.danger ? 'text-red-600' : card.warning ? 'text-amber-600' : 'text-gray-900'"
            >
              <span v-if="loading || ticketsLoading" class="inline-block h-7 w-10 animate-pulse rounded bg-gray-200" />
              <template v-else>{{ card.value }}</template>
            </p>
          </div>
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-md"
            :class="
              card.danger
                ? 'bg-red-50 text-red-600'
                : card.warning
                  ? 'bg-amber-50 text-amber-600'
                  : 'bg-gray-100 text-gray-500'
            "
          >
            <component :is="card.icon" class="size-4" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
          <section class="panel overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <h2 class="text-sm font-semibold text-gray-900">Chamados recentes</h2>
              <router-link
                :to="{ name: 'customer.tickets.index' }"
                class="text-xs font-medium text-theme-light-700 hover:underline"
              >
                Ver todos
              </router-link>
            </div>
            <AlertBanner v-if="ticketsError" class="m-4">{{ ticketsError }}</AlertBanner>
            <div v-if="ticketsLoading" class="space-y-3 px-5 py-5">
              <div v-for="row in 3" :key="row" class="h-10 animate-pulse rounded bg-gray-100" />
            </div>
            <div v-else-if="!tickets.length" class="flex flex-col items-center px-5 py-10 text-center">
              <p class="text-sm font-medium text-gray-900">Você ainda não tem chamados</p>
              <p class="mt-1 text-sm text-gray-500">Abra um chamado para falar com a nossa equipe.</p>
            </div>
            <ul v-else class="divide-y divide-gray-100">
              <li v-for="ticket in recentTickets" :key="ticket.id">
                <router-link
                  :to="{ name: 'customer.tickets.show', params: { id: ticket.id } }"
                  class="flex flex-wrap items-center gap-3 px-5 py-3 transition-colors hover:bg-gray-50"
                >
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-gray-900">
                      <span class="font-mono text-[13px] text-gray-500">{{ ticket.reference }}</span>
                      · {{ ticket.title }}
                    </span>
                    <span class="mt-0.5 block text-xs text-gray-500">
                      Atualizado em {{ formatDateTime(ticket.updated_at) }}
                    </span>
                  </span>
                  <BadgeTag :label="ticket.status_label" :tone="ticket.status" />
                </router-link>
              </li>
            </ul>
          </section>

          <section class="panel overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <h2 class="text-sm font-semibold text-gray-900">
                Suas tarefas
                <span class="ml-1 text-xs font-normal tabular-nums text-gray-500">{{ tasks.length }}</span>
              </h2>
            </div>
            <div v-if="loading" class="space-y-3 px-5 py-5">
              <div v-for="row in 2" :key="row" class="h-10 animate-pulse rounded bg-gray-100" />
            </div>
            <p v-else-if="!tasks.length" class="px-5 py-10 text-center text-sm text-gray-500">
              Nenhuma tarefa no momento.
            </p>
            <ul v-else class="divide-y divide-gray-100">
              <li
                v-for="task in tasks"
                :key="task.id"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                :class="task.is_overdue ? 'bg-red-50/60' : ''"
              >
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                  <p class="mt-0.5 text-xs" :class="task.is_overdue ? 'text-red-600' : 'text-gray-500'">
                    Prazo: <span class="tabular-nums">{{ formatDate(task.due_date) }}</span>
                    <span v-if="task.completed_at" class="text-gray-500">
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

        <section class="panel p-5">
          <h2 class="text-sm font-semibold text-gray-900">Dados cadastrais</h2>
          <div v-if="loading" class="mt-4 space-y-3">
            <div v-for="row in 4" :key="row" class="h-9 animate-pulse rounded bg-gray-100" />
          </div>
          <dl v-else-if="customer" class="mt-4 space-y-4">
            <div>
              <dt class="stat-label">Empresa</dt>
              <dd class="mt-1 text-sm font-medium text-gray-900">{{ customer.name }}</dd>
            </div>
            <div>
              <dt class="stat-label">CNPJ/CPF</dt>
              <dd class="mono mt-1 text-sm text-gray-900">{{ customer.document_formatted }}</dd>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <dt class="stat-label">Segmento</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ customer.segment_label }}</dd>
              </div>
              <div>
                <dt class="stat-label">Telefone</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ customer.phone || '—' }}</dd>
              </div>
            </div>
            <div>
              <dt class="stat-label">E-mail</dt>
              <dd class="mt-1 truncate text-sm text-gray-900">{{ customer.email }}</dd>
            </div>
            <div v-if="customer.contact_name">
              <dt class="stat-label">Contato</dt>
              <dd class="mt-1 text-sm text-gray-900">{{ customer.contact_name }}</dd>
            </div>
          </dl>
        </section>
      </div>
    </template>

    <CustomerTicketFormDrawer :open="ticketDrawerOpen" @close="ticketDrawerOpen = false" @saved="fetchTickets" />
  </AppLayout>
</template>
