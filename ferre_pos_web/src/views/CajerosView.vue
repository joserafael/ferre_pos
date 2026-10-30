<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useTasaStore } from '@/stores/tasaStore'

const tasaStore = useTasaStore()
const cajeros = ref([])
const cargando = ref(false)
const errorMsg = ref('')
const exitoMsg = ref('')

// Modal Crear / Editar Cajero
const modalForm = ref(false)
const editandoId = ref(null)
const guardando = ref(false)
const form = ref({
  nombre: '',
  username: '',
  email: '',
  password: '',
  activo: true
})

// Modal Histórico de Ventas por Cajero
const modalHistorico = ref(false)
const cargandoHistorico = ref(false)
const historicoData = ref(null)

async function cargarCajeros() {
  cargando.value = true
  try {
    const res = await api.get('/cajeros')
    cajeros.value = res.data || []
  } catch (e) {
    errorMsg.value = e.message
  } finally {
    cargando.value = false
  }
}

onMounted(cargarCajeros)

function abrirNuevoCajero() {
  errorMsg.value = ''
  exitoMsg.value = ''
  editandoId.value = null
  form.value = {
    nombre: '',
    username: '',
    email: '',
    password: '',
    activo: true
  }
  modalForm.value = true
}

function abrirEditarCajero(c) {
  errorMsg.value = ''
  exitoMsg.value = ''
  editandoId.value = c.id
  form.value = {
    nombre: c.nombre,
    username: c.username,
    email: c.email || '',
    password: '',
    activo: Number(c.activo) === 1
  }
  modalForm.value = true
}

async function guardarCajero() {
  errorMsg.value = ''
  exitoMsg.value = ''
  guardando.value = true
  try {
    const payload = {
      nombre: form.value.nombre.trim(),
      username: form.value.username.trim().toLowerCase(),
      email: form.value.email.trim(),
      activo: form.value.activo ? 1 : 0
    }
    if (form.value.password) {
      payload.password = form.value.password
    }

    if (editandoId.value) {
      await api.put(`/cajeros/${editandoId.value}`, payload)
      exitoMsg.value = 'Datos del cajero actualizados correctamente.'
    } else {
      await api.post('/cajeros', payload)
      exitoMsg.value = 'Nuevo cajero creado correctamente.'
    }

    modalForm.value = false
    await cargarCajeros()
  } catch (e) {
    errorMsg.value = e.message
  } finally {
    guardando.value = false
  }
}

async function verHistoricoVentas(cajero) {
  errorMsg.value = ''
  cargandoHistorico.value = true
  historicoData.value = null
  modalHistorico.value = true
  try {
    const res = await api.get(`/cajeros/${cajero.id}/ventas`)
    historicoData.value = res.data
  } catch (e) {
    errorMsg.value = e.message
    modalHistorico.value = false
  } finally {
    cargandoHistorico.value = false
  }
}
</script>

