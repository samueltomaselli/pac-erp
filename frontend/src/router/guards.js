import { useAuthStore } from '@/stores/useAuthStore'

export async function roleGuard(to) {
  const requiredRole = to.meta.role

  if (!requiredRole) {
    return true
  }

  const auth = useAuthStore()

  if (!auth.user) {
    await auth.sharedData()
  }

  if (!auth.user) {
    return { name: 'login' }
  }

  if (auth.role !== requiredRole) {
    return { name: auth.role === 'admin' ? 'admin.home' : 'customer.home' }
  }

  return true
}
