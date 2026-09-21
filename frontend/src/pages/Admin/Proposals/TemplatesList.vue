<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { ArrowLeft, Plus, Trash2 } from "@lucide/vue";
import AppLayout from "@/components/AppLayout.vue";
import BaseButton from "@/components/BaseButton.vue";
import { PROPOSAL_TEMPLATE_TYPES } from "@/constants/domain";
import {
  createProposalTemplate,
  deleteProposalTemplate,
  listProposalTemplates,
  updateProposalTemplate,
} from "@/utils/api/proposalTemplates";
const templates = ref([]);
const error = ref("");
const editing = ref(null);
const form = reactive({ name: "", type: "observations", content: "" });
const groups = computed(() =>
  PROPOSAL_TEMPLATE_TYPES.map((type) => ({
    ...type,
    items: templates.value.filter((item) => item.type === type.value),
  })),
);
function reset(item = null) {
  editing.value = item?.id || null;
  Object.assign(
    form,
    item
      ? { name: item.name, type: item.type, content: item.content }
      : { name: "", type: "observations", content: "" },
  );
}
async function load() {
  try {
    const result = await listProposalTemplates({ per_page: 100 });
    templates.value = result.data || result;
  } catch {
    error.value = "Não foi possível carregar os modelos.";
  }
}
async function save() {
  try {
    if (editing.value) await updateProposalTemplate(editing.value, form);
    else await createProposalTemplate(form);
    reset();
    load();
  } catch (e) {
    error.value =
      e?.response?.data?.message || "Não foi possível salvar o modelo.";
  }
}
async function remove(item) {
  if (!window.confirm(`Remover o modelo "${item.name}"?`)) return;
  await deleteProposalTemplate(item.id);
  load();
}
onMounted(load);
</script>
<template>
  <AppLayout
    title="Modelos de texto"
    subtitle="Textos reutilizáveis para propostas"
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
    <form class="panel mb-5 p-5" @submit.prevent="save">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="field-label">Nome</label
          ><input v-model="form.name" required class="field" />
        </div>
        <div>
          <label class="field-label">Tipo</label
          ><select v-model="form.type" class="field">
            <option
              v-for="type in PROPOSAL_TEMPLATE_TYPES"
              :key="type.value"
              :value="type.value"
            >
              {{ type.label }}
            </option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="field-label">Conteúdo</label
          ><textarea v-model="form.content" rows="7" required class="field" />
        </div>
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
          {{ editing ? "Salvar alterações" : "Novo modelo" }}</BaseButton
        >
      </div>
    </form>
    <section v-for="group in groups" :key="group.value" class="mb-5">
      <h2 class="mb-2 font-display text-base font-semibold">
        {{ group.label }}
      </h2>
      <div class="space-y-2">
        <article
          v-for="item in group.items"
          :key="item.id"
          class="panel flex items-start justify-between gap-4 p-4"
        >
          <div>
            <h3 class="font-medium">{{ item.name }}</h3>
            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">
              {{ item.content }}
            </p>
          </div>
          <div class="flex shrink-0 gap-1">
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
        </article>
        <p v-if="!group.items.length" class="text-sm text-gray-500">
          Nenhum modelo cadastrado.
        </p>
      </div>
    </section></AppLayout
  >
</template>
