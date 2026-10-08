<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArchiveRestore, Ban, Pencil, Plus, Search, X } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import CustomerFormDrawer from '@/components/drawers/CustomerFormDrawer.vue'
import { CUSTOMER_SEGMENTS, CUSTOMER_STATUSES } from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import { deactivateCustomer, listCustomers, restoreCustomer } from '@/utils/api/customers'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const customers = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')

const blankFilters = () => ({ search: '', status: '', segment: '', trashed: '' })
const filters = ref(blankFilters())
const page = ref(1)

const drawer = ref({ open: route.query.novo === '1', customerId: null })

const hasFilters = computed(() => Object.values(filters.value).some(Boolean))

let searchTimer

async function fetchCustomers() {
  loading.value = true
  error.value = ''

  try {
    const params = { page: page.value }

    for (const [key, value] of Object.entries(filters.value)) {
      if (value) {
        params[key] = value
      }
    }

    const response = await listCustomers(params)
    customers.value = response.data
    meta.value = response.meta
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar os clientes.')
  } finally {
    loading.value = false
  }
}

watch(
  () => filters.value.search,
  () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      page.value = 1
      fetchCustomers()
    }, 300)
  },
)

watch(
  [() => filters.value.status, () => filters.value.segment, () => filters.value.trashed],
  () => {
    page.value = 1
    fetchCustomers()
  },
)

watch(page, fetchCustomers)

function openCreate() {
  drawer.value = { open: true, customerId: null }
}

function openEdit(customer) {
  drawer.value = { open: true, customerId: customer.id }
}

function closeDrawer() {
  drawer.value.open = false

  if (route.query.novo) {
    router.replace({ query: {} })
  }
}

function openCustomer(customer) {
  router.push({ name: 'admin.customers.show', params: { id: customer.id } })
}

async function onDeactivate(customer) {
  const ok = await confirm({
    title: 'Inativar cliente',
    message: `"${customer.name}" perderá o acesso ao sistema. O histórico de tarefas e propostas é preservado e o cliente pode ser reativado depois.`,
    confirmLabel: 'Inativar',
    tone: 'danger',
  })

  if (!ok) {
    return
  }

  try {
    await deactivateCustomer(customer.id)
    toast.success('Cliente inativado.')
    fetchCustomers()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível inativar o cliente.'))
  }
}

async function onRestore(customer) {
  try {
    await restoreCustomer(customer.id)
    toast.success('Cliente reativado.')
    fetchCustomers()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível reativar o cliente.'))
  }
}

