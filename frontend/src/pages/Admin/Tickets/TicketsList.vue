<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Plus, X } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import TicketFormDrawer from '@/components/drawers/TicketFormDrawer.vue'
import { TICKET_PRIORITIES, TICKET_STATUSES, formatDateTime } from '@/constants/domain'
import { errorMessage } from '@/composables/useToast'
import { listCustomers } from '@/utils/api/customers'
import { listStaff, listTickets } from '@/utils/api/tickets'

const route = useRoute()
const router = useRouter()
const tickets = ref([])
const customers = ref([])
const filterCustomers = ref([])
const staff = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')
const customersError = ref('')
const staffError = ref('')
const drawerOpen = ref(false)
const searchTerm = ref('')
const filters = reactive({
  status: '',
  active: '',
  priority: '',
  assigned_to: '',
  customer_id: '',
  q: '',
  sort: 'recent',
})

let searchTimer
let requestId = 0

const currentPage = computed(() => {
  const parsedPage = Number.parseInt(route.query.page, 10)
  return Number.isInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1
})
const hasFilters = computed(() =>
  Boolean(
    filters.status ||
      filters.active ||
      filters.priority ||
      filters.assigned_to ||
      filters.customer_id ||
      filters.q ||
      searchTerm.value ||
      filters.sort !== 'recent',
  ),
)

function queryValue(value) {
  return Array.isArray(value) ? value[0] ?? '' : value ?? ''
}

function sameQuery(left, right) {
  const normalize = (query) =>
    Object.fromEntries(Object.keys(query).sort().map((key) => [key, query[key]]))

  return JSON.stringify(normalize(left)) === JSON.stringify(normalize(right))
}

function queryFilters() {
  const query = { ...route.query }
  for (const key of ['status', 'active', 'priority', 'assigned_to', 'customer_id', 'q', 'sort', 'page']) {
    delete query[key]
  }

  for (const key of ['status', 'active', 'priority', 'assigned_to', 'customer_id', 'q']) {
    if (filters[key]) query[key] = filters[key]
  }
  if (filters.sort !== 'recent') query.sort = filters.sort
  if (currentPage.value > 1) query.page = String(currentPage.value)

  return query
}

function syncFromRoute(searchChanged) {
  filters.status = String(queryValue(route.query.status))
  filters.active = queryValue(route.query.active) === '1' ? '1' : ''
  filters.priority = String(queryValue(route.query.priority))
  filters.assigned_to = String(queryValue(route.query.assigned_to))
  filters.customer_id = String(queryValue(route.query.customer_id))
  filters.q = String(queryValue(route.query.q))
  filters.sort = String(queryValue(route.query.sort) || 'recent')
  if (searchChanged) {
    clearTimeout(searchTimer)
    searchTerm.value = filters.q
  }
}

function onTicketSaved() {
  if (currentPage.value !== 1) {
    router.replace({ query: { ...route.query, page: undefined } })
    return
  }
  fetchTickets()
}

