import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import LoginView from '@/views/LoginView.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import DashboardView from '@/views/DashboardView.vue'
import PosView from '@/views/PosView.vue'
import ProductosView from '@/views/ProductosView.vue'
import CategoriasView from '@/views/CategoriasView.vue'
import InventarioView from '@/views/InventarioView.vue'
import VentasView from '@/views/VentasView.vue'
import CajerosView from '@/views/CajerosView.vue'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guest: true }
  },
  {
    path: '/',
    component: MainLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/pos' },
      { path: 'pos', name: 'pos', component: PosView },
      { path: 'ventas', name: 'ventas', component: VentasView },
      { path: 'dashboard', name: 'dashboard', component: DashboardView, meta: { adminOnly: true } },
      { path: 'productos', name: 'productos', component: ProductosView, meta: { adminOnly: true } },
      { path: 'categorias', name: 'categorias', component: CategoriasView, meta: { adminOnly: true } },
      { path: 'inventario', name: 'inventario', component: InventarioView, meta: { adminOnly: true } },
      { path: 'cajeros', name: 'cajeros', component: CajerosView, meta: { adminOnly: true } }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to) => {
  const authStore = useAuthStore()
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' }
  }
  if (to.meta.guest && authStore.isAuthenticated) {
    return { name: 'pos' }
  }
  if (to.meta.adminOnly && !authStore.isAdmin) {
    return { name: 'pos' }
  }
})

export default router
