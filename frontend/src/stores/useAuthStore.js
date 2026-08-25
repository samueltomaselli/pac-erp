import { ref } from 'vue'
import { defineStore } from 'pinia'
import httpClient, { ensureCsrfCookie } from '@/utils/api/httpClient'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const role = ref(null)

  function setSession(data) {
    user.value = data?.user ?? null
    role.value = data?.role ?? null
  }

  function clearSession() {
    user.value = null
    role.value = null
  }

  async function sharedData() {
    try {
      const { data } = await httpClient.get('/api/me')
      setSession(data)
    } catch {
      clearSession()
    }

    return user.value
  }

  async function login(credentials) {
    await ensureCsrfCookie()
    const { data } = await httpClient.post('/api/login', credentials)
    setSession(data)

    return data
  }

  async function register(payload) {
    await ensureCsrfCookie()
    const { data } = await httpClient.post('/api/register', payload)
    setSession(data)

    return data
  }

  async function logout() {
    await ensureCsrfCookie()

    try {
      await httpClient.post('/api/logout')
    } finally {
      clearSession()
    }
  }

  return { user, role, sharedData, login, register, logout }
})
