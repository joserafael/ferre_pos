import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { useTasaStore } from './tasaStore'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('ferrepos_token') || '')
  const usuario = ref(JSON.parse(localStorage.getItem('ferrepos_user') || 'null'))

  const isAuthenticated = computed(() => Boolean(token.value))
  const isAdmin = computed(() => String(usuario.value?.rol || '').toUpperCase() === 'ADMIN')
  const isCajero = computed(() => String(usuario.value?.rol || '').toUpperCase() === 'CAJERO')

  async function login(username, password) {
    const res = await api.post('/auth/login', { username, password })
    token.value = res.data.token
    usuario.value = res.data.usuario
    localStorage.setItem('ferrepos_token', res.data.token)
    localStorage.setItem('ferrepos_user', JSON.stringify(res.data.usuario))

    if (res.data.tasa_activa) {
      const tasaStore = useTasaStore()
      tasaStore.setTasaActiva(res.data.tasa_activa)
    }
    return res.data
  }

  function logout() {
    token.value = ''
    usuario.value = null
    localStorage.removeItem('ferrepos_token')
    localStorage.removeItem('ferrepos_user')
  }

  return {
    token,
    usuario,
    isAuthenticated,
    isAdmin,
    isCajero,
    login,
    logout
  }
})
