<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { useCajaStore } from '@/stores/cajaStore'
import { useTasaStore } from '@/stores/tasaStore'
import { useAuthStore } from '@/stores/authStore'
import PaginationBar from '@/components/PaginationBar.vue'

const cajaStore = useCajaStore()
const tasaStore = useTasaStore()
const authStore = useAuthStore()

const errorMsg = ref('')
const exitoMsg = ref('')
const procesando = ref(false)

// Formulario Apertura
const aperturaForm = ref({
  monto_apertura_usd: 0,
  monto_apertura_ves: 0,
  observacion_apertura: ''
})

// Modal Cierre / Arqueo
const modalCierre = ref(false)
const cierreForm = ref({
  contado_efectivo_usd: 0,
  contado_efectivo_ves: 0,
  observacion_cierre: ''
})

// Comprobante de cierre recién realizado o consultado
const detalleSesion = ref(null)

// Historial paginado
const historial = ref([])
const busqueda = ref('')
const estadoFiltro = ref('')
const paginaActual = ref(1)
const porPagina = ref(15)
const paginacion = ref({ total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 })
const cargandoHistorial = ref(false)
let debounceTimer = null

const diferenciaVivaUsd = computed(() => {
  if (!cajaStore.resumen) return 0
  return Number((Number(cierreForm.value.contado_efectivo_usd || 0) - Number(cajaStore.resumen.esperado_efectivo_usd || 0)).toFixed(2))
})

const diferenciaVivaVes = computed(() => {
  if (!cajaStore.resumen) return 0
  return Number((Number(cierreForm.value.contado_efectivo_ves || 0) - Number(cajaStore.resumen.esperado_efectivo_ves || 0)).toFixed(2))
})

async function cargarHistorial() {
  cargandoHistorial.value = true
  try {
    const params = new URLSearchParams({
      page: String(paginaActual.value),
      per_page: String(porPagina.value)
    })
    if (busqueda.value.trim()) params.set('q', busqueda.value.trim())
    if (estadoFiltro.value) params.set('estado', estadoFiltro.value)

    const res = await api.get(`/caja/historial?${params.toString()}`)
    const data = res.data || {}
    historial.value = data.items || []
    if (data.pagination) {
      paginacion.value = data.pagination
      paginaActual.value = data.pagination.current_page
    }
  } finally {
    cargandoHistorial.value = false
  }
}

async function cargarTodo() {
  await Promise.all([cajaStore.cargarCajaActual(), cargarHistorial()])
}

onMounted(cargarTodo)

function alEscribirBusqueda() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    paginaActual.value = 1
    cargarHistorial()
  }, 180)
}

async function ejecutarApertura() {
  errorMsg.value = ''
  exitoMsg.value = ''
  procesando.value = true
  try {
    await cajaStore.abrirCaja(aperturaForm.value)
    exitoMsg.value = 'Caja abierta exitosamente. Ya puede emitir facturas desde el POS.'
    aperturaForm.value = { monto_apertura_usd: 0, monto_apertura_ves: 0, observacion_apertura: '' }
    await cargarHistorial()
  } catch (e) {
    errorMsg.value = e.message
  } finally {
    procesando.value = false
  }
}

async function abrirModalCierre() {
  errorMsg.value = ''
  await cajaStore.cargarCajaActual()
  cierreForm.value = {
    contado_efectivo_usd: cajaStore.resumen?.esperado_efectivo_usd ?? 0,
    contado_efectivo_ves: cajaStore.resumen?.esperado_efectivo_ves ?? 0,
    observacion_cierre: ''
  }
  modalCierre.value = true
}

async function ejecutarCierre() {
  errorMsg.value = ''
  exitoMsg.value = ''
  procesando.value = true
  try {
    const res = await cajaStore.cerrarCaja(cierreForm.value)
    modalCierre.value = false
    exitoMsg.value = 'Caja cerrada y arqueo registrado correctamente.'
    detalleSesion.value = res.sesion || null
    await cargarHistorial()
  } catch (e) {
    errorMsg.value = e.message
  } finally {
    procesando.value = false
  }
}

async function verDetalle(id) {
  const res = await api.get(`/caja/${id}`)
  detalleSesion.value = res.data
}

