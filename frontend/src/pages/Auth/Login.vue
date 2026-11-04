<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import BaseButton from '@/components/BaseButton.vue'
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
  <AuthCard title="Entrar" subtitle="Acesse com o e-mail cadastrado">
    <form class="space-y-4" @submit.prevent="onSubmit">
      <div>
        <label class="field-label" for="email">E-mail</label>
        <input id="email" v-model="email" type="email" required class="field" />
      </div>
      <div>
        <label class="field-label" for="password">Senha</label>
        <input id="password" v-model="password" type="password" required class="field" />
      </div>
      <p
        v-if="error"
        class="rounded-md bg-red-100 px-3 py-2 text-xs text-red-600 ring-1 ring-red-200"
      >
        {{ error }}
      </p>
      <BaseButton type="submit" :disabled="submitting" block>Entrar</BaseButton>
    </form>
    <template #footer>
      Não tem uma conta?
      <router-link
        class="font-medium text-theme-light-700 hover:underline"
        :to="{ name: 'register' }"
      >
        Cadastre-se
      </router-link>
    </template>
  </AuthCard>
</template>