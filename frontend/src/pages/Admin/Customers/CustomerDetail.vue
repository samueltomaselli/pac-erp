<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ArrowLeft, Pencil } from '@lucide/vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import { formatCurrency, formatDate, formatDateTime } from '@/constants/domain'
import { getCustomer } from '@/utils/api/customers'
import { completeTask } from '@/utils/api/tasks'

const route = useRoute()

const customer = ref(null)
const loading = ref(true)
const error = ref('')

async function fetchCustomer() {
  loading.value = true
  error.value = ''

  try {
    customer.value = await getCustomer(route.params.id)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Não foi possível carregar o cliente.'
  } finally {
    loading.value = false
  }
}

async function onComplete(task) {
  await completeTask(task.id)
  fetchCustomer()
}

onMounted(fetchCustomer)
</script>
<template>
  <AppLayout
    :title="customer?.name ?? 'Cliente'"
    subtitle="Dados cadastrais e tarefas vinculadas"
  >
    <template #actions>
      <router-link
        v-if="customer"
        :to="{ name: 'admin.customers.edit', params: { id: customer.id } }"
      >
        <BaseButton type="button" variant="secondary">
          <Pencil class="size-3.5" />
          Editar
        </BaseButton>
      </router-link>
      <router-link :to="{ name: 'admin.customers.index' }">
        <BaseButton type="button" variant="ghost">
          <ArrowLeft class="size-3.5" />
          Voltar
        </BaseButton>
      </router-link>
    </template>
    <div v-if="loading" class="space-y-4">
      <div class="panel space-y-4 p-6">
        <div class="h-4 w-40 animate-pulse rounded bg-gray-200" />
        <div class="grid gap-4 sm:grid-cols-3">
          <div v-for="n in 6" :key="n" class="h-9 animate-pulse rounded bg-gray-200" />
        </div>
      </div>
    </div>
    <p
      v-else-if="error"
      class="rounded-lg bg-red-100 px-4 py-3 text-sm text-red-600 ring-1 ring-red-200"
    >
      {{ error }}
    </p>
    <div v-else-if="customer" class="space-y-4">
      <section v-if="customer.current_plan" class="panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="field-label">Plano atual</p>
            <h2 class="font-display text-lg font-semibold text-gray-900">
              {{ customer.current_plan.title }}
            </h2>
            <p class="mono mt-1 text-xs text-gray-500">
              {{ customer.current_plan.reference }}
            </p>
          </div>
          <div class="text-right">
            <p class="field-label">Valor mensal</p>
            <p class="text-lg font-semibold tabular-nums text-gray-900">
              {{ formatCurrency(customer.current_plan.mrr_cents) }}
            </p>
          </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">
          Ativo desde {{ formatDateTime(customer.current_plan.accepted_at) }}
        </p>
      </section>
      <p v-else class="px-1 text-sm text-gray-500">Sem plano ativo</p>
      <section class="panel p-6">
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <h2 class="text-sm font-semibold text-gray-900">Dados cadastrais</h2>
          <BadgeTag :label="customer.status_label" :tone="customer.status" />
          <BadgeTag v-if="customer.deleted_at" label="Inativado" tone="inactive" />
        </div>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
          <div>
            <dt class="text-[11px] font-medium text-gray-500">Contato</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ customer.contact_name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-[11px] font-medium text-gray-500">Cadastrado em</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(customer.created_at) }}</dd>
          </div>
        </dl>
      </section>
      <section class="panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-3">
          <h2 class="text-sm font-semibold text-gray-900">
            Tarefas
            <span class="ml-1 text-xs font-normal text-gray-500">
              <span class="tabular-nums">{{ customer.pending_tasks_count }}</span> pendente(s) de
              <span class="tabular-nums">{{ customer.tasks_count }}</span>
            </span>
          </h2>
          <router-link
            :to="{ name: 'admin.tasks.index', query: { customer_id: customer.id } }"
            class="text-xs font-medium text-theme-light-700 hover:underline"
          >
            Ver na agenda
          </router-link>
        </div>
        <p v-if="!customer.tasks.length" class="px-6 py-10 text-center text-sm text-gray-500">
          Nenhuma tarefa vinculada a este cliente.
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
                Prazo: {{ formatDate(task.due_date) }}
                <span v-if="task.completed_at">
                  · Concluída em {{ formatDateTime(task.completed_at) }}
                </span>
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
              <BadgeTag v-if="task.is_overdue" label="Vencida" tone="high" />
              <BadgeTag :label="task.priority_label" :tone="task.priority" />
              <BadgeTag :label="task.status_label" :tone="task.status" />
              <BaseButton
                v-if="task.status === 'pending'"
                type="button"
                variant="secondary"
                size="sm"
                class="ml-1"
                @click="onComplete(task)"
              >
                Concluir
              </BaseButton>
            </div>
          </li>
        </ul>
      </section>
      <section class="panel overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
          <h2 class="text-sm font-semibold text-gray-900">
            Propostas
            <span class="ml-1 text-xs font-normal text-gray-500">
              {{ customer.proposals_count }}
            </span>
          </h2>
        </div>
        <p
          v-if="!customer.proposals?.length"
          class="px-6 py-10 text-center text-sm text-gray-500"
        >
          Nenhuma proposta para este cliente
        </p>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white text-left">
              <tr class="border-b border-gray-200">
                <th class="px-4 py-2.5 text-[11px] font-medium text-gray-500">Proposta</th>
                <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">
                  Emissão
                </th>
                <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">
                  Validade
                </th>
                <th class="whitespace-nowrap px-4 py-2.5 text-[11px] font-medium text-gray-500">
                  Situação
                </th>
                <th class="whitespace-nowrap px-4 py-2.5 text-right text-[11px] font-medium text-gray-500">
                  Total
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr
                v-for="proposal in customer.proposals"
                :key="proposal.id"
                class="cursor-pointer hover:bg-gray-50"
                @click="$router.push({ name: 'admin.proposals.show', params: { id: proposal.id } })"
              >
                <td class="max-w-80 px-4 py-3">
                  <span class="mono text-xs text-gray-500">{{ proposal.reference }}</span>
                  <p class="truncate font-medium text-gray-900">{{ proposal.title }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-700">
                  {{ formatDate(proposal.issued_on) }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-700">
                  {{ formatDate(proposal.valid_until) }}
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                  <BadgeTag :label="proposal.status_label" :tone="proposal.status" />
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">
                  {{ formatCurrency(proposal.totals.total_cents) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>