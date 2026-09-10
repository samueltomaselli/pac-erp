import { createRouter, createWebHistory } from 'vue-router'
import { roleGuard } from './guards'

const routes = [
  {
    path: '/',
    redirect: '/login',
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/Auth/Login.vue'),
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/pages/Auth/Register.vue'),
  },
  {
    path: '/admin',
    name: 'admin.home',
    component: () => import('@/pages/AdminHome.vue'),
    meta: { role: 'admin' },
  },
  {
    path: '/admin/clientes',
    name: 'admin.customers.index',
    component: () => import('@/pages/Admin/Customers/CustomersList.vue'),
    meta: { role: 'admin' },
  },
  {
    path: '/admin/clientes/novo',
    name: 'admin.customers.create',
    component: () => import('@/pages/Admin/Customers/CustomerForm.vue'),
    meta: { role: 'admin' },
  },
  {
    path: '/admin/clientes/:id',
    name: 'admin.customers.show',
    component: () => import('@/pages/Admin/Customers/CustomerDetail.vue'),
    meta: { role: 'admin' },
  },
  {
    path: '/admin/clientes/:id/editar',
    name: 'admin.customers.edit',
    component: () => import('@/pages/Admin/Customers/CustomerForm.vue'),
    meta: { role: 'admin' },
  },
  {
    path: '/admin/tarefas',
    name: 'admin.tasks.index',
    component: () => import('@/pages/Admin/Tasks/TasksList.vue'),
    meta: { role: 'admin' },
  },
  {
    path: '/customer',
    name: 'customer.home',
    component: () => import('@/pages/CustomerHome.vue'),
    meta: { role: 'customer' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(roleGuard)

export default router
