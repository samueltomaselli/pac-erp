<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Pencil, Plus, Search, Trash2, X } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { PROPOSAL_STATUSES, formatCurrency, formatDate } from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import { listCustomers } from '@/utils/api/customers'
import { deleteProposal, listProposals } from '@/utils/api/proposals'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const proposals = ref([])
const customers = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')
const page = ref(1)
const filters = reactive({
  search: '',
  customer_id: route.query.customer_id ? Number(route.query.customer_id) : '',
  status: PROPOSAL_STATUSES.some((s) => s.value === route.query.status) ? route.query.status : '',
})
const hasFilters = computed(() => Object.values(filters).some(Boolean))
let searchTimer

async function fetchProposals() {
  loading.value = true
  error.value = ''

  try {
    const params = { page: page.value }

    Object.entries(filters).forEach(([key, value]) => {
      if (value) params[key] = value
    })

    const response = await listProposals(params)
    proposals.value = response.data
    meta.value = response.meta
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar as propostas.')
  } finally {
    loading.value = false
  }
}

watch(
  () => filters.search,
  () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      page.value = 1
      fetchProposals()
    }, 300)
  },
)

watch([() => filters.customer_id, () => filters.status], () => {
  page.value = 1
  fetchProposals()
})

watch(page, fetchProposals)

function clearFilters() {
  Object.assign(filters, { search: '', customer_id: '', status: '' })
}

function openProposal(proposal) {
  router.push({ name: 'admin.proposals.show', params: { id: proposal.id } })
}

async function onDelete(proposal) {
  const ok = await confirm({
    title: 'Remover proposta',
    message: `A proposta ${proposal.reference} — "${proposal.title}" será removida com todos os itens.`,
    confirmLabel: 'Remover',
    tone: 'danger',
  })

  if (!ok) return

  try {
    await deleteProposal(proposal.id)
    toast.success('Proposta removida.')
    fetchProposals()
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível remover a proposta.'))
  }
}

onMounted(async () => {
  try {
    const response = await listCustomers({ per_page: 100, status: 'active' })
    customers.value = response.data
  } catch {}

  fetchProposals()
})
</script>
<template>
  <AppLayout title="Propostas" subtitle="Propostas comerciais e condições negociadas">
    <template #actions>
      <BaseButton :to="{ name: 'admin.proposals.create' }">
        <Plus class="size-4" />
        <span class="hidden sm:inline">Nova proposta</span>
        <span class="sm:hidden">Nova</span>
      </BaseButton>
    </template>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <div class="relative min-w-60 flex-1">
        <label class="sr-only" for="proposal-search">Buscar proposta</label>
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
        <input
          id="proposal-search"
          v-model="filters.search"
          type="search"
          placeholder="Buscar por título ou número (#PC-12)"
          class="field pl-9"
        />
      </div>
      <select v-model="filters.customer_id" class="field w-auto min-w-48" aria-label="Cliente">
        <option value="">Todos os clientes</option>
        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
          {{ customer.name }}
        </option>
      </select>
      <BaseButton v-if="hasFilters" variant="ghost" @click="clearFilters">
        <X class="size-4" />
        Limpar
      </BaseButton>
    </div>
    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-gray-200">
      <button
        v-for="option in [{ value: '', label: 'Todas' }, ...PROPOSAL_STATUSES]"
        :key="option.value"
        type="button"
        class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-[13px] font-medium transition-colors"
        :class="
          filters.status === option.value
            ? 'border-theme-light-600 text-theme-light-800'
            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800'
        "
        @click="filters.status = option.value"
      >
        {{ option.label }}
      </button>
    </div>

    <AlertBanner v-if="error" class="mb-4">{{ error }}</AlertBanner>

    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="th">Proposta</th>
              <th class="th">Emissão</th>
              <th class="th">Validade</th>
              <th class="th text-right">Itens</th>
              <th class="th text-right">Total</th>
              <th class="th">Situação</th>
              <th class="th w-px"><span class="sr-only">Ações</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-if="loading">
              <tr v-for="n in 5" :key="n">
                <td v-for="col in 7" :key="col" class="td">
                  <div class="h-3.5 animate-pulse rounded bg-gray-200" :class="col === 1 ? 'w-52' : 'w-16'" />
                </td>
              </tr>
            </template>
            <tr v-else-if="!proposals.length">
              <td colspan="7" class="px-4 py-14 text-center">
                <p class="text-sm font-medium text-gray-900">Nenhuma proposta encontrada</p>
                <p class="mt-1 text-sm text-gray-500">
                  {{ hasFilters ? 'Ajuste ou limpe os filtros.' : 'Monte a primeira proposta comercial.' }}
                </p>
                <BaseButton v-if="!hasFilters" class="mt-4" :to="{ name: 'admin.proposals.create' }">
                  <Plus class="size-4" /> Nova proposta
                </BaseButton>
              </td>
            </tr>
            <tr
              v-for="proposal in proposals"
              v-else
              :key="proposal.id"
              class="group cursor-pointer transition-colors hover:bg-gray-50"
              @click="openProposal(proposal)"
            >
              <td class="td max-w-md">
                <router-link
                  :to="{ name: 'admin.proposals.show', params: { id: proposal.id } }"
                  class="block truncate font-medium text-gray-900 group-hover:text-theme-light-700"
                  @click.stop
                >
                  {{ proposal.title }}
                </router-link>
                <p class="truncate text-xs text-gray-500">
                  <span class="mono">{{ proposal.reference }}</span> · {{ proposal.customer?.name }}
                </p>
              </td>
              <td class="td whitespace-nowrap tabular-nums text-gray-700">{{ formatDate(proposal.issued_on) }}</td>
              <td class="td whitespace-nowrap tabular-nums text-gray-700">{{ formatDate(proposal.valid_until) }}</td>
              <td class="td text-right tabular-nums text-gray-600">{{ proposal.items_count }}</td>
              <td class="td whitespace-nowrap text-right tabular-nums">
                <span class="font-medium text-gray-900">{{ formatCurrency(proposal.totals?.total_cents) }}</span>
                <span v-if="proposal.totals?.mrr_cents" class="block text-xs text-gray-500">
                  {{ formatCurrency(proposal.totals.mrr_cents) }}/mês
                </span>
              </td>
              <td class="td whitespace-nowrap">
                <BadgeTag :label="proposal.status_label" :tone="proposal.status" />
              </td>
              <td class="td whitespace-nowrap" @click.stop>
                <div v-if="proposal.is_editable" class="flex justify-end gap-0.5">
                  <BaseButton
                    variant="ghost"
                    size="sm"
                    icon
                    label="Editar"
                    :to="{ name: 'admin.proposals.edit', params: { id: proposal.id } }"
                  >
                    <Pencil class="size-4" />
                  </BaseButton>
                  <BaseButton variant="ghost-danger" size="sm" icon label="Remover" @click="onDelete(proposal)">
                    <Trash2 class="size-4" />
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <PaginationBar :meta="meta" @change="page = $event" />
    </div>
  </AppLayout>
</template>
