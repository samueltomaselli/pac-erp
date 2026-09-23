<script setup>
import { onMounted, ref } from "vue";
import { ArrowLeft, Check, Pencil, X } from "@lucide/vue";
import AppLayout from "@/components/AppLayout.vue";
import BadgeTag from "@/components/BadgeTag.vue";
import BaseButton from "@/components/BaseButton.vue";
import { formatCurrency, formatDate, formatDateTime } from "@/constants/domain";
import {
  acceptProposal,
  getProposal,
  rejectProposal,
  sendProposal,
} from "@/utils/api/proposals";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const proposal = ref(null);
const loading = ref(true);
const error = ref("");
async function load() {
  try {
    proposal.value = await getProposal(route.params.id);
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Não foi possível carregar a proposta.";
  } finally {
    loading.value = false;
  }
}
async function transition(status) {
  const labels = {
    sent: "enviar",
    accepted: "marcar como aceita",
    rejected: "marcar como recusada",
  };
  if (!window.confirm(`Confirmar ${labels[status]} esta proposta?`)) return;
  error.value = "";
  try {
    proposal.value =
      status === "sent"
        ? await sendProposal(proposal.value.id)
        : status === "accepted"
          ? await acceptProposal(proposal.value.id)
          : await rejectProposal(proposal.value.id);
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Não foi possível atualizar a proposta.";
  }
}
onMounted(load);
</script>
<template>
  <AppLayout
    :title="proposal?.reference || 'Proposta'"
    subtitle="Detalhes da proposta comercial"
  >
    <template #actions
      ><div class="flex gap-2">
        <router-link :to="{ name: 'admin.proposals.index' }"
          ><BaseButton type="button" variant="secondary"
            ><ArrowLeft class="size-3.5" /> Voltar</BaseButton
          ></router-link
        ><router-link
          v-if="proposal?.is_editable"
          :to="{ name: 'admin.proposals.edit', params: { id: proposal.id } }"
          ><BaseButton type="button"
            ><Pencil class="size-3.5" /> Editar</BaseButton
          ></router-link
        >
      </div></template
    >
    <div v-if="loading" class="panel h-64 animate-pulse bg-gray-100" />
    <p v-else-if="error" class="rounded-lg bg-red-100 px-4 py-3 text-red-600">
      {{ error }}
    </p>
    <template v-else-if="proposal"
      ><div
        v-if="!proposal.is_editable"
        class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200"
      >
        Esta proposta não pode mais ser editada porque não está em rascunho.
      </div>
      <p v-if="error" class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-red-600">
        {{ error }}
      </p>
      <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="mono text-xs text-gray-500">{{ proposal.reference }}</p>
          <h2 class="font-display text-xl font-semibold text-gray-900">
            {{ proposal.title }}
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            {{ proposal.customer?.name }}
          </p>
        </div>
        <div class="flex items-center gap-3">
          <BadgeTag
            :label="proposal.status_label"
            :tone="proposal.status"
          /><template
            v-for="allowedTransition in proposal.allowed_transitions"
            :key="allowedTransition.status"
            ><BaseButton
              v-if="allowedTransition.status === 'sent'"
              type="button"
              @click="transition(allowedTransition.status)"
              >Enviar</BaseButton
            ><BaseButton
              v-else-if="allowedTransition.status === 'accepted'"
              type="button"
              @click="transition(allowedTransition.status)"
              ><Check class="size-3.5" /> Marcar como aceita</BaseButton
            ><BaseButton
              v-else
              type="button"
              variant="danger"
              @click="transition(allowedTransition.status)"
              ><X class="size-3.5" /> Marcar como recusada</BaseButton
            ></template
          >
        </div>
      </div>
      <div class="panel mb-4 grid gap-4 p-5 sm:grid-cols-4">
        <div>
          <p class="field-label">Emissão</p>
          <p>{{ formatDate(proposal.issued_on) }}</p>
        </div>
        <div>
          <p class="field-label">Validade</p>
          <p>{{ formatDate(proposal.valid_until) }}</p>
        </div>
        <div>
          <p class="field-label">Enviada em</p>
          <p>{{ formatDateTime(proposal.sent_at) }}</p>
        </div>
        <div>
          <p class="field-label">Decidida em</p>
          <p>{{ formatDateTime(proposal.decided_at) }}</p>
        </div>
      </div>
      <div class="panel mb-4 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left">
              <tr>
                <th class="px-4 py-2.5 text-xs text-gray-500">Item</th>
                <th class="px-4 py-2.5 text-xs text-gray-500">Tipo</th>
                <th class="px-4 py-2.5 text-xs text-gray-500">Qtd.</th>
                <th class="px-4 py-2.5 text-xs text-gray-500">Valor</th>
                <th class="px-4 py-2.5 text-xs text-gray-500">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="item in proposal.items" :key="item.id">
                <td class="px-4 py-3">{{ item.description }}</td>
                <td class="px-4 py-3">{{ item.type_label }}</td>
                <td class="px-4 py-3 tabular-nums">{{ item.quantity }}</td>
                <td class="px-4 py-3 tabular-nums">
                  {{ formatCurrency(item.unit_amount_cents) }}
                </td>
                <td class="px-4 py-3 tabular-nums">
                  {{ formatCurrency(item.line_total_cents)
                  }}<span
                    v-if="item.installments > 1"
                    class="block text-xs text-gray-500"
                    >{{ item.installments }}x de
                    {{ formatCurrency(item.installment_amount_cents) }}</span
                  >
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="mb-4 grid gap-3 sm:grid-cols-3">
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
      </div>
      <div class="panel grid gap-5 p-5 sm:grid-cols-2">
        <div>
          <h3 class="font-medium">Observações</h3>
          <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">
            {{ proposal.observations || "Nenhuma observação." }}
          </p>
        </div>
        <div>
          <h3 class="font-medium">Condições gerais</h3>
          <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">
            {{ proposal.terms || "Nenhuma condição informada." }}
          </p>
        </div>
      </div>
    </template>
  </AppLayout>
</template>
