<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import {
  CheckSquare,
  FileText,
  LayoutDashboard,
  LogOut,
  Users,
} from "@lucide/vue";
import AppHeader from "@/components/AppHeader.vue";
import AppSidebar from "@/components/AppSidebar.vue";
import { useAuthStore } from "@/stores/useAuthStore";

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: "" },
});

const router = useRouter();
const auth = useAuthStore();

const sidebarOpen = ref(false);

const navigation = [
  { name: "admin.home", label: "Início", icon: LayoutDashboard },
  {
    name: "admin.customers.index",
    label: "Clientes",
    icon: Users,
    match: "/admin/clientes",
  },
  {
    name: "admin.proposals.index",
    label: "Propostas",
    icon: FileText,
    match: "/admin/propostas",
  },
  {
    name: "admin.tasks.index",
    label: "Tarefas",
    icon: CheckSquare,
    match: "/admin/tarefas",
  },
];

async function onLogout() {
  await auth.logout();
  router.push({ name: "login" });
}
</script>
<template>
  <div class="flex min-h-screen bg-gray-50">
    <AppSidebar v-model:open="sidebarOpen" :navigation="navigation" />
    <div class="flex min-w-0 flex-1 flex-col">
      <AppHeader
        :title="title"
        :subtitle="subtitle"
        @toggle="sidebarOpen = !sidebarOpen"
      >
        <template #actions>
          <slot name="actions" />
          <div
            class="ml-1 flex items-center gap-x-2 border-l border-gray-200 pl-3"
          >
            <span
              class="hidden max-w-45 truncate text-xs text-gray-500 sm:block"
            >
              {{ auth.user?.email }}
            </span>
            <button
              type="button"
              class="inline-flex size-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-light-500/30"
              @click="onLogout"
            >
              <span class="sr-only">Sair</span>
              <LogOut class="size-4" />
            </button>
          </div>
        </template>
      </AppHeader>
      <main class="flex-1 px-3 py-6 sm:px-4 lg:px-6">
        <slot />
      </main>
    </div>
  </div>
</template>
