<script setup>
import { onMounted, ref, watch } from 'vue'
import { Plus, Search } from '@lucide/vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import { CUSTOMER_SEGMENTS, CUSTOMER_STATUSES } from '@/constants/domain'
import { deactivateCustomer, listCustomers, restoreCustomer } from '@/utils/api/customers'

const customers = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')

const filters = ref({ search: '', status: '', segment: '', trashed: '' })
const page = ref(1)

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
    error.value = e?.response?.data?.message || 'Não foi possível carregar os clientes.'
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

async function onDeactivate(customer) {
  if (!window.confirm(`Inativar o cliente "${customer.name}"? O histórico é preservado.`)) {
    return
  }

  await deactivateCustomer(customer.id)
  fetchCustomers()
}

async function onRestore(customer) {
  await restoreCustomer(customer.id)
  fetchCustomers()
}

onMounted(fetchCustomers)
</script>
<template>
  <AppLayout title="Clientes" subtitle="Cadastro e acompanhamento da carteira">
    <template #actions>
      <router-link :to="{ name: 'admin.customers.create' }">
        <BaseButton type="button">
          <Plus class="size-3.5" />
          Novo cliente
        </BaseButton>
      </router-link>
    </template>
    <div class="panel mb-4 p-3">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
          <label class="field-label" for="search">Buscar por nome ou CNPJ/CPF</label>
          <div class="relative">
            <Search
              class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-gray-400"
            />
            <input
              id="search"
              v-model="filters.search"
              type="search"
              placeholder="Ex.: Alvorada ou 11.222.333/0001-81"
              class="field pl-8"
            />
          </div>
        </div>
        <div>
          <label class="field-label" for="status">Status</label>
          <select id="status" v-model="filters.status" class="field">
            <option value="">Todos</option>
            <option v-for="option in CUSTOMER_STATUSES" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="field-label" for="segment">Segmento</label>
          <select id="segment" v-model="filters.segment" class="field">
            <option value="">Todos</option>
            <option v-for="option in CUSTOMER_SEGMENTS" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>
      <label class="mt-3 flex w-fit items-center gap-2 text-xs text-gray-600">
        <input
          v-model="filters.trashed"
          type="checkbox"
          true-value="with"
          false-value=""
          class="size-3.5 rounded border-gray-300 text-theme-light-600 focus:ring-theme-light-500/30"
        />
        Incluir clientes inativados
      </label>
    </div>
    <p v-if="error" class="mb-4 rounded-md border border-red-200 bg-red-100 px-4 py-3 text-sm text-red-600">
      {{ error }}
    </p>
    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left">
            <tr class="border-b border-gray-200">
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Cliente</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">CNPJ/CPF</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Segmento</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Status</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">Tarefas</th>
              <th class="whitespace-nowrap px-4 py-2.5 text-right text-[11px] font-medium text-gray-500">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <template v-if="loading">
              <tr v-for="n in 4" :key="`skeleton-${n}`">
                <td v-for="col in 6" :key="col" class="px-4 py-3">
                  <div
                    class="h-3 animate-pulse rounded bg-gray-200"
                    :class="col === 1 ? 'w-40' : 'w-20'"
                  />
                </td>
              </tr>
            </template>
            <tr v-else-if="!customers.length">
              <td class="px-4 py-10 text-center text-sm text-gray-500" colspan="6">
                Nenhum cliente encontrado.
              </td>
            </tr>
            <tr v-for="customer in customers" v-else :key="customer.id" class="hover:bg-gray-50">
              <td class="max-w-[20rem] px-4 py-3">
                <router-link
                  :to="{ name: 'admin.customers.show', params: { id: customer.id } }"
                  class="block truncate font-medium text-gray-900 hover:text-theme-light-700 hover:underline"
                >
                  {{ customer.name }}
                </router-link>
                <p class="truncate text-xs text-gray-500">{{ customer.email }}</p>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <span class="mono text-xs text-gray-600">{{ customer.document_formatted }}</span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ customer.segment_label }}</td>
              <td class="whitespace-nowrap px-4 py-3">
                <BadgeTag :label="customer.status_label" :tone="customer.status" />
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-600">
                <span class="tabular-nums">{{ customer.pending_tasks_count }}</span> pendente(s)
                de <span class="tabular-nums">{{ customer.tasks_count }}</span>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <div class="flex justify-end gap-1">
                  <router-link :to="{ name: 'admin.customers.edit', params: { id: customer.id } }">
                    <BaseButton type="button" variant="ghost" size="sm">Editar</BaseButton>
                  </router-link>
                  <BaseButton
                    v-if="customer.deleted_at"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-green-700 hover:bg-green-50"
                    @click="onRestore(customer)"
                  >
                    Reativar
                  </BaseButton>
                  <BaseButton
                    v-else
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:bg-red-50"
                    @click="onDeactivate(customer)"
                  >
                    Inativar
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-if="meta && meta.last_page > 1" class="mt-3 flex items-center justify-between">
      <span class="text-xs text-gray-500">
        Página <span class="tabular-nums">{{ meta.current_page }}</span> de
        <span class="tabular-nums">{{ meta.last_page }}</span>
      </span>
      <div class="flex gap-2">
        <BaseButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page <= 1"
          @click="page -= 1"
        >
          Anterior
        </BaseButton>
        <BaseButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page >= meta.last_page"
          @click="page += 1"
        >
          Próxima
        </BaseButton>
      </div>
    </div>
  </AppLayout>
</template>