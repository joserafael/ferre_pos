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
      { path: 'dashboard', name: 'dashboard', component: DashboardView },
      { path: 'pos', name: 'pos', component: PosView },
      { path: 'productos', name: 'productos', component: ProductosView },
      { path: 'categorias', name: 'categorias', component: CategoriasView },
      { path: 'inventario', name: 'inventario', component: InventarioView },
      { path: 'ventas', name: 'ventas', component: VentasView }
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
})

export default router
