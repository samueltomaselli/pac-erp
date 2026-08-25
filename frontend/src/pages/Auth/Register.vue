<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

const router = useRouter()
const auth = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const submitting = ref(false)

async function onSubmit() {
  error.value = ''
  submitting.value = true

  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    router.push({ name: 'customer.home' })
  } catch (e) {
    const errors = e?.response?.data?.errors
    error.value = errors ? Object.values(errors).flat().join(' ') : 'Falha no cadastro.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-sm rounded-lg bg-white p-8 shadow">
      <h1 class="mb-6 text-2xl font-semibold text-gray-900">Criar conta</h1>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Nome</label>
          <input
            id="name"
            v-model="name"
            type="text"
            required
            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>

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

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700" for="password_confirmation">Confirmar senha</label>
          <input
            id="password_confirmation"
            v-model="passwordConfirmation"
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
          Cadastrar
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        Já tem uma conta?
        <router-link class="font-medium text-indigo-600 hover:underline" :to="{ name: 'login' }">Entrar</router-link>
      </p>
    </div>
  </div>
</template>
