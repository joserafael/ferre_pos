import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useCajaStore = defineStore('caja', () => {
  const sesion = ref(null)
  const resumen = ref(null)
  const cargando = ref(false)
  const cargada = ref(false)

  const estaAbierta = computed(() => Boolean(sesion.value && sesion.value.estado === 'ABIERTA'))

  async function cargarCajaActual() {
    cargando.value = true
    try {
      const res = await api.get('/caja/actual')
      const data = res.data || {}
      sesion.value = data.abierta ? data.sesion : null
      resumen.value = data.abierta ? data.resumen : null
      cargada.value = true
      return data
    } finally {
      cargando.value = false
    }
  }

  async function abrirCaja({ monto_apertura_usd = 0, monto_apertura_ves = 0, observacion_apertura = '' }) {
    const res = await api.post('/caja/abrir', {
      monto_apertura_usd: Number(monto_apertura_usd || 0),
      monto_apertura_ves: Number(monto_apertura_ves || 0),
      observacion_apertura
    })
    const data = res.data || {}
    sesion.value = data.sesion || null
    resumen.value = data.resumen || null
    cargada.value = true
    return data
  }

  async function cerrarCaja({ contado_efectivo_usd = 0, contado_efectivo_ves = 0, observacion_cierre = '' }) {
    const res = await api.post('/caja/cerrar', {
      contado_efectivo_usd: Number(contado_efectivo_usd || 0),
      contado_efectivo_ves: Number(contado_efectivo_ves || 0),
      observacion_cierre
    })
    const data = res.data || {}
    sesion.value = null
    resumen.value = null
    cargada.value = true
    return data
  }

  function limpiar() {
    sesion.value = null
    resumen.value = null
    cargada.value = false
  }

  return {
    sesion,
    resumen,
    cargando,
    cargada,
    estaAbierta,
    cargarCajaActual,
    abrirCaja,
    cerrarCaja,
    limpiar
  }
})
