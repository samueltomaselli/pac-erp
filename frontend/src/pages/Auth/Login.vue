<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

async function onSubmit() {
  error.value = ''
  submitting.value = true

  try {
    const { role } = await auth.login({ email: email.value, password: password.value })
    router.push(role === 'admin' ? { name: 'admin.home' } : { name: 'customer.home' })
  } catch (e) {
    error.value = e?.response?.data?.message || 'Credenciais inválidas.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-sm rounded-lg bg-white p-8 shadow">
      <h1 class="mb-6 text-2xl font-semibold text-gray-900">Entrar</h1>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700" for="email">E-mail</label>
          <input
            id="email"
            v-model="email"
            type="email"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700" for="password">Senha</label>
          <input
            id="password"
            v-model="password"
            type="password"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <button
          type="submit"
          :disabled="submitting"
          class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
        >
          Entrar
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        Não tem uma conta?
        <router-link class="font-medium text-indigo-600 hover:underline" :to="{ name: 'register' }">Cadastre-se</router-link>
      </p>
    </div>
  </div>
</template>