onMounted(fetchCustomers)
</script>
<template>
  <AppLayout title="Clientes" subtitle="Cadastro e acompanhamento da carteira">
    <template #actions>
      <BaseButton @click="openCreate">
        <Plus class="size-4" />
        <span class="hidden sm:inline">Novo cliente</span>
        <span class="sm:hidden">Novo</span>
      </BaseButton>
    </template>

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <div class="relative min-w-60 flex-1">
        <label class="sr-only" for="search">Buscar por nome ou CNPJ/CPF</label>
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
        <input
          id="search"
          v-model="filters.search"
          type="search"
          placeholder="Buscar por nome ou CNPJ/CPF"
          class="field pl-9"
        />
      </div>
      <select v-model="filters.status" class="field w-auto min-w-36" aria-label="Status">
        <option value="">Todos os status</option>
        <option v-for="option in CUSTOMER_STATUSES" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
      <select v-model="filters.segment" class="field w-auto min-w-40" aria-label="Segmento">
        <option value="">Todos os segmentos</option>
        <option v-for="option in CUSTOMER_SEGMENTS" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
      <label class="flex h-9 items-center gap-2 rounded-md border border-gray-300 bg-white px-3 text-[13px] text-gray-700 shadow-xs">
        <input v-model="filters.trashed" type="checkbox" true-value="with" false-value="" class="checkbox" />
        Incluir inativados
      </label>
      <BaseButton v-if="hasFilters" variant="ghost" @click="filters = blankFilters()">
        <X class="size-4" />
        Limpar
      </BaseButton>
    </div>

    <AlertBanner v-if="error" class="mb-4">{{ error }}</AlertBanner>

    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="th">Cliente</th>
              <th class="th">CNPJ/CPF</th>
              <th class="th">Segmento</th>
              <th class="th">Status</th>
              <th class="th">Tarefas</th>
              <th class="th w-px"><span class="sr-only">Ações</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="loading">
              <tr v-for="n in 5" :key="`skeleton-${n}`">
                <td v-for="col in 6" :key="col" class="td">
                  <div class="h-3.5 animate-pulse rounded bg-gray-200" :class="col === 1 ? 'w-44' : 'w-20'" />
                </td>
              </tr>
            </template>
            <tr v-else-if="!customers.length">
              <td class="px-4 py-14 text-center" colspan="6">
                <p class="text-sm font-medium text-gray-900">Nenhum cliente encontrado</p>
                <p class="mt-1 text-sm text-gray-500">
                  {{ hasFilters ? 'Ajuste ou limpe os filtros.' : 'Cadastre o primeiro cliente da carteira.' }}
                </p>
                <BaseButton v-if="!hasFilters" class="mt-4" @click="openCreate">
                  <Plus class="size-4" /> Novo cliente
                </BaseButton>
              </td>
            </tr>
            <tr
              v-for="customer in customers"
              v-else
              :key="customer.id"
              class="group cursor-pointer transition-colors hover:bg-gray-50"
              :class="customer.deleted_at && 'text-gray-500'"
              @click="openCustomer(customer)"
            >
              <td class="td max-w-80">
                <router-link
                  :to="{ name: 'admin.customers.show', params: { id: customer.id } }"
                  class="block truncate font-medium text-gray-900 group-hover:text-theme-light-700"
                  @click.stop
                >
                  {{ customer.name }}
                </router-link>
                <p class="truncate text-xs text-gray-500">{{ customer.email }}</p>
              </td>
              <td class="td whitespace-nowrap">
                <span class="mono text-xs text-gray-600">{{ customer.document_formatted }}</span>
              </td>
              <td class="td whitespace-nowrap text-gray-700">{{ customer.segment_label }}</td>
              <td class="td whitespace-nowrap">
                <BadgeTag v-if="customer.deleted_at" label="Inativado" tone="inactive" />
                <BadgeTag v-else :label="customer.status_label" :tone="customer.status" />
              </td>
              <td class="td whitespace-nowrap text-[13px] text-gray-600">
                <span
                  class="font-medium tabular-nums"
                  :class="customer.pending_tasks_count ? 'text-gray-900' : 'text-gray-400'"
                >
                  {{ customer.pending_tasks_count }}
                </span>
                pendente(s) de <span class="tabular-nums">{{ customer.tasks_count }}</span>
              </td>
              <td class="td whitespace-nowrap" @click.stop>
                <div class="flex justify-end gap-0.5">
                  <BaseButton variant="ghost" size="sm" icon label="Editar" @click="openEdit(customer)">
                    <Pencil class="size-4" />
                  </BaseButton>
                  <BaseButton
                    v-if="customer.deleted_at"
                    variant="ghost"
                    size="sm"
                    icon
                    label="Reativar"
                    class="hover:text-green-700"
                    @click="onRestore(customer)"
                  >
                    <ArchiveRestore class="size-4" />
                  </BaseButton>
                  <BaseButton
                    v-else
                    variant="ghost-danger"
                    size="sm"
                    icon
                    label="Inativar"
                    @click="onDeactivate(customer)"
                  >
                    <Ban class="size-4" />
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="meta" @change="page = $event" />
    </div>

    <CustomerFormDrawer
      :open="drawer.open"
      :customer-id="drawer.customerId"
      @close="closeDrawer"
      @saved="fetchCustomers"
    />
  </AppLayout>
</template>
