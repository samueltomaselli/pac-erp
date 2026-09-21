<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { ArrowLeft, Plus, Save, Trash2 } from "@lucide/vue";
import AppLayout from "@/components/AppLayout.vue";
import BaseButton from "@/components/BaseButton.vue";
import FormField from "@/components/FormField.vue";
import {
  PAYMENT_METHODS,
  PROPOSAL_ITEM_TYPES,
  PROPOSAL_TEMPLATE_TYPES,
  currencyToCents,
  formatCurrency,
} from "@/constants/domain";
import { listCustomers } from "@/utils/api/customers";
import { listProposalCatalog } from "@/utils/api/proposalCatalog";
import { listProposalTemplates } from "@/utils/api/proposalTemplates";
import {
  addProposalItem,
  createProposal,
  getProposal,
  removeProposalItem,
  updateProposal,
  updateProposalItem,
} from "@/utils/api/proposals";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const id = computed(() => route.params.id || null);
const editing = computed(() => Boolean(id.value));
const proposal = ref(null);
const customers = ref([]);
const catalog = ref([]);
const templates = ref([]);
const errors = ref({});
const message = ref("");
const loading = ref(false);
const saving = ref(false);
const itemSaving = ref(false);
const itemErrors = ref({});
const itemEditor = ref(null);
const form = reactive({
  customer_id: "",
  title: "",
  issued_on: new Date().toISOString().slice(0, 10),
  valid_until: "",
  payment_method: "",
  payment_notes: "",
  observations: "",
  terms: "",
});
const blankItem = () => ({
  type: "one_time",
  description: "",
  quantity: 1,
  unit_amount: "",
  discount: "",
  installments: 1,
  catalog_item_id: null,
});

