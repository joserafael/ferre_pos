import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useTasaStore = defineStore('tasa', () => {
  const tasaActiva = ref({
    id: 1,
    tasa_ves: 42.5,
    fuente: 'BCV Oficial',
    created_at: null
  })
  const historial = ref([])
  const cargando = ref(false)

  const tasaVes = computed(() => Number(tasaActiva.value?.tasa_ves || 42.5))

  function setTasaActiva(tasa) {
    if (tasa && Number(tasa.tasa_ves) > 0) {
      tasaActiva.value = tasa
    }
  }

  async function fetchTasaActiva() {
    try {
      const res = await api.get('/tasas/activa')
      if (res.data) {
        tasaActiva.value = res.data
      }
    } catch {
      // fallback silencioso
    }
  }

  async function fetchHistorial() {
    cargando.value = true
    try {
      const res = await api.get('/tasas')
      if (res.data?.activa) tasaActiva.value = res.data.activa
      historial.value = res.data?.historial || []
    } finally {
      cargando.value = false
    }
  }

  async function actualizarTasa(nuevaTasaVes, fuente = 'BCV Oficial') {
    const res = await api.post('/tasas', {
      tasa_ves: Number(nuevaTasaVes),
      fuente
    })
    if (res.data) {
      tasaActiva.value = res.data
    }
    await fetchHistorial()
    return res.data
  }

  function aBolivares(montoUsd) {
    return Number((Number(montoUsd || 0) * tasaVes.value).toFixed(2))
  }

  function aDolares(montoVes) {
    if (tasaVes.value <= 0) return 0
    return Number((Number(montoVes || 0) / tasaVes.value).toFixed(2))
  }

  function formatUsd(val) {
    return `$ ${Number(val || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    })}`
  }

  function formatVes(val) {
    return `Bs. ${Number(val || 0).toLocaleString('es-VE', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    })}`
  }

  return {
    tasaActiva,
    historial,
    cargando,
    tasaVes,
    setTasaActiva,
    fetchTasaActiva,
    fetchHistorial,
    actualizarTasa,
    aBolivares,
    aDolares,
    formatUsd,
    formatVes
  }
})
