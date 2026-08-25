<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const router = useRouter()
const auth = useAuthStore()

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-gray-50">
    <h1 class="text-2xl font-semibold text-gray-900">Área do cliente</h1>
    <p class="text-sm text-gray-600">Conectado como {{ auth.user?.email }}</p>
    <button
      class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
      @click="onLogout"
    >
      Sair
    </button>
  </div>
</template>
