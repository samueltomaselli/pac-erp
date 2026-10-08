<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import BadgeTag from '@/components/BadgeTag.vue'
import BaseButton from '@/components/BaseButton.vue'
import FormField from '@/components/FormField.vue'
import {
  DOCUMENT_MASK,
  formatCurrency,
  formatDate,
  formatDateTime,
  maskAttr,
} from '@/constants/domain'
import { acceptPublicProposal, getPublicProposal } from '@/utils/api/publicProposals'

const route = useRoute()
const proposal = ref(null)
const loading = ref(true)
const notFound = ref(false)
const pageError = ref('')
const formErrors = ref({})
const formMessage = ref('')
const submitting = ref(false)
const form = reactive({ name: '', document: '', email: '' })

async function loadProposal() {
  loading.value = true
  pageError.value = ''
  notFound.value = false

  try {
    proposal.value = await getPublicProposal(route.params.pubId)
  } catch (error) {
    if (error?.response?.status === 404) {
      notFound.value = true
    } else {
      pageError.value =
        error?.response?.data?.message || 'Não foi possível carregar a proposta.'
    }
  } finally {
    loading.value = false
  }
}

async function onAccept() {
  formErrors.value = {}
  formMessage.value = ''

  if (!window.confirm('Confirmar o aceite desta proposta comercial?')) {
    return
  }

  submitting.value = true

  try {
    await acceptPublicProposal(route.params.pubId, {
      name: form.name,
      document: form.document.replace(/\D/g, ''),
      email: form.email,
    })
    await loadProposal()
  } catch (error) {
    if (error?.response?.status === 422) {
      try {
        const refreshedProposal = await getPublicProposal(route.params.pubId)
        proposal.value = refreshedProposal

        if (refreshedProposal.status === 'pending' && refreshedProposal.can_accept) {
          formErrors.value = error.response.data.errors ?? {}
          formMessage.value =
            error.response.data.message || 'Revise os dados informados.'
        }
      } catch (refreshError) {
        pageError.value =
          refreshError?.response?.data?.message ||
          'Não foi possível atualizar o estado da proposta.'
      }
    } else {
      formMessage.value =
        error?.response?.data?.message || 'Não foi possível registrar o aceite.'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(loadProposal)
</script>

<template>
  <div class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 sm:py-10">
    <div class="mx-auto max-w-5xl">
      <header class="mb-6 flex items-center gap-3 border-b border-gray-200 pb-5">
        <div
          class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-theme-light-700 font-display text-lg font-semibold text-white"
          aria-label="Rauzee"
        >
          R
        </div>
        <div class="min-w-0">
          <p class="font-display text-base font-semibold text-gray-900">Rauzee</p>
          <p v-if="proposal" class="truncate text-xs text-gray-500">
            {{ proposal.reference }} · {{ proposal.customer.name }}
          </p>
        </div>
      </header>

      <div v-if="loading" class="panel h-64 animate-pulse bg-gray-100" />
      <section v-else-if="notFound" class="panel px-6 py-12 text-center">
        <h1 class="font-display text-xl font-semibold text-gray-900">
          Proposta não encontrada
        </h1>
        <p class="mt-2 text-sm text-gray-500">
          Confira o link recebido ou fale com o escritório.
        </p>
      </section>
      <p
        v-else-if="pageError"
        class="rounded-lg bg-red-100 px-4 py-3 text-sm text-red-600 ring-1 ring-red-200"
      >
        {{ pageError }}
      </p>

      <template v-else-if="proposal">
        <section class="panel mb-4 p-5 sm:p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="mono text-xs text-gray-500">{{ proposal.reference }}</p>
              <h1 class="mt-1 break-words font-display text-xl font-semibold text-gray-900 sm:text-2xl">
                {{ proposal.title }}
              </h1>
              <p class="mt-1 text-sm text-gray-600">{{ proposal.customer.name }}</p>
            </div>
            <BadgeTag :label="proposal.status_label" :tone="proposal.status" />
          </div>
          <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
            <div>
              <dt class="field-label">Emissão</dt>
              <dd class="tabular-nums">{{ formatDate(proposal.issued_on) }}</dd>
            </div>
            <div>
              <dt class="field-label">Validade</dt>
              <dd class="tabular-nums">{{ formatDate(proposal.valid_until) }}</dd>
            </div>
          </dl>
        </section>

        <section class="panel mb-4 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left">
                <tr>
                  <th class="px-4 py-3 text-xs font-medium text-gray-500">Item</th>
                  <th class="whitespace-nowrap px-4 py-3 text-xs font-medium text-gray-500">Tipo</th>
                  <th class="whitespace-nowrap px-4 py-3 text-xs font-medium text-gray-500">Qtd.</th>
                  <th class="whitespace-nowrap px-4 py-3 text-xs font-medium text-gray-500">Valor</th>
                  <th class="whitespace-nowrap px-4 py-3 text-xs font-medium text-gray-500">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="(item, index) in proposal.items" :key="index">
                  <td class="px-4 py-3">{{ item.description }}</td>
                  <td class="whitespace-nowrap px-4 py-3">{{ item.type_label }}</td>
                  <td class="px-4 py-3 tabular-nums">{{ item.quantity }}</td>
                  <td class="whitespace-nowrap px-4 py-3 tabular-nums">
                    {{ formatCurrency(item.unit_amount_cents) }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 tabular-nums">
                    {{ formatCurrency(item.line_total_cents) }}
                    <span v-if="item.installments > 1" class="block text-xs text-gray-500">
                      {{ item.installments }}x de
                      {{ formatCurrency(item.installment_amount_cents) }}
                      <template
                        v-if="item.last_installment_cents !== item.installment_amount_cents"
                      >
                        (última de {{ formatCurrency(item.last_installment_cents) }})
                      </template>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="mb-4 grid gap-3 sm:grid-cols-3">
          <div class="panel p-4">
            <p class="field-label">Total</p>
            <p class="text-lg font-semibold tabular-nums">
              {{ formatCurrency(proposal.totals.total_cents) }}
            </p>
          </div>
          <div class="panel p-4">
            <p class="field-label">Recorrente mensal</p>
            <p class="text-lg font-semibold tabular-nums">
              {{ formatCurrency(proposal.totals.mrr_cents) }}
            </p>
          </div>
          <div class="panel p-4">
            <p class="field-label">Primeiro pagamento</p>
            <p class="text-lg font-semibold tabular-nums">
              {{ formatCurrency(proposal.totals.first_payment_cents) }}
            </p>
          </div>
        </section>

        <section class="panel mb-4 grid gap-5 p-5 sm:grid-cols-2">
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Forma de pagamento</h2>
            <p class="mt-2 text-sm text-gray-600">
              {{ proposal.payment_method_label || 'Não informada' }}
            </p>
            <p v-if="proposal.payment_notes" class="mt-1 whitespace-pre-wrap text-sm text-gray-600">
              {{ proposal.payment_notes }}
            </p>
          </div>
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Observações</h2>
            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">
              {{ proposal.observations || 'Nenhuma observação.' }}
            </p>
          </div>
          <div class="sm:col-span-2">
            <h2 class="text-sm font-semibold text-gray-900">Condições gerais</h2>
            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">
              {{ proposal.terms || 'Nenhuma condição informada.' }}
            </p>
          </div>
        </section>

        <section v-if="proposal.status === 'accepted'" class="panel p-5">
          <h2 class="text-sm font-semibold text-gray-900">Aceite registrado</h2>
          <p class="mt-2 text-sm text-gray-600">
            Aceita por {{ proposal.acceptance.name }} em
            {{ formatDateTime(proposal.acceptance.accepted_at) }}.
          </p>
        </section>
        <section
          v-else-if="proposal.status === 'expired'"
          class="rounded-lg bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200"
        >
          <h2 class="font-semibold">O prazo desta proposta passou.</h2>
          <p class="mt-1">Fale com o escritório para receber orientações.</p>
        </section>
        <section v-else-if="proposal.can_accept" class="panel p-5 sm:p-6">
          <h2 class="font-display text-lg font-semibold text-gray-900">
            Aceite da proposta
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            Preencha seus dados para registrar o aceite comercial.
          </p>

          <p
            v-if="formMessage"
            class="mt-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-600 ring-1 ring-red-200"
          >
            {{ formMessage }}
          </p>

          <form class="mt-5 space-y-4" @submit.prevent="onAccept">
            <FormField label="Nome / Razão social" name="name" :errors="formErrors">
              <input
                id="name"
                v-model="form.name"
                type="text"
                required
                autocomplete="name"
                class="field"
                :class="formErrors.name && 'field-error'"
              />
            </FormField>
            <FormField label="CNPJ/CPF" name="document" :errors="formErrors">
              <input
                id="document"
                v-model="form.document"
                v-maska
                :data-maska="maskAttr(DOCUMENT_MASK)"
                type="text"
                inputmode="numeric"
                required
                autocomplete="off"
                placeholder="000.000.000-00"
                class="field mono"
                :class="formErrors.document && 'field-error'"
              />
            </FormField>
            <FormField label="E-mail" name="email" :errors="formErrors">
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                autocomplete="email"
                class="field"
                :class="formErrors.email && 'field-error'"
              />
            </FormField>
            <div class="flex justify-end border-t border-gray-200 pt-4">
              <BaseButton type="submit" :disabled="submitting">
                {{ submitting ? 'Registrando aceite…' : 'Aceitar proposta' }}
              </BaseButton>
            </div>
          </form>
        </section>
      </template>
    </div>
  </div>
</template>