function setForm(value) {
  Object.assign(form, {
    customer_id: value.customer_id,
    title: value.title,
    issued_on: value.issued_on,
    valid_until: value.valid_until,
    payment_method: value.payment_method || "",
    payment_notes: value.payment_notes || "",
    observations: value.observations || "",
    terms: value.terms || "",
  });
}
function payload() {
  return { ...form, customer_id: Number(form.customer_id) };
}
async function load() {
  loading.value = true;
  try {
    const [customerResponse, catalogResponse, templateResponse] =
      await Promise.all([
        listCustomers({ per_page: 100, status: "active" }),
        listProposalCatalog({ per_page: 100, active: 1 }),
        listProposalTemplates({ per_page: 100 }),
      ]);
    customers.value = customerResponse.data;
    catalog.value = catalogResponse.data || catalogResponse;
    templates.value = templateResponse.data || templateResponse;
    if (editing.value) {
      proposal.value = await getProposal(id.value);
      setForm(proposal.value);
    }
  } catch (e) {
    message.value =
      e?.response?.data?.message || "Não foi possível carregar os dados.";
  } finally {
    loading.value = false;
  }
}
async function onSubmit() {
  errors.value = {};
  message.value = "";
  saving.value = true;
  try {
    const result = editing.value
      ? await updateProposal(id.value, payload())
      : await createProposal(payload());
    router.push({ name: "admin.proposals.edit", params: { id: result.id } });
  } catch (e) {
    errors.value = e?.response?.data?.errors || {};
    message.value =
      e?.response?.data?.message || "Não foi possível salvar a proposta.";
  } finally {
    saving.value = false;
  }
}
function editItem(item = null) {
  itemErrors.value = {};
  itemEditor.value = item
    ? {
        ...item,
        unit_amount: (item.unit_amount_cents / 100)
          .toFixed(2)
          .replace(".", ","),
        discount: (item.discount_cents / 100).toFixed(2).replace(".", ","),
      }
    : blankItem();
}
function applyCatalog(value) {
  const selected = catalog.value.find(
    (item) => String(item.id) === String(value),
  );
  if (selected)
    Object.assign(itemEditor.value, {
      catalog_item_id: selected.id,
      type: selected.type,
      description: selected.name,
      quantity: selected.default_quantity,
      unit_amount: (selected.default_unit_amount_cents / 100)
        .toFixed(2)
        .replace(".", ","),
      installments: 1,
    });
}
function itemPayload() {
  return {
    catalog_item_id: itemEditor.value.catalog_item_id,
    type: itemEditor.value.type,
    description: itemEditor.value.description,
    quantity: Number(itemEditor.value.quantity),
    unit_amount_cents: currencyToCents(itemEditor.value.unit_amount),
    discount_cents: currencyToCents(itemEditor.value.discount),
    installments:
      itemEditor.value.type === "one_time"
        ? Number(itemEditor.value.installments)
        : 1,
  };
}
async function saveItem() {
  itemErrors.value = {};
  itemSaving.value = true;
  try {
    proposal.value = itemEditor.value.id
      ? await updateProposalItem(
          proposal.value.id,
          itemEditor.value.id,
          itemPayload(),
        )
      : await addProposalItem(proposal.value.id, itemPayload());
    itemEditor.value = null;
  } catch (e) {
    itemErrors.value = e?.response?.data?.errors || {};
    message.value =
      e?.response?.data?.message || "Não foi possível salvar o item.";
  } finally {
    itemSaving.value = false;
  }
}
async function removeItem(item) {
  if (!window.confirm(`Remover o item "${item.description}"?`)) return;
  try {
    proposal.value = await removeProposalItem(proposal.value.id, item.id);
  } catch (e) {
    message.value =
      e?.response?.data?.message || "Não foi possível remover o item.";
  }
}
function useTemplate(type) {
  const selected = templates.value.find((template) => template.type === type);
  if (selected)
    form[type === "observations" ? "observations" : "terms"] = selected.content;
}
onMounted(load);
</script>
<template>
  <AppLayout
    :title="editing ? 'Editar proposta' : 'Nova proposta'"
    subtitle="Dados comerciais e itens da proposta"
  >
    <template #actions
      ><router-link :to="{ name: 'admin.proposals.index' }"
        ><BaseButton type="button" variant="secondary"
          ><ArrowLeft class="size-3.5" /> Voltar</BaseButton
        ></router-link
      ></template
    >
    <div v-if="loading" class="panel h-64 animate-pulse bg-gray-100" />
    <template v-else
      ><p
        v-if="message"
        class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-600"
      >
        {{ message }}
      </p>
      <div
        v-if="proposal && !proposal.is_editable"
        class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200"
      >
        Somente propostas em rascunho podem ser editadas.
      </div>
      <form class="panel p-6" @submit.prevent="onSubmit">
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField label="Cliente" name="customer_id" :errors="errors"
            ><select
              v-model="form.customer_id"
              class="field"
              :disabled="!(!proposal || proposal.is_editable)"
              required
            >
              <option value="" disabled>Selecione…</option>
              <option
                v-for="customer in customers"
                :key="customer.id"
                :value="customer.id"
              >
                {{ customer.name }}
              </option>
            </select></FormField
          ><FormField label="Título" name="title" :errors="errors"
            ><input
              v-model="form.title"
              class="field"
              :disabled="proposal && !proposal.is_editable"
              required /></FormField
          ><FormField label="Data de emissão" name="issued_on" :errors="errors"
            ><input
              v-model="form.issued_on"
              type="date"
              class="field"
              :disabled="proposal && !proposal.is_editable"
              required /></FormField
          ><FormField label="Validade" name="valid_until" :errors="errors"
            ><input
              v-model="form.valid_until"
              type="date"
              class="field"
              :disabled="proposal && !proposal.is_editable"
              required /></FormField
          ><FormField
            label="Forma de pagamento"
            name="payment_method"
            :errors="errors"
            ><select
              v-model="form.payment_method"
              class="field"
              :disabled="proposal && !proposal.is_editable"
            >
              <option value="">Selecione…</option>
              <option
                v-for="method in PAYMENT_METHODS"
                :key="method.value"
                :value="method.value"
              >
                {{ method.label }}
              </option>
            </select></FormField
          ><FormField
            label="Observações de pagamento"
            name="payment_notes"
            :errors="errors"
            ><input
              v-model="form.payment_notes"
              class="field"
              :disabled="proposal && !proposal.is_editable"
          /></FormField>
          <div>
            <FormField label="Observações" name="observations" :errors="errors">
              <textarea
                v-model="form.observations"
                rows="4"
                class="field"
                :disabled="proposal && !proposal.is_editable"
              /></FormField
            ><button
              type="button"
              class="mt-1 text-xs text-theme-light-700 hover:underline"
              :disabled="proposal && !proposal.is_editable"
              @click="useTemplate('observations')"
            >
              Usar modelo (copia o texto)
            </button>
          </div>
          <div>
            <FormField label="Condições gerais" name="terms" :errors="errors">
              <textarea
                v-model="form.terms"
                rows="4"
                class="field"
                :disabled="proposal && !proposal.is_editable"
              /></FormField
            ><button
              type="button"
              class="mt-1 text-xs text-theme-light-700 hover:underline"
              :disabled="proposal && !proposal.is_editable"
              @click="useTemplate('general_conditions')"
            >
              Usar modelo (copia o texto)
            </button>
          </div>
        </div>
        <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
          <BaseButton
            type="submit"
            :disabled="saving || (proposal && !proposal.is_editable)"
            ><Save class="size-3.5" /> Salvar proposta</BaseButton
          >
        </div>
      </form>
      <template v-if="proposal"
        ><div class="mt-4 panel overflow-hidden">
          <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4"
          >
            <h2 class="font-medium">Itens da proposta</h2>
            <BaseButton
              type="button"
              size="sm"
              :disabled="!proposal.is_editable"
              @click="editItem()"
              ><Plus class="size-3.5" /> Adicionar item</BaseButton
            >
          </div>
          <div
            v-if="itemEditor"
            class="border-b border-gray-200 bg-gray-50 p-5"
          >
            <div class="grid gap-3 sm:grid-cols-3">
              <div>
                <label class="field-label">Catálogo</label
                ><select
                  class="field"
                  @change="applyCatalog($event.target.value)"
                >
                  <option value="">Preencher do catálogo…</option>
                  <option
                    v-for="item in catalog"
                    :key="item.id"
                    :value="item.id"
                  >
                    {{ item.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="field-label">Tipo</label
                ><select v-model="itemEditor.type" class="field">
                  <option
                    v-for="type in PROPOSAL_ITEM_TYPES"
                    :key="type.value"
                    :value="type.value"
                  >
                    {{ type.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="field-label">Descrição</label
                ><input v-model="itemEditor.description" class="field" />
              </div>
              <div>
                <label class="field-label">Quantidade</label
                ><input
                  v-model="itemEditor.quantity"
                  type="number"
                  min="1"
                  class="field"
                />
              </div>
              <div>
                <label class="field-label">Valor unitário</label
                ><input
                  v-model="itemEditor.unit_amount"
                  inputmode="decimal"
                  class="field"
                  placeholder="0,00"
                />
              </div>
              <div>
                <label class="field-label">Desconto</label
                ><input
                  v-model="itemEditor.discount"
                  inputmode="decimal"
                  class="field"
                  placeholder="0,00"
                />
              </div>
              <div v-if="itemEditor.type === 'one_time'">
                <label class="field-label">Parcelas</label
                ><input
                  v-model="itemEditor.installments"
                  type="number"
                  min="1"
                  max="12"
                  class="field"
                />
              </div>
            </div>
            <p
              v-if="itemErrors.quantity || itemErrors.discount_cents"
              class="mt-2 text-xs text-red-600"
            >
              {{ (itemErrors.quantity || itemErrors.discount_cents)[0] }}
            </p>
            <div class="mt-4 flex justify-end gap-2">
              <BaseButton
                type="button"
                variant="secondary"
                size="sm"
                @click="itemEditor = null"
                >Cancelar</BaseButton
              ><BaseButton
                type="button"
                size="sm"
                :disabled="itemSaving"
                @click="saveItem"
                >Salvar item</BaseButton
              >
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left">
                <tr>
                  <th class="px-4 py-2.5 text-xs text-gray-500">Descrição</th>
                  <th class="px-4 py-2.5 text-xs text-gray-500">Tipo</th>
                  <th class="px-4 py-2.5 text-xs text-gray-500">Qtd.</th>
                  <th class="px-4 py-2.5 text-xs text-gray-500">Total</th>
                  <th class="px-4 py-2.5 text-right text-xs text-gray-500">
                    Ações
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="item in proposal.items" :key="item.id">
                  <td class="px-4 py-3">{{ item.description }}</td>
                  <td class="px-4 py-3">{{ item.type_label }}</td>
                  <td class="px-4 py-3 tabular-nums">{{ item.quantity }}</td>
                  <td class="px-4 py-3 tabular-nums">
                    {{ formatCurrency(item.line_total_cents) }}
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-1">
                      <BaseButton
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="!proposal.is_editable"
                        @click="editItem(item)"
                        >Editar</BaseButton
                      ><BaseButton
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-red-600"
                        :disabled="!proposal.is_editable"
                        @click="removeItem(item)"
                        ><Trash2 class="size-3.5"
                      /></BaseButton>
                    </div>
                  </td>
                </tr>
                <tr v-if="!proposal.items?.length">
                  <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                    Nenhum item adicionado.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            class="grid gap-3 border-t border-gray-200 bg-gray-50 p-5 sm:grid-cols-3"
          >
            <div>
              <p class="field-label">Total</p>
              <p class="font-semibold tabular-nums">
                {{ formatCurrency(proposal.totals.total_cents) }}
              </p>
            </div>
            <div>
              <p class="field-label">Recorrente mensal</p>
              <p class="font-semibold tabular-nums">
                {{ formatCurrency(proposal.totals.mrr_cents) }}
              </p>
            </div>
            <div v-if="proposal.totals.max_installments > 1">
              <p class="field-label">Primeiro pagamento</p>
              <p class="font-semibold tabular-nums">
                {{ formatCurrency(proposal.totals.first_payment_cents) }}
              </p>
              <p class="text-xs text-gray-500">
                Durante parcelas:
                {{ formatCurrency(proposal.totals.during_installments_cents) }}
              </p>
            </div>
          </div>
        </div></template
      >
    </template>
  </AppLayout>
</template>
