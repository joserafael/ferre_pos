import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import { useAuthStore } from '@/stores/authStore'
import { useTasaStore } from '@/stores/tasaStore'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: null }),
    post: vi.fn().mockResolvedValue({ data: null })
  }
}))

describe('MainLayout Component', () => {
  let router

  beforeEach(() => {
    setActivePinia(createPinia())
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div>Home</div>' } },
        { path: '/pos', component: { template: '<div>POS</div>' } },
        { path: '/ventas', component: { template: '<div>Ventas</div>' } },
        { path: '/dashboard', component: { template: '<div>Dashboard</div>' } },
        { path: '/productos', component: { template: '<div>Productos</div>' } },
        { path: '/categorias', component: { template: '<div>Categorias</div>' } },
        { path: '/inventario', component: { template: '<div>Inventario</div>' } },
        { path: '/cajeros', component: { template: '<div>Cajeros</div>' } },
        { path: '/login', component: { template: '<div>Login</div>' } }
      ]
    })
  })

  it('muestra la tasa BCV activa en la barra superior', async () => {
    const tasaStore = useTasaStore()
    tasaStore.setTasaActiva({ tasa_ves: 43.25, fuente: 'BCV Oficial' })

    const wrapper = mount(MainLayout, {
      global: {
        plugins: [router],
        stubs: ['router-view']
      }
    })

    expect(wrapper.text()).toContain('43.25')
    expect(wrapper.text()).toContain('BCV')
  })

  it('muestra las opciones de administración solo si el usuario es ADMIN', async () => {
    const authStore = useAuthStore()
    authStore.token = 'jwt.admin.token'
    authStore.usuario = { id: 1, nombre: 'Admin Master', rol: 'ADMIN' }

    const wrapper = mount(MainLayout, {
      global: {
        plugins: [router],
        stubs: ['router-view']
      }
    })

    expect(wrapper.text()).toContain('Dashboard Bimonetario')
    expect(wrapper.text()).toContain('Compras & Kardex')
    expect(wrapper.text()).toContain('Cajeros & Histórico')
    expect(wrapper.text()).toContain('Admin Master')
  })

  it('oculta módulos de administración cuando el usuario es CAJERO', async () => {
    const authStore = useAuthStore()
    authStore.token = 'jwt.cajero.token'
    authStore.usuario = { id: 2, nombre: 'Cajero Turno Mañana', rol: 'CAJERO' }

    const wrapper = mount(MainLayout, {
      global: {
        plugins: [router],
        stubs: ['router-view']
      }
    })

    // El cajero ve Punto de Venta y Mis Ventas Realizadas
    expect(wrapper.text()).toContain('Punto de Venta (POS)')
    expect(wrapper.text()).toContain('Mis Ventas Realizadas')

    // No debe ver los módulos exclusivos de Admin
    expect(wrapper.text()).not.toContain('Dashboard Bimonetario')
    expect(wrapper.text()).not.toContain('Compras & Kardex')
    expect(wrapper.text()).not.toContain('Cajeros & Histórico')
  })
})