<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800">Gestión de Cajeros e Histórico de Ventas</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem">
          Creación de usuarios cajeros y auditoría histórica de todo lo vendido por cada operador en USD y Bolívares.
        </p>
      </div>
      <button class="btn btn-primary" @click="abrirNuevoCajero">+ Crear Nuevo Cajero</button>
    </div>

    <div v-if="exitoMsg" class="badge badge-usd" style="width: 100%; padding: 0.65rem; margin-bottom: 1rem">
      ✓ {{ exitoMsg }}
    </div>
    <div v-if="errorMsg" class="badge badge-danger" style="width: 100%; padding: 0.65rem; margin-bottom: 1rem">
      ⚠️ {{ errorMsg }}
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nombre del Cajero / Operador</th>
              <th>Usuario</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Ventas Completadas</th>
              <th>Total Vendido USD ($)</th>
              <th>Total Vendido Bs.</th>
              <th>Última Venta</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in cajeros" :key="c.id">
              <td>
                <strong>{{ c.nombre }}</strong>
                <div style="font-size: 0.72rem; color: var(--text-muted)">{{ c.email }}</div>
              </td>
              <td class="mono"><strong>{{ c.username }}</strong></td>
              <td>
                <span :class="['badge', c.rol === 'ADMIN' ? 'badge-amber' : 'badge-ves']">
                  {{ c.rol }}
                </span>
              </td>
              <td>
                <span :class="['badge', Number(c.activo) === 1 ? 'badge-usd' : 'badge-danger']">
                  {{ Number(c.activo) === 1 ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="mono">
                <strong>{{ c.ventas_completadas }}</strong>
                <span v-if="c.ventas_anuladas > 0" style="color: var(--danger); font-size: 0.72rem">
                  ({{ c.ventas_anuladas }} anul.)
                </span>
              </td>
              <td class="mono">
                <strong style="color: var(--usd-green)">{{ tasaStore.formatUsd(c.total_vendido_usd) }}</strong>
              </td>
              <td class="mono">
                <strong style="color: var(--ves-blue)">{{ tasaStore.formatVes(c.total_vendido_ves) }}</strong>
              </td>
              <td style="font-size: 0.78rem">{{ c.ultima_venta || 'Sin ventas aún' }}</td>
              <td style="display: flex; gap: 0.4rem">
                <button class="btn btn-dark btn-sm" @click="verHistoricoVentas(c)">
                  Ver Histórico
                </button>
                <button class="btn btn-outline btn-sm" @click="abrirEditarCajero(c)">
                  Editar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Crear / Editar Cajero -->
    <div v-if="modalForm" class="modal-backdrop" @click.self="modalForm = false">
      <div class="modal-box" style="max-width: 480px">
        <div class="modal-header">
          <h3>{{ editandoId ? 'Editar Cajero / Operador' : 'Registrar Nuevo Cajero' }}</h3>
          <button class="btn btn-outline btn-sm" @click="modalForm = false">✕</button>
        </div>

        <form @submit.prevent="guardarCajero">
          <div class="form-group">
            <label class="form-label">Nombre Completo del Cajero *</label>
            <input
              v-model="form.nombre"
              type="text"
              class="form-control"
              placeholder="Ej. María Rodríguez"
              required
            />
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Usuario de Acceso *</label>
              <input
                v-model="form.username"
                type="text"
                class="form-control mono"
                placeholder="Ej. cajero2"
                required
              />
            </div>

            <div class="form-group">
              <label class="form-label">
                Contraseña {{ editandoId ? '(Dejar vacío para no cambiar)' : '*' }}
              </label>
              <input
                v-model="form.password"
                type="password"
                class="form-control"
                :placeholder="editandoId ? '••••••••' : 'Mínimo 4 caracteres'"
                :required="!editandoId"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Correo Electrónico (Opcional)</label>
            <input
              v-model="form.email"
              type="email"
              class="form-control"
              placeholder="cajero@ferrepos.local"
            />
          </div>

          <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem">
            <input id="chkActivoCajero" v-model="form.activo" type="checkbox" />
            <label for="chkActivoCajero" style="font-size: 0.85rem; cursor: pointer">
              Cuenta activa (puede iniciar sesión en el POS para realizar ventas)
            </label>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem">
            <button type="button" class="btn btn-outline" @click="modalForm = false">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="guardando">
              {{ guardando ? 'Guardando...' : 'Guardar Cajero' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Histórico de lo que vendió el Cajero -->
    <div v-if="modalHistorico" class="modal-backdrop" @click.self="modalHistorico = false">
      <div class="modal-box" style="max-width: 860px">
        <div class="modal-header">
          <div>
            <h3>Histórico de Ventas: {{ historicoData?.cajero?.nombre || 'Cargando...' }}</h3>
            <div v-if="historicoData?.cajero" style="font-size: 0.8rem; color: var(--text-muted)">
              Usuario: <strong class="mono">{{ historicoData.cajero.username }}</strong> · Rol:
              <strong>{{ historicoData.cajero.rol }}</strong>
            </div>
          </div>
          <button class="btn btn-outline btn-sm" @click="modalHistorico = false">✕</button>
        </div>

        <div v-if="cargandoHistorico" style="padding: 2rem; text-align: center; color: var(--text-muted)">
          Consultando histórico de ventas del cajero...
        </div>

        <div v-else-if="historicoData">
          <!-- Tarjetas de resumen del cajero -->
          <div class="grid-3" style="margin-bottom: 1rem">
            <div class="card" style="background: #f8fafc; padding: 0.85rem">
              <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700">FACTURAS EMITIDAS</div>
              <div class="mono" style="font-size: 1.35rem; font-weight: 800">
                {{ historicoData.resumen.ventas_completadas }}
              </div>
              <div style="font-size: 0.75rem; color: var(--text-muted)">
                {{ historicoData.resumen.total_lineas_vendidas }} renglones de productos vendidos
              </div>
            </div>

            <div class="card" style="background: var(--usd-bg); padding: 0.85rem">
              <div style="font-size: 0.72rem; color: var(--usd-green); font-weight: 700">TOTAL VENDIDO EN USD ($)</div>
              <div class="mono" style="font-size: 1.35rem; font-weight: 800; color: var(--usd-green)">
                {{ tasaStore.formatUsd(historicoData.resumen.total_vendido_usd) }}
              </div>
              <div style="font-size: 0.75rem; color: var(--usd-green)">Acumulado histórico en dólares</div>
            </div>

            <div class="card" style="background: #eff6ff; padding: 0.85rem">
              <div style="font-size: 0.72rem; color: var(--ves-blue); font-weight: 700">TOTAL VENDIDO EN BOLÍVARES</div>
              <div class="mono" style="font-size: 1.35rem; font-weight: 800; color: var(--ves-blue)">
                {{ tasaStore.formatVes(historicoData.resumen.total_vendido_ves) }}
              </div>
              <div style="font-size: 0.75rem; color: var(--ves-blue)">Según tasa histórica de cada venta</div>
            </div>
          </div>

          <!-- Tabla detallada de ventas y productos vendidos por el cajero -->
          <div class="table-wrap" style="max-height: 430px; overflow-y: auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Factura / Fecha</th>
                  <th>Cliente (Cédula / RIF)</th>
                  <th>Productos Vendidos</th>
                  <th>Total USD</th>
                  <th>Total Bs.</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!historicoData.ventas.length">
                  <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.5rem">
                    Este cajero aún no registra ventas en el sistema.
                  </td>
                </tr>
                <tr v-for="v in historicoData.ventas" :key="v.id">
                  <td>
                    <strong class="mono">{{ v.numero_factura }}</strong>
                    <div style="font-size: 0.74rem; color: var(--text-muted)">{{ v.created_at }}</div>
                  </td>
                  <td>
                    <strong>{{ v.cliente_nombre || 'Sin nombre' }}</strong>
                    <div class="mono" style="font-size: 0.74rem; color: var(--text-muted)">
                      {{ v.cliente_documento }}
                    </div>
                  </td>
                  <td>
                    <div
                      v-for="d in v.detalles"
                      :key="d.id"
                      style="font-size: 0.78rem; margin-bottom: 0.2rem"
                    >
                      <strong class="mono">{{ Number(d.cantidad) }} {{ d.unidad_codigo }}</strong>
                      · {{ d.producto_nombre }}
                      <span class="mono" style="color: var(--text-muted)">
                        ({{ tasaStore.formatUsd(d.subtotal_usd) }})
                      </span>
                    </div>
                  </td>
                  <td class="mono">
                    <strong style="color: var(--usd-green)">{{ tasaStore.formatUsd(v.total_usd) }}</strong>
                  </td>
                  <td class="mono">
                    <strong style="color: var(--ves-blue)">{{ tasaStore.formatVes(v.total_ves) }}</strong>
                  </td>
                  <td>
                    <span :class="['badge', v.estado === 'COMPLETADA' ? 'badge-usd' : 'badge-danger']">
                      {{ v.estado }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
