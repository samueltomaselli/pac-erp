<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Check, ExternalLink, Link2, Pencil, Send, X } from '@lucide/vue'
import AlertBanner from '@/components/AlertBanner.vue'
import AppLayout from '@/components/AppLayout.vue'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import { formatCurrency, formatDate, formatDateTime } from '@/constants/domain'
import { useConfirm } from '@/composables/useConfirm'
import { errorMessage, useToast } from '@/composables/useToast'
import { acceptProposal, getProposal, rejectProposal, sendProposal } from '@/utils/api/proposals'

const route = useRoute()
const confirm = useConfirm()
const toast = useToast()

const proposal = ref(null)
const loading = ref(true)
const error = ref('')
const linkCopied = ref(false)
const transitioning = ref(null)

const publicUrl = computed(() =>
  proposal.value?.pub_id ? `${window.location.origin}/proposta/${proposal.value.pub_id}` : null,
)

const TRANSITIONS = {
  sent: {
    label: 'Enviar proposta',
    icon: Send,
    variant: 'primary',
    title: 'Enviar proposta',
    message: 'A proposta deixa de ser rascunho e não poderá mais ser editada. O link público fica disponível para o cliente.',
    confirmLabel: 'Enviar',
    done: 'Proposta enviada.',
    action: sendProposal,
  },
  accepted: {
    label: 'Marcar como aceita',
    icon: Check,
    variant: 'primary',
    title: 'Marcar como aceita',
    message: 'Registra o aceite manualmente, em nome do cliente.',
    confirmLabel: 'Marcar como aceita',
    done: 'Proposta marcada como aceita.',
    action: acceptProposal,
  },
  rejected: {
    label: 'Marcar como recusada',
    icon: X,
    variant: 'secondary',
    title: 'Marcar como recusada',
    message: 'A proposta será encerrada como recusada.',
    confirmLabel: 'Marcar como recusada',
    tone: 'danger',
    done: 'Proposta marcada como recusada.',
    action: rejectProposal,
  },
}

const transitions = computed(() =>
  (proposal.value?.allowed_transitions ?? [])
    .map((t) => ({ status: t.status, ...TRANSITIONS[t.status] }))
    .filter((t) => t.action),
)

async function copyPublicLink() {
  if (!publicUrl.value) return

  try {
    await navigator.clipboard.writeText(publicUrl.value)
    linkCopied.value = true
    toast.success('Link do cliente copiado.')
    setTimeout(() => (linkCopied.value = false), 2000)
  } catch {
    toast.error('Não foi possível copiar o link automaticamente.')
  }
}

async function load() {
  try {
    proposal.value = await getProposal(route.params.id)
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar a proposta.')
  } finally {
    loading.value = false
  }
}

async function transition(item) {
  const ok = await confirm({
    title: item.title,
    message: item.message,
    confirmLabel: item.confirmLabel,
    tone: item.tone,
  })

  if (!ok) return

  transitioning.value = item.status

  try {
    proposal.value = await item.action(proposal.value.id)
    toast.success(item.done)
  } catch (e) {
    toast.error(errorMessage(e, 'Não foi possível atualizar a proposta.'))
  } finally {
    transitioning.value = null
  }
}

