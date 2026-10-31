import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/authStore'

describe('authStore', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('inicia no autenticado cuando localStorage está vacío', () => {
    const auth = useAuthStore()
    expect(auth.token).toBe('')
    expect(auth.usuario).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.isAdmin).toBe(false)
    expect(auth.isCajero).toBe(false)
  })

  it('reconoce correctamente el rol de ADMIN', () => {
    localStorage.setItem('ferrepos_token', 'jwt.test.token')
    localStorage.setItem(
      'ferrepos_user',
      JSON.stringify({ id: 1, username: 'admin', rol: 'ADMIN', nombre: 'Admin User' })
    )

    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.isAdmin).toBe(true)
    expect(auth.isCajero).toBe(false)
  })

  it('reconoce correctamente el rol de CAJERO', () => {
    localStorage.setItem('ferrepos_token', 'jwt.cajero.token')
    localStorage.setItem(
      'ferrepos_user',
      JSON.stringify({ id: 2, username: 'cajero', rol: 'CAJERO', nombre: 'Cajero User' })
    )

    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.isAdmin).toBe(false)
    expect(auth.isCajero).toBe(true)
  })

  it('logout limpia el estado y remueve los datos de localStorage', () => {
    localStorage.setItem('ferrepos_token', 'jwt.test.token')
    localStorage.setItem(
      'ferrepos_user',
      JSON.stringify({ id: 1, username: 'admin', rol: 'ADMIN' })
    )

    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(true)

    auth.logout()

    expect(auth.token).toBe('')
    expect(auth.usuario).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem('ferrepos_token')).toBeNull()
    expect(localStorage.getItem('ferrepos_user')).toBeNull()
  })
})