function claseDiferencia(val) {
  if (val === null || val === undefined) return ''
  if (Math.abs(Number(val)) < 0.01) return 'badge-usd'
  return 'badge-danger'
}
</script>

<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 0.75rem">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800">🔐 Apertura, Arqueo & Cierre de Caja</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem">
          Control de fondo inicial, arqueo de efectivo bimonetario (USD / Bs.) y auditoría de turnos por cajero.
        </p>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center">
        <span :class="['badge', cajaStore.estaAbierta ? 'badge-usd' : 'badge-danger']" style="font-size: 0.85rem; padding: 0.4rem 0.75rem">
          {{ cajaStore.estaAbierta ? '🟢 CAJA ABIERTA' : '🔴 CAJA CERRADA' }}
        </span>
        <button class="btn btn-outline btn-sm" @click="cargarTodo" :disabled="cajaStore.cargando">↻ Actualizar</button>
      </div>
    </div>

    <div v-if="exitoMsg" class="badge badge-usd" style="width: 100%; padding: 0.65rem; margin-bottom: 1rem">
      ✓ {{ exitoMsg }}
    </div>
    <div v-if="errorMsg" class="badge badge-danger" style="width: 100%; padding: 0.65rem; margin-bottom: 1rem">
      ⚠️ {{ errorMsg }}
    </div>

    <!-- Panel de Apertura (cuando está cerrada) -->
    <div v-if="!cajaStore.estaAbierta" class="card" style="margin-bottom: 1.25rem">
      <h3 style="margin-bottom: 0.35rem">🔓 Abrir Turno de Caja</h3>
      <p style="color: var(--text-muted); font-size: 0.84rem; margin-bottom: 1rem">
        Ingresa el fondo de efectivo inicial en gaveta (para dar vueltos) antes de comenzar a facturar en el POS.
      </p>

      <form @submit.prevent="ejecutarApertura">
        <div class="grid-3">
          <div class="form-group">
            <label class="form-label">Fondo Inicial Efectivo USD ($)</label>
            <input v-model="aperturaForm.monto_apertura_usd" type="number" step="0.01" min="0" class="form-control mono" required />
          </div>
          <div class="form-group">
            <label class="form-label">Fondo Inicial Efectivo Bolívares (Bs.)</label>
            <input v-model="aperturaForm.monto_apertura_ves" type="number" step="0.01" min="0" class="form-control mono" required />
          </div>
          <div class="form-group">
            <label class="form-label">Observación de Apertura (Opcional)</label>
            <input v-model="aperturaForm.observacion_apertura" type="text" class="form-control" placeholder="Ej. Turno mañana - Caja 1" />
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end">
          <button type="submit" class="btn btn-primary" :disabled="procesando">
            {{ procesando ? 'Abriendo...' : '🔓 Abrir Caja y Comenzar Turno' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Panel en Vivo (cuando está abierta) -->
    <div v-else class="card" style="margin-bottom: 1.25rem">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem">
        <div>
          <h3 style="margin-bottom: 0.2rem">Turno Activo #{{ cajaStore.sesion.id }}</h3>
          <div style="font-size: 0.8rem; color: var(--text-muted)">
            Abierta el <strong class="mono">{{ cajaStore.sesion.abierta_at }}</strong> ·
            Tasa de apertura: <strong class="mono">Bs. {{ Number(cajaStore.sesion.tasa_apertura).toFixed(4) }}</strong>
          </div>
        </div>
        <button class="btn btn-danger" @click="abrirModalCierre">
          🔒 Realizar Arqueo y Cerrar Caja
        </button>
      </div>

      <div class="grid-4" style="margin-bottom: 1rem">
        <div class="kpi-box">
          <div class="kpi-label">Facturas Emitidas</div>
          <div class="kpi-val mono">{{ cajaStore.resumen?.ventas_cantidad || 0 }}</div>
          <div style="font-size: 0.72rem; color: var(--text-muted)">
            Anuladas: {{ cajaStore.resumen?.ventas_anuladas || 0 }}
          </div>
        </div>

        <div class="kpi-box">
          <div class="kpi-label">Total Vendido</div>
          <div class="kpi-val mono" style="color: var(--usd-green)">
            {{ tasaStore.formatUsd(cajaStore.resumen?.total_ventas_usd || 0) }}
          </div>
          <div class="mono" style="font-size: 0.78rem; color: var(--ves-blue)">
            {{ tasaStore.formatVes(cajaStore.resumen?.total_ventas_ves || 0) }}
          </div>
        </div>

        <div class="kpi-box">
          <div class="kpi-label">Esperado Efectivo USD</div>
          <div class="kpi-val mono" style="color: var(--usd-green)">
            {{ tasaStore.formatUsd(cajaStore.resumen?.esperado_efectivo_usd || 0) }}
          </div>
          <div style="font-size: 0.72rem; color: var(--text-muted)">
            Fondo: {{ tasaStore.formatUsd(cajaStore.sesion.monto_apertura_usd) }}
            · Vueltos: -{{ tasaStore.formatUsd(cajaStore.resumen?.vueltos_usd || 0) }}
          </div>
        </div>

        <div class="kpi-box">
          <div class="kpi-label">Esperado Efectivo Bs.</div>
          <div class="kpi-val mono" style="color: var(--ves-blue)">
            {{ tasaStore.formatVes(cajaStore.resumen?.esperado_efectivo_ves || 0) }}
          </div>
          <div style="font-size: 0.72rem; color: var(--text-muted)">
            Fondo: {{ tasaStore.formatVes(cajaStore.sesion.monto_apertura_ves) }}
            · Vueltos: -{{ tasaStore.formatVes(cajaStore.resumen?.vueltos_ves || 0) }}
          </div>
        </div>
      </div>

      <div v-if="cajaStore.resumen?.desglose_metodos?.length">
        <h4 style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.45rem">INGRESOS DEL TURNO POR MÉTODO DE PAGO</h4>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Método</th>
                <th>Moneda</th>
                <th>Monto Recibido</th>
                <th>Equivalente USD</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in cajaStore.resumen.desglose_metodos" :key="m.codigo">
                <td><strong>{{ m.nombre }}</strong> <span class="mono" style="font-size: 0.72rem; color: var(--text-muted)">({{ m.codigo }})</span></td>
                <td><span :class="['badge', m.moneda === 'USD' ? 'badge-usd' : 'badge-ves']">{{ m.moneda }}</span></td>
                <td class="mono"><strong>{{ m.moneda === 'USD' ? tasaStore.formatUsd(m.total_original) : tasaStore.formatVes(m.total_original) }}</strong></td>
                <td class="mono">{{ tasaStore.formatUsd(m.total_equiv_usd) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Historial de Sesiones / Cierres -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.9rem; flex-wrap: wrap; gap: 0.75rem">
        <h3>📜 {{ authStore.isAdmin ? 'Historial General de Cajas y Arqueos' : 'Mis Cierres de Caja' }}</h3>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap">
          <input
            v-model="busqueda"
            type="text"
            class="form-control"
            style="min-width: 220px"
            placeholder="🔍 Buscar por cajero u observación..."
            @input="alEscribirBusqueda"
          />
          <select v-model="estadoFiltro" class="form-control" style="width: 145px" @change="() => { paginaActual = 1; cargarHistorial() }">
            <option value="">Todos los estados</option>
            <option value="ABIERTA">ABIERTA</option>
            <option value="CERRADA">CERRADA</option>
          </select>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Cajero</th>
              <th>Apertura / Cierre</th>
              <th>Estado</th>
              <th>Facturas</th>
              <th>Total Vendido</th>
              <th>Dif. Efectivo USD</th>
              <th>Dif. Efectivo Bs.</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!historial.length">
              <td colspan="9" style="text-align: center; padding: 1.5rem; color: var(--text-muted)">
                No hay sesiones de caja registradas.
              </td>
            </tr>
            <tr v-for="s in historial" :key="s.id">
              <td class="mono">#{{ s.id }}</td>
              <td>
                <strong>{{ s.usuario_nombre || '—' }}</strong>
                <div class="mono" style="font-size: 0.72rem; color: var(--text-muted)">@{{ s.usuario_username }}</div>
              </td>
              <td style="font-size: 0.78rem">
                <div><strong>Abre:</strong> {{ s.abierta_at }}</div>
                <div v-if="s.cerrada_at"><strong>Cierra:</strong> {{ s.cerrada_at }}</div>
              </td>
              <td>
                <span :class="['badge', s.estado === 'ABIERTA' ? 'badge-usd' : 'badge-ves']">
                  {{ s.estado }}
                </span>
              </td>
              <td class="mono">{{ s.ventas_cantidad }}</td>
              <td class="mono">
                <strong style="color: var(--usd-green)">{{ tasaStore.formatUsd(s.total_ventas_usd) }}</strong>
                <div style="font-size: 0.74rem; color: var(--ves-blue)">{{ tasaStore.formatVes(s.total_ventas_ves) }}</div>
              </td>
              <td class="mono">
                <span v-if="s.estado === 'CERRADA'" :class="['badge', claseDiferencia(s.diferencia_usd)]">
                  {{ Number(s.diferencia_usd) >= 0 ? '+' : '' }}{{ tasaStore.formatUsd(s.diferencia_usd) }}
                </span>
                <span v-else style="color: var(--text-muted)">En curso</span>
              </td>
              <td class="mono">
                <span v-if="s.estado === 'CERRADA'" :class="['badge', claseDiferencia(s.diferencia_ves)]">
                  {{ Number(s.diferencia_ves) >= 0 ? '+' : '' }}{{ tasaStore.formatVes(s.diferencia_ves) }}
                </span>
                <span v-else style="color: var(--text-muted)">En curso</span>
              </td>
              <td>
                <button class="btn btn-outline btn-sm" @click="verDetalle(s.id)">Ver Arqueo</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar
        :pagination="paginacion"
        :loading="cargandoHistorial"
        label="sesiones de caja"
        @page-change="(p) => { paginaActual = p; cargarHistorial() }"
        @per-page-change="(pp) => { porPagina = pp; paginaActual = 1; cargarHistorial() }"
      />
    </div>

    <!-- Modal Cierre de Caja / Conteo Físico -->
    <div v-if="modalCierre" class="modal-backdrop" @click.self="modalCierre = false">
      <div class="modal-box" style="max-width: 560px">
        <div class="modal-header">
          <h3>🔒 Arqueo y Cierre de Caja #{{ cajaStore.sesion?.id }}</h3>
          <button class="btn btn-outline btn-sm" @click="modalCierre = false">✕</button>
        </div>

        <div class="grid-2" style="margin-bottom: 1rem; background: #f8fafc; padding: 0.85rem; border-radius: 8px; border: 1px solid var(--border-color)">
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted)">Esperado en Gaveta (USD)</div>
            <div class="mono" style="font-size: 1.15rem; font-weight: 800; color: var(--usd-green)">
              {{ tasaStore.formatUsd(cajaStore.resumen?.esperado_efectivo_usd || 0) }}
            </div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted)">Esperado en Gaveta (Bs.)</div>
            <div class="mono" style="font-size: 1.15rem; font-weight: 800; color: var(--ves-blue)">
              {{ tasaStore.formatVes(cajaStore.resumen?.esperado_efectivo_ves || 0) }}
            </div>
          </div>
        </div>

        <form @submit.prevent="ejecutarCierre">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Efectivo Contado USD ($) *</label>
              <input v-model="cierreForm.contado_efectivo_usd" type="number" step="0.01" min="0" class="form-control mono" required />
              <div style="margin-top: 0.3rem; font-size: 0.76rem">
                Diferencia:
                <span :class="['badge', claseDiferencia(diferenciaVivaUsd)]">
                  {{ diferenciaVivaUsd >= 0 ? '+' : '' }}{{ tasaStore.formatUsd(diferenciaVivaUsd) }}
                </span>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Efectivo Contado Bolívares (Bs.) *</label>
              <input v-model="cierreForm.contado_efectivo_ves" type="number" step="0.01" min="0" class="form-control mono" required />
              <div style="margin-top: 0.3rem; font-size: 0.76rem">
                Diferencia:
                <span :class="['badge', claseDiferencia(diferenciaVivaVes)]">
                  {{ diferenciaVivaVes >= 0 ? '+' : '' }}{{ tasaStore.formatVes(diferenciaVivaVes) }}
                </span>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Observación de Cierre (Opcional)</label>
            <input v-model="cierreForm.observacion_cierre" type="text" class="form-control" placeholder="Ej. Cierre conforme sin novedades" />
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.5rem">
            <button type="button" class="btn btn-outline" @click="modalCierre = false">Cancelar</button>
            <button type="submit" class="btn btn-danger" :disabled="procesando">
              {{ procesando ? 'Cerrando...' : '✓ Confirmar Cierre de Caja' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Detalle / Comprobante de Arqueo -->
    <div v-if="detalleSesion" class="modal-backdrop" @click.self="detalleSesion = null">
      <div class="modal-box" style="max-width: 580px">
        <div class="modal-header">
          <h3>🧾 Comprobante de Arqueo #{{ detalleSesion.id }}</h3>
          <button class="btn btn-outline btn-sm" @click="detalleSesion = null">✕</button>
        </div>

        <div class="mono" style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px dashed #cbd5e1; font-size: 0.82rem">
          <div style="margin-bottom: 0.65rem">
            <strong>Cajero:</strong> {{ detalleSesion.usuario_nombre || authStore.usuario?.nombre }}<br />
            <strong>Estado:</strong> {{ detalleSesion.estado }}<br />
            <strong>Apertura:</strong> {{ detalleSesion.abierta_at }} (Tasa Bs. {{ Number(detalleSesion.tasa_apertura).toFixed(4) }})<br />
            <span v-if="detalleSesion.cerrada_at"><strong>Cierre:</strong> {{ detalleSesion.cerrada_at }}<br /></span>
            <strong>Facturas Completadas:</strong> {{ detalleSesion.resumen_vivo?.ventas_cantidad ?? detalleSesion.ventas_cantidad }}
          </div>
          <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0.5rem 0" />
          <div style="display: flex; justify-content: space-between">
            <span>Fondo Apertura:</span>
            <span>{{ tasaStore.formatUsd(detalleSesion.monto_apertura_usd) }} / {{ tasaStore.formatVes(detalleSesion.monto_apertura_ves) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between">
            <span>Total Vendido:</span>
            <span>{{ tasaStore.formatUsd(detalleSesion.resumen_vivo?.total_ventas_usd ?? detalleSesion.total_ventas_usd) }} / {{ tasaStore.formatVes(detalleSesion.resumen_vivo?.total_ventas_ves ?? detalleSesion.total_ventas_ves) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between">
            <span>Efectivo Esperado:</span>
            <span>{{ tasaStore.formatUsd(detalleSesion.resumen_vivo?.esperado_efectivo_usd ?? detalleSesion.esperado_efectivo_usd) }} / {{ tasaStore.formatVes(detalleSesion.resumen_vivo?.esperado_efectivo_ves ?? detalleSesion.esperado_efectivo_ves) }}</span>
          </div>
          <template v-if="detalleSesion.estado === 'CERRADA'">
            <div style="display: flex; justify-content: space-between">
              <span>Efectivo Contado:</span>
              <span>{{ tasaStore.formatUsd(detalleSesion.contado_efectivo_usd) }} / {{ tasaStore.formatVes(detalleSesion.contado_efectivo_ves) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: 800; margin-top: 0.25rem">
              <span>Diferencia Arqueo:</span>
              <span>{{ tasaStore.formatUsd(detalleSesion.diferencia_usd) }} / {{ tasaStore.formatVes(detalleSesion.diferencia_ves) }}</span>
            </div>
          </template>
          <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0.5rem 0" />
          <div v-if="(detalleSesion.resumen_vivo?.desglose_metodos || detalleSesion.desglose_metodos || []).length">
            <strong>Desglose por Método de Pago:</strong>
            <div
              v-for="m in (detalleSesion.resumen_vivo?.desglose_metodos || detalleSesion.desglose_metodos)"
              :key="m.codigo"
              style="display: flex; justify-content: space-between; margin-top: 0.2rem"
            >
              <span>{{ m.nombre }}:</span>
              <span>{{ m.moneda === 'USD' ? tasaStore.formatUsd(m.total_original) : tasaStore.formatVes(m.total_original) }}</span>
            </div>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem">
          <button class="btn btn-primary" @click="detalleSesion = null">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kpi-box {
  background: #f8fafc;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.85rem;
}

.kpi-label {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.kpi-val {
  font-size: 1.25rem;
  font-weight: 800;
}
</style>
