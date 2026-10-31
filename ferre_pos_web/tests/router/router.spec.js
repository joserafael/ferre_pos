import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import router from '@/router/index'
import { useAuthStore } from '@/stores/authStore'

describe('router navigation guards', () => {
  beforeEach(async () => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('redirige a /login cuando no está autenticado y se intenta acceder a /pos', async () => {
    const authStore = useAuthStore()
    authStore.logout()

    await router.push('/pos')
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirige a /pos si ya está autenticado y se intenta acceder a /login', async () => {
    const authStore = useAuthStore()
    authStore.token = 'jwt.valid.token'
    authStore.usuario = { id: 2, username: 'cajero', rol: 'CAJERO' }

    // Simular que viene de otra ruta para forzar navegación a /login
    await router.push('/ventas')
    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('pos')
  })

  it('bloquea al CAJERO de rutas exclusivas de admin (ej. /inventario, /cajeros) y lo redirige a /pos', async () => {
    const authStore = useAuthStore()
    authStore.token = 'jwt.cajero.token'
    authStore.usuario = { id: 2, username: 'cajero', rol: 'CAJERO' }

    await router.push('/cajeros')
    expect(router.currentRoute.value.name).toBe('pos')

    await router.push('/inventario')
    expect(router.currentRoute.value.name).toBe('pos')
  })

  it('permite al ADMIN acceder a rutas de administración (/inventario, /cajeros)', async () => {
    const authStore = useAuthStore()
    authStore.token = 'jwt.admin.token'
    authStore.usuario = { id: 1, username: 'admin', rol: 'ADMIN' }

    await router.push('/cajeros')
    expect(router.currentRoute.value.name).toBe('cajeros')

    await router.push('/inventario')
    expect(router.currentRoute.value.name).toBe('inventario')
  })
})
