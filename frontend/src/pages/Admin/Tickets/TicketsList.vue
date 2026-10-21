<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Plus, X } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import TicketFormDrawer from '@/components/drawers/TicketFormDrawer.vue'
import { TICKET_STATUSES, formatDateTime } from '@/constants/domain'
import { errorMessage } from '@/composables/useToast'
import { listCustomers } from '@/utils/api/customers'
import { listTickets } from '@/utils/api/tickets'

const tickets = ref([])
const customers = ref([])
const filterCustomers = ref([])
const meta = ref(null)
const page = ref(1)
const loading = ref(true)
const error = ref('')
const customersError = ref('')
const drawerOpen = ref(false)
const filters = reactive({ status: '', customer_id: '' })

const hasFilters = computed(() => Boolean(filters.status || filters.customer_id))

function onTicketSaved() {
  if (page.value !== 1) {
    page.value = 1
    return
  }
  fetchTickets()
}

async function fetchTickets() {
  loading.value = true
  error.value = ''
  try {
    const params = { page: page.value }
    if (filters.status) params.status = filters.status
    if (filters.customer_id) params.customer_id = filters.customer_id
    const response = await listTickets(params)
    tickets.value = response.data
    meta.value = response.meta
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar os chamados.')
  } finally {
    loading.value = false
  }
}

async function fetchCustomers() {
  try {
    const response = await listCustomers({ per_page: 100, status: 'active' })
    customers.value = response.data
  } catch (e) {
    customersError.value = errorMessage(e, 'Não foi possível carregar os clientes.')
  }
}

async function fetchFilterCustomers() {
  try {
    const response = await listCustomers({ per_page: 100, trashed: 'with' })
    filterCustomers.value = response.data
  } catch (e) {
    customersError.value = errorMessage(e, 'Não foi possível carregar os clientes para o filtro.')
  }
}

function clearFilters() {
  filters.status = ''
  filters.customer_id = ''
}

watch(filters, () => {
  if (page.value !== 1) {
    page.value = 1
    return
  }
  fetchTickets()
})
watch(page, fetchTickets)

onMounted(() => {
  fetchCustomers()
  fetchFilterCustomers()
  fetchTickets()
})
</script>

<template>
  <AppLayout title="Chamados" subtitle="Acompanhe as solicitações dos clientes">
    <template #actions>
      <BaseButton class="!shadow-none" @click="drawerOpen = true">
        <Plus class="size-4" />
        Abrir chamado
      </BaseButton>
    </template>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <select
        v-model="filters.status"
        class="field !h-8 !text-[13px] !leading-8 !shadow-none w-auto min-w-44"
        aria-label="Filtrar por status"
      >
        <option value="">Todos os status</option>
        <option v-for="status in TICKET_STATUSES" :key="status.value" :value="status.value">
          {{ status.label }}
        </option>
      </select>
      <select
        v-model="filters.customer_id"
        class="field !h-8 !text-[13px] !leading-8 !shadow-none w-auto min-w-52"
        aria-label="Filtrar por cliente"
      >
        <option value="">Todos os clientes</option>
        <option v-for="customer in filterCustomers" :key="customer.id" :value="customer.id">
          {{ customer.name }}
        </option>
      </select>
      <BaseButton v-if="hasFilters" variant="ghost" class="!shadow-none" @click="clearFilters">
        <X class="size-4" />
        Limpar
      </BaseButton>
      <span class="hidden text-xs text-gray-400 sm:inline">Mais filtros serão adicionados em uma próxima etapa.</span>
    </div>

    <AlertBanner v-if="customersError" class="mb-4">{{ customersError }}</AlertBanner>
    <AlertBanner v-if="error" class="mb-4">{{ error }}</AlertBanner>
    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="th">Número</th>
              <th class="th">Título</th>
              <th class="th">Cliente</th>
              <th class="th">Categoria</th>
              <th class="th">Prioridade</th>
              <th class="th">Status</th>
              <th class="th">Responsável</th>
              <th class="th">Última atualização</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="loading">
              <tr v-for="row in 5" :key="row">
                <td v-for="col in 8" :key="col" class="td">
                  <div class="h-3.5 w-24 animate-pulse rounded bg-gray-200" />
                </td>
              </tr>
            </template>
            <tr v-else-if="!tickets.length">
              <td colspan="8" class="px-4 py-14 text-center">
                <p class="text-sm font-medium text-gray-900">Nenhum chamado encontrado</p>
                <p class="mt-1 text-sm text-gray-500">
                  {{ hasFilters ? 'Ajuste os filtros ou limpe a seleção.' : 'Abra um chamado para começar.' }}
                </p>
              </td>
            </tr>
            <tr v-for="ticket in tickets" v-else :key="ticket.id" class="hover:bg-gray-50">
              <td class="td whitespace-nowrap">
                <router-link
                  :to="{ name: 'admin.tickets.show', params: { id: ticket.id } }"
                  class="font-mono text-[13px] font-medium text-theme-light-700 hover:underline"
                >
                  {{ ticket.reference }}
                </router-link>
              </td>
              <td class="td max-w-64">
                <router-link
                  :to="{ name: 'admin.tickets.show', params: { id: ticket.id } }"
                  class="block truncate font-medium text-gray-900 hover:text-theme-light-700"
                >
                  {{ ticket.title }}
                </router-link>
              </td>
              <td class="td max-w-48 truncate text-gray-700">{{ ticket.customer?.name || '—' }}</td>
              <td class="td whitespace-nowrap text-gray-600">{{ ticket.category_label }}</td>
              <td class="td whitespace-nowrap">
                <BadgeTag :label="ticket.priority_label" :tone="ticket.priority" />
              </td>
              <td class="td whitespace-nowrap">
                <BadgeTag :label="ticket.status_label" :tone="ticket.status" />
              </td>
              <td class="td whitespace-nowrap text-gray-600">{{ ticket.assignee?.name || 'Sem responsável' }}</td>
              <td class="td whitespace-nowrap text-[13px] text-gray-600">{{ formatDateTime(ticket.updated_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="meta" @change="page = $event" />
    </div>
    <TicketFormDrawer
      :open="drawerOpen"
      :customers="customers"
      @close="drawerOpen = false"
      @saved="onTicketSaved"
    />
  </AppLayout>
</template>