async function fetchTickets() {
  const activeRequest = ++requestId
  loading.value = true
  error.value = ''
  try {
    const params = { ...filters, page: currentPage.value }
    for (const key of Object.keys(params)) {
      if (!params[key]) delete params[key]
    }
    if (params.sort === 'recent') delete params.sort
    const response = await listTickets(params)
    if (activeRequest !== requestId) return
    tickets.value = response.data
    meta.value = response.meta
  } catch (e) {
    if (activeRequest === requestId) {
      error.value = errorMessage(e, 'Não foi possível carregar os chamados.')
    }
  } finally {
    if (activeRequest === requestId) loading.value = false
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

async function fetchStaff() {
  try {
    staff.value = await listStaff()
  } catch (e) {
    staffError.value = errorMessage(e, 'Não foi possível carregar a lista de responsáveis.')
  }
}

function clearFilters() {
  clearTimeout(searchTimer)
  searchTerm.value = ''
  filters.status = ''
  filters.active = ''
  filters.priority = ''
  filters.assigned_to = ''
  filters.customer_id = ''
  filters.q = ''
  filters.sort = 'recent'
}

function changePage(page) {
  router.push({ query: { ...route.query, page: page > 1 ? String(page) : undefined } })
}

watch(
  () => searchTerm.value,
  (value) => {
    clearTimeout(searchTimer)
    if (value.trim() === filters.q) return
    searchTimer = setTimeout(() => {
      filters.q = value.trim()
    }, 300)
  },
)

watch(
  filters,
  () => {
    const query = queryFilters()
    if (!sameQuery(query, route.query)) {
      router.replace({ query: { ...query, page: undefined } })
    }
  },
  { deep: true },
)

watch(
  () => route.query,
  (query, previousQuery) => {
    const searchChanged = queryValue(query.q) !== queryValue(previousQuery?.q)
    syncFromRoute(searchChanged)
    fetchTickets()
  },
  { deep: true, immediate: true },
)

onMounted(() => {
  fetchCustomers()
  fetchFilterCustomers()
  fetchStaff()
})
</script>

<template>
  <AppLayout title="Chamados" subtitle="Acompanhe as solicitações dos clientes">
    <template #actions>
      <BaseButton size="sm" class="!shadow-none" @click="drawerOpen = true">
        <Plus class="size-4" />
        Abrir chamado
      </BaseButton>
    </template>

    <div class="mb-4 flex min-w-0 flex-wrap items-center gap-2">
      <input
        v-model="searchTerm"
        type="search"
        placeholder="Buscar por título ou número"
        aria-label="Buscar chamado por título ou número"
        class="field h-8 w-full min-w-0 max-w-full !text-[13px] !leading-8 !shadow-none sm:w-64"
      />
      <select
        v-model="filters.status"
        class="field h-8 w-full min-w-0 max-w-full !text-[13px] !leading-8 !shadow-none sm:w-auto sm:min-w-40"
        aria-label="Filtrar por status"
      >
        <option value="">Todos os status</option>
        <option v-for="status in TICKET_STATUSES" :key="status.value" :value="status.value">
          {{ status.label }}
        </option>
      </select>
      <label class="inline-flex h-8 items-center gap-2 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 text-[13px] text-gray-700">
        <input v-model="filters.active" type="checkbox" true-value="1" false-value="" class="size-3.5 rounded border-gray-300" />
        Só em andamento
      </label>
      <select
        v-model="filters.priority"
        class="field h-8 w-full min-w-0 max-w-full !text-[13px] !leading-8 !shadow-none sm:w-auto sm:min-w-36"
        aria-label="Filtrar por prioridade"
      >
        <option value="">Todas as prioridades</option>
        <option v-for="priority in TICKET_PRIORITIES" :key="priority.value" :value="priority.value">
          {{ priority.label }}
        </option>
      </select>
      <select
        v-model="filters.assigned_to"
        class="field h-8 w-full min-w-0 max-w-full !text-[13px] !leading-8 !shadow-none sm:w-auto sm:min-w-44"
        aria-label="Filtrar por responsável"
      >
        <option value="">Todos os responsáveis</option>
        <option value="none">Sem responsável</option>
        <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option>
      </select>
      <select
        v-model="filters.customer_id"
        class="field h-8 w-full min-w-0 max-w-full !text-[13px] !leading-8 !shadow-none sm:w-auto sm:min-w-48"
        aria-label="Filtrar por cliente"
      >
        <option value="">Todos os clientes</option>
        <option v-for="customer in filterCustomers" :key="customer.id" :value="customer.id">
          {{ customer.name }}
        </option>
      </select>
      <select
        v-model="filters.sort"
        class="field h-8 w-full min-w-0 max-w-full !text-[13px] !leading-8 !shadow-none sm:w-auto sm:min-w-40"
        aria-label="Ordenar chamados"
      >
        <option value="recent">Mais recentes</option>
        <option value="updated">Última atualização</option>
        <option value="priority">Prioridade</option>
      </select>
      <BaseButton v-if="hasFilters" variant="ghost" size="sm" class="!shadow-none" @click="clearFilters">
        <X class="size-4" />
        Limpar filtros
      </BaseButton>
    </div>

    <AlertBanner v-if="customersError" class="mb-4">{{ customersError }}</AlertBanner>
    <AlertBanner v-if="staffError" class="mb-4">{{ staffError }}</AlertBanner>
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
                <p class="text-sm font-medium text-gray-900">
                  {{ hasFilters ? 'Nenhum chamado com esses filtros' : 'Nenhum chamado encontrado' }}
                </p>
                <p class="mt-1 text-sm text-gray-500">
                  {{ hasFilters ? 'Ajuste os filtros ou limpe a seleção.' : 'Abra um chamado para começar.' }}
                </p>
              </td>
            </tr>
            <tr v-for="ticket in tickets" v-else :key="ticket.id" class="hover:bg-gray-50">
              <td class="td whitespace-nowrap">
                <router-link
                  :to="{ name: 'admin.tickets.show', params: { id: ticket.id }, query: route.query }"
                  class="font-mono text-[13px] font-medium text-theme-light-700 hover:underline"
                >
                  {{ ticket.reference }}
                </router-link>
              </td>
              <td class="td max-w-64">
                <router-link
                  :to="{ name: 'admin.tickets.show', params: { id: ticket.id }, query: route.query }"
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
              <td class="td whitespace-nowrap" :class="ticket.assignee ? 'text-gray-600' : 'text-gray-400'">
                {{ ticket.assignee?.name || 'Sem responsável' }}
              </td>
              <td class="td whitespace-nowrap text-[13px] text-gray-600">{{ formatDateTime(ticket.updated_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="meta" @change="changePage" />
    </div>
    <TicketFormDrawer
      :open="drawerOpen"
      :customers="customers"
      @close="drawerOpen = false"
      @saved="onTicketSaved"
    />
  </AppLayout>
</template>
