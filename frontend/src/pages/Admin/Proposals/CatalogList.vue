<script setup>
import { onMounted, reactive, ref } from "vue";
import { ArrowLeft, Plus, Trash2 } from "@lucide/vue";
import AppLayout from "@/components/AppLayout.vue";
import BaseButton from "@/components/BaseButton.vue";
import {
  PROPOSAL_ITEM_TYPES,
  currencyToCents,
  formatCurrency,
} from "@/constants/domain";
import {
  createProposalCatalog,
  deleteProposalCatalog,
  listProposalCatalog,
  updateProposalCatalog,
} from "@/utils/api/proposalCatalog";
const items = ref([]);
const error = ref("");
const editing = ref(null);
const form = reactive({
  name: "",
  description: "",
  type: "recurring",
  default_quantity: 1,
  default_unit_amount: "",
  allows_installments: false,
  is_active: true,
  sort_order: 0,
});
function reset(item = null) {
  editing.value = item?.id || null;
  Object.assign(
    form,
    item
      ? {
          ...item,
          default_unit_amount: (item.default_unit_amount_cents / 100)
            .toFixed(2)
            .replace(".", ","),
        }
      : {
          name: "",
          description: "",
          type: "recurring",
          default_quantity: 1,
          default_unit_amount: "",
          allows_installments: false,
          is_active: true,
          sort_order: 0,
        },
  );
}
async function load() {
  try {
    const result = await listProposalCatalog({ per_page: 100 });
    items.value = result.data || result;
  } catch {
    error.value = "Não foi possível carregar o catálogo.";
  }
}
async function save() {
  try {
    const payload = {
      ...form,
      default_quantity: Number(form.default_quantity),
      default_unit_amount_cents: currencyToCents(form.default_unit_amount),
      sort_order: Number(form.sort_order),
    };
    if (editing.value) await updateProposalCatalog(editing.value, payload);
    else await createProposalCatalog(payload);
    reset();
    load();
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Não foi possível salvar o item.";
  }
}
async function remove(item) {
  if (!window.confirm(`Remover "${item.name}"?`)) return;
  await deleteProposalCatalog(item.id);
  load();
}
onMounted(load);
</script>
<template>
  <AppLayout title="Catálogo de itens" subtitle="Padrões para montar propostas"
    ><template #actions
      ><router-link :to="{ name: 'admin.proposals.index' }"
        ><BaseButton type="button" variant="secondary"
          ><ArrowLeft class="size-3.5" /> Propostas</BaseButton
        ></router-link
      ></template
    >
    <p v-if="error" class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-red-600">
      {{ error }}
    </p>
    <form class="panel mb-4 p-5" @submit.prevent="save">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="field-label">Nome</label
          ><input v-model="form.name" required class="field" />
        </div>
        <div>
          <label class="field-label">Tipo</label
          ><select v-model="form.type" class="field">
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
          <label class="field-label">Quantidade padrão</label
          ><input
            v-model="form.default_quantity"
            type="number"
            min="1"
            class="field"
          />
        </div>
        <div>
          <label class="field-label">Valor padrão</label
          ><input
            v-model="form.default_unit_amount"
            inputmode="decimal"
            class="field"
            placeholder="0,00"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="field-label">Descrição</label
          ><textarea v-model="form.description" rows="2" class="field" />
        </div>
        <label class="flex items-center gap-2 text-xs text-gray-600"
          ><input v-model="form.allows_installments" type="checkbox" /> Permite
          parcelamento</label
        ><label class="flex items-center gap-2 text-xs text-gray-600"
          ><input v-model="form.is_active" type="checkbox" /> Ativo</label
        >
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <BaseButton
          v-if="editing"
          type="button"
          variant="secondary"
          @click="reset()"
          >Cancelar</BaseButton
        ><BaseButton type="submit"
          ><Plus v-if="!editing" class="size-3.5" />
          {{
            editing ? "Salvar alterações" : "Adicionar ao catálogo"
          }}</BaseButton
        >
      </div>
    </form>
    <div class="panel overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left">
          <tr>
            <th class="px-4 py-2.5 text-xs text-gray-500">Item</th>
            <th class="px-4 py-2.5 text-xs text-gray-500">Tipo</th>
            <th class="px-4 py-2.5 text-xs text-gray-500">Valor</th>
            <th class="px-4 py-2.5 text-xs text-gray-500">Status</th>
            <th class="px-4 py-2.5 text-right text-xs text-gray-500">Ações</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="item in items"
            :key="item.id"
            :class="!item.is_active && 'opacity-50'"
          >
            <td class="px-4 py-3">
              <p class="font-medium">{{ item.name }}</p>
              <p class="text-xs text-gray-500">{{ item.description }}</p>
            </td>
            <td class="px-4 py-3">{{ item.type_label || item.type }}</td>
            <td class="px-4 py-3 tabular-nums">
              {{ formatCurrency(item.default_unit_amount_cents) }}
            </td>
            <td class="px-4 py-3">
              {{ item.is_active ? "Ativo" : "Inativo" }}
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <BaseButton
                  type="button"
                  variant="ghost"
                  size="sm"
                  @click="reset(item)"
                  >Editar</BaseButton
                ><BaseButton
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="text-red-600"
                  @click="remove(item)"
                  ><Trash2 class="size-3.5"
                /></BaseButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table></div
  ></AppLayout>
</template>