onMounted(load)
</script>
<template>
  <AppLayout
    :title="proposal?.title || 'Proposta'"
    :subtitle="proposal ? `${proposal.reference} · ${proposal.customer?.name ?? ''}` : 'Detalhes da proposta comercial'"
    :back="{ name: 'admin.proposals.index' }"
  >
    <template #actions>
      <template v-if="proposal">
        <BaseButton v-if="publicUrl" variant="secondary" class="max-md:hidden!" @click="copyPublicLink">
          <component :is="linkCopied ? Check : Link2" class="size-4" />
          {{ linkCopied ? 'Copiado' : 'Copiar link do cliente' }}
        </BaseButton>
        <BaseButton v-if="publicUrl" variant="secondary" icon label="Copiar link do cliente" class="md:hidden!" @click="copyPublicLink">
          <Link2 class="size-4" />
        </BaseButton>
        <BaseButton v-if="proposal.is_editable" :to="{ name: 'admin.proposals.edit', params: { id: proposal.id } }">
          <Pencil class="size-4" />
          Editar
        </BaseButton>
      </template>
    </template>

    <div v-if="loading" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div class="panel h-80 animate-pulse bg-gray-100 lg:col-span-2" />
      <div class="panel h-80 animate-pulse bg-gray-100" />
    </div>
    <AlertBanner v-else-if="error">{{ error }}</AlertBanner>

    <div v-else-if="proposal" class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
      <aside class="space-y-4 lg:sticky lg:top-20 lg:order-last">
        <section class="panel p-5">
          <div class="flex items-center justify-between gap-2">
            <h2 class="stat-label">Situação</h2>
            <BadgeTag :label="proposal.status_label" :tone="proposal.status" />
          </div>
          <div v-if="transitions.length" class="mt-4 flex flex-col gap-2">
            <BaseButton
              v-for="item in transitions"
              :key="item.status"
              :variant="item.variant"
              :loading="transitioning === item.status"
              :disabled="Boolean(transitioning)"
              block
              @click="transition(item)"
            >
              <component :is="item.icon" class="size-4" />
              {{ item.label }}
            </BaseButton>
          </div>
          <p v-if="!proposal.is_editable" class="mt-3 text-xs text-gray-500">
            Só propostas em rascunho podem ser editadas.
          </p>
          <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4">
            <div>
              <dt class="stat-label">Emissão</dt>
              <dd class="mt-0.5 tabular-nums">{{ formatDate(proposal.issued_on) }}</dd>
            </div>
            <div>
              <dt class="stat-label">Validade</dt>
              <dd class="mt-0.5 tabular-nums">{{ formatDate(proposal.valid_until) }}</dd>
            </div>
            <div>
              <dt class="stat-label">Enviada em</dt>
              <dd class="mt-0.5 tabular-nums">{{ formatDateTime(proposal.sent_at) }}</dd>
            </div>
            <div>
              <dt class="stat-label">Decidida em</dt>
              <dd class="mt-0.5 tabular-nums">{{ formatDateTime(proposal.decided_at) }}</dd>
            </div>
          </dl>
          <a
            v-if="publicUrl"
            :href="publicUrl"
            target="_blank"
            rel="noopener"
            class="link mt-4 inline-flex items-center gap-1 text-xs"
          >
            Ver como o cliente vê <ExternalLink class="size-3" />
          </a>
        </section>

        <section class="panel divide-y divide-gray-100">
          <div class="p-5">
            <p class="stat-label">Total</p>
            <p class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-900">
              {{ formatCurrency(proposal.totals.total_cents) }}
            </p>
          </div>
          <div class="grid grid-cols-2 gap-3 p-5">
            <div>
              <p class="stat-label">Recorrente mensal</p>
              <p class="mt-0.5 font-semibold tabular-nums">{{ formatCurrency(proposal.totals.mrr_cents) }}</p>
            </div>
            <div>
              <p class="stat-label">Primeiro pagamento</p>
              <p class="mt-0.5 font-semibold tabular-nums">
                {{ formatCurrency(proposal.totals.first_payment_cents) }}
              </p>
            </div>
          </div>
        </section>
      </aside>

      <div class="space-y-4 lg:col-span-2">
        <section class="panel overflow-hidden">
          <h2 class="border-b border-gray-200 px-5 py-3 text-sm font-semibold text-gray-900">
            Itens <span class="ml-1 text-xs font-normal tabular-nums text-gray-500">{{ proposal.items?.length ?? 0 }}</span>
          </h2>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="th">Item</th>
                  <th class="th">Tipo</th>
                  <th class="th text-right">Qtd.</th>
                  <th class="th text-right">Valor</th>
                  <th class="th text-right">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="item in proposal.items" :key="item.id">
                  <td class="td">{{ item.description }}</td>
                  <td class="td whitespace-nowrap text-gray-600">{{ item.type_label }}</td>
                  <td class="td text-right tabular-nums">{{ item.quantity }}</td>
                  <td class="td whitespace-nowrap text-right tabular-nums">{{ formatCurrency(item.unit_amount_cents) }}</td>
                  <td class="td whitespace-nowrap text-right tabular-nums">
                    <span class="font-medium">{{ formatCurrency(item.line_total_cents) }}</span>
                    <span v-if="item.installments > 1" class="block text-xs text-gray-500">
                      {{ item.installments }}x de {{ formatCurrency(item.installment_amount_cents) }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!proposal.items?.length">
                  <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">Nenhum item na proposta.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel grid gap-5 p-5 sm:grid-cols-2">
          <div>
            <h3 class="text-sm font-semibold text-gray-900">Pagamento</h3>
            <p class="mt-2 text-sm text-gray-600">{{ proposal.payment_method_label || 'Não informado' }}</p>
            <p v-if="proposal.payment_notes" class="mt-1 whitespace-pre-wrap text-sm text-gray-600">
              {{ proposal.payment_notes }}
            </p>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-900">Observações</h3>
            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">
              {{ proposal.observations || 'Nenhuma observação.' }}
            </p>
          </div>
          <div class="sm:col-span-2">
            <h3 class="text-sm font-semibold text-gray-900">Condições gerais</h3>
            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">
              {{ proposal.terms || 'Nenhuma condição informada.' }}
            </p>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
