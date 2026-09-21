<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import { Plus, Search, Settings2, TextQuote } from "@lucide/vue";
import AppLayout from "@/components/AppLayout.vue";
import BadgeTag from "@/components/BadgeTag.vue";
import BaseButton from "@/components/BaseButton.vue";
import {
  PROPOSAL_STATUSES,
  formatCurrency,
  formatDate,
} from "@/constants/domain";
import { listCustomers } from "@/utils/api/customers";
import { deleteProposal, listProposals } from "@/utils/api/proposals";

const proposals = ref([]);
const customers = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref("");
const page = ref(1);
const filters = reactive({ search: "", customer_id: "", status: "" });
let searchTimer;

async function fetchProposals() {
  loading.value = true;
  error.value = "";
  try {
    const params = { page: page.value };
    Object.entries(filters).forEach(([key, value]) => {
      if (value) params[key] = value;
    });
    const response = await listProposals(params);
    proposals.value = response.data;
    meta.value = response.meta;
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Não foi possível carregar as propostas.";
  } finally {
    loading.value = false;
  }
}

watch(
  () => filters.search,
  () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      page.value = 1;
      fetchProposals();
    }, 300);
  },
);
watch([() => filters.customer_id, () => filters.status], () => {
  page.value = 1;
  fetchProposals();
});
watch(page, fetchProposals);

async function onDelete(proposal) {
  if (!window.confirm(`Remover a proposta ${proposal.reference}?`)) return;
  try {
    await deleteProposal(proposal.id);
    fetchProposals();
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Não foi possível remover a proposta.";
  }
}

onMounted(async () => {
  try {
    const response = await listCustomers({ per_page: 100, status: "active" });
    customers.value = response.data;
  } catch {}
  fetchProposals();
});
</script>
<template>
  <AppLayout
    title="Propostas"
    subtitle="Propostas comerciais e condições negociadas"
  >
    <template #actions>
      <div class="flex gap-2">
        <router-link :to="{ name: 'admin.proposals.catalog' }"
          ><BaseButton type="button" variant="secondary" size="sm"
            ><Settings2 class="size-3.5" /> Catálogo</BaseButton
          ></router-link
        >
        <router-link :to="{ name: 'admin.proposals.templates' }"
          ><BaseButton type="button" variant="secondary" size="sm"
            ><TextQuote class="size-3.5" /> Modelos</BaseButton
          ></router-link
        >
        <router-link :to="{ name: 'admin.proposals.create' }"
          ><BaseButton type="button"
            ><Plus class="size-3.5" /> Nova proposta</BaseButton
          ></router-link
        >
      </div>
    </template>
    <div class="panel mb-4 p-3">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="lg:col-span-1">
          <label class="field-label" for="proposal-search"
            >Buscar proposta</label
          >
          <div class="relative">
            <Search
              class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-gray-400"
            /><input
              id="proposal-search"
              v-model="filters.search"
              type="search"
              placeholder="Título ou #PC-12"
              class="field pl-8"
            />
          </div>
        </div>
        <div>
          <label class="field-label" for="proposal-customer">Cliente</label
          ><select
            id="proposal-customer"
            v-model="filters.customer_id"
            class="field"
          >
            <option value="">Todos</option>
            <option
              v-for="customer in customers"
              :key="customer.id"
              :value="customer.id"
            >
              {{ customer.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="field-label" for="proposal-status">Situação</label
          ><select id="proposal-status" v-model="filters.status" class="field">
            <option value="">Todas</option>
            <option
              v-for="status in PROPOSAL_STATUSES"
              :key="status.value"
              :value="status.value"
            >
              {{ status.label }}
            </option>
          </select>
        </div>
      </div>
    </div>
    <p
      v-if="error"
      class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-600 ring-1 ring-red-200"
    >
      {{ error }}
    </p>
    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left">
            <tr class="border-b border-gray-200">
              <th class="px-4 py-2.5 text-[11px] font-medium text-gray-500">
                Proposta
              </th>
              <th class="px-4 py-2.5 text-[11px] font-medium text-gray-500">
                Emissão
              </th>
              <th class="px-4 py-2.5 text-[11px] font-medium text-gray-500">
                Validade
              </th>
              <th class="px-4 py-2.5 text-[11px] font-medium text-gray-500">
                Itens
              </th>
              <th class="px-4 py-2.5 text-[11px] font-medium text-gray-500">
                Total
              </th>
              <th class="px-4 py-2.5 text-[11px] font-medium text-gray-500">
                Situação
              </th>
              <th
                class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500"
              >
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <template v-if="loading"
              ><tr v-for="n in 4" :key="n">
                <td v-for="col in 7" :key="col" class="px-4 py-3">
                  <div class="h-3 w-20 animate-pulse rounded bg-gray-200" />
                </td></tr
            ></template>
            <tr v-else-if="!proposals.length">
              <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                Nenhuma proposta encontrada.
              </td>
            </tr>
            <tr
              v-for="proposal in proposals"
              v-else
              :key="proposal.id"
              class="cursor-pointer hover:bg-gray-50"
              @click="
                $router.push({
                  name: 'admin.proposals.show',
                  params: { id: proposal.id },
                })
              "
            >
              <td class="max-w-100 px-4 py-3">
                <span class="mono text-xs text-gray-500">{{
                  proposal.reference
                }}</span>
                <p class="truncate font-medium text-gray-900">
                  {{ proposal.title }}
                </p>
                <p class="truncate text-xs text-gray-500">
                  {{ proposal.customer?.name }}
                </p>
              </td>
              <td
                class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-700"
              >
                {{ formatDate(proposal.issued_on) }}
              </td>
              <td
                class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-700"
              >
                {{ formatDate(proposal.valid_until) }}
              </td>
              <td class="px-4 py-3 tabular-nums text-gray-600">
                {{ proposal.items_count }}
              </td>
              <td
                class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-700"
              >
                <span>{{ formatCurrency(proposal.totals?.total_cents) }}</span
                ><span
                  v-if="proposal.totals?.mrr_cents"
                  class="block text-xs text-gray-500"
                  >{{ formatCurrency(proposal.totals.mrr_cents) }}/mês</span
                >
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <BadgeTag
                  :label="proposal.status_label"
                  :tone="proposal.status"
                />
              </td>
              <td class="whitespace-nowrap px-4 py-3" @click.stop>
                <div class="flex justify-end gap-1">
                  <router-link
                    v-if="proposal.is_editable"
                    :to="{
                      name: 'admin.proposals.edit',
                      params: { id: proposal.id },
                    }"
                    ><BaseButton type="button" variant="ghost" size="sm"
                      >Editar</BaseButton
                    ></router-link
                  ><BaseButton
                    v-if="proposal.is_editable"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:bg-red-50"
                    @click="onDelete(proposal)"
                    >Remover</BaseButton
                  >
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div
      v-if="meta && meta.last_page > 1"
      class="mt-3 flex items-center justify-between"
    >
      <span class="text-xs text-gray-500"
        >Página {{ meta.current_page }} de {{ meta.last_page }}</span
      >
      <div class="flex gap-2">
        <BaseButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page <= 1"
          @click="page -= 1"
          >Anterior</BaseButton
        ><BaseButton
          type="button"
          variant="secondary"
          size="sm"
          :disabled="meta.current_page >= meta.last_page"
          @click="page += 1"
          >Próxima</BaseButton
        >
      </div>
    </div>
  </AppLayout>
</template>
