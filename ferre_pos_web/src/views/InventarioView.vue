<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useTasaStore } from '@/stores/tasaStore'

const tasaStore = useTasaStore()
const tabActiva = ref('kardex')
const kardex = ref([])
const entradas = ref([])
const productos = ref([])
const filtroTipo = ref('')
const filtroProductoId = ref('')
const alertaMsg = ref('')
const errorMsg = ref('')

// Modal Nueva Entrada de Compra (USD o VES)
const modalEntrada = ref(false)
const entradaForm = ref({
  numero_documento: '',
  proveedor_nombre: '',
  moneda_ingreso: 'USD',
  observacion: '',
  items: [{ producto_id: '', cantidad_comprada: 1, costo_unitario: 1, precio_venta_usd: '' }]
})

// Modal Ajuste de Inventario (AJUSTE_POSITIVO / AJUSTE_NEGATIVO)
const modalAjuste = ref(false)
const ajusteForm = ref({
  producto_id: '',
  tipo_movimiento: 'AJUSTE_POSITIVO',
  cantidad: 1,
  observacion: ''
})

async function cargarTodo() {
  const paramsKardex = new URLSearchParams()
  if (filtroProductoId.value) paramsKardex.set('producto_id', filtroProductoId.value)
  if (filtroTipo.value) paramsKardex.set('tipo_movimiento', filtroTipo.value)

  const [resK, resE, resP] = await Promise.all([
    api.get(`/inventario/kardex?${paramsKardex.toString()}`),
    api.get('/inventario/entradas'),
    api.get('/productos')
  ])
  kardex.value = resK.data || []
  entradas.value = resE.data || []
  productos.value = resP.data || []
}

onMounted(cargarTodo)

function agregarFilaCompra() {
  entradaForm.value.items.push({
    producto_id: productos.value[0]?.id || '',
    cantidad_comprada: 1,
    costo_unitario: 1,
    precio_venta_usd: ''
  })
}

async function registrarEntradaCompra() {
  errorMsg.value = ''
  alertaMsg.value = ''
  try {
    await api.post('/inventario/entradas', entradaForm.value)
    modalEntrada.value = false
    alertaMsg.value = 'Entrada de compra registrada y Kardex actualizado.'
    await cargarTodo()
  } catch (e) {
    errorMsg.value = e.message
  }
}

async function registrarAjusteKardex() {
  errorMsg.value = ''
  alertaMsg.value = ''
  try {
    await api.post('/inventario/ajustes', ajusteForm.value)
    modalAjuste.value = false
    alertaMsg.value = 'Ajuste de inventario asentado en el Kardex inmutable.'
    await cargarTodo()
  } catch (e) {
    errorMsg.value = e.message
  }
}
</script>

<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800">Kardex Inmutable & Entradas de Inventario</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem">
          Trazabilidad de movimientos de stock y compras bimonetarias (USD / VES) con factor de conversión.
        </p>
      </div>
      <div style="display: flex; gap: 0.5rem">
        <button class="btn btn-outline" @click="modalAjuste = true">⚖️ Ajuste de Stock</button>
        <button class="btn btn-primary" @click="modalEntrada = true">+ Registrar Compra (USD / Bs.)</button>
      </div>
    </div>

    <div v-if="alertaMsg" class="badge badge-usd" style="width: 100%; padding: 0.65rem; margin-bottom: 0.9rem">
      ✓ {{ alertaMsg }}
    </div>
    <div v-if="errorMsg" class="badge badge-danger" style="width: 100%; padding: 0.65rem; margin-bottom: 0.9rem">
      ⚠️ {{ errorMsg }}
    </div>

    <!-- Pestañas -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem">
      <button :class="['btn', tabActiva === 'kardex' ? 'btn-dark' : 'btn-outline']" @click="tabActiva = 'kardex'">
        📜 Movimientos del Kardex ({{ kardex.length }})
      </button>
      <button :class="['btn', tabActiva === 'entradas' ? 'btn-dark' : 'btn-outline']" @click="tabActiva = 'entradas'">
        🚚 Facturas de Compra / Entradas ({{ entradas.length }})
      </button>
    </div>

    <!-- Vista Kardex Inmutable -->
    <div v-if="tabActiva === 'kardex'" class="card">
      <div class="grid-3" style="margin-bottom: 1rem">
        <select v-model="filtroProductoId" class="form-control" @change="cargarTodo">
          <option value="">Todos los productos</option>
          <option v-for="p in productos" :key="p.id" :value="p.id">{{ p.sku }} - {{ p.nombre }}</option>
        </select>

        <select v-model="filtroTipo" class="form-control" @change="cargarTodo">
          <option value="">Todos los tipos de movimiento</option>
          <option value="ENTRADA_COMPRA">ENTRADA_COMPRA</option>
          <option value="SALIDA_VENTA">SALIDA_VENTA</option>
          <option value="ANULACION_VENTA">ANULACION_VENTA</option>
          <option value="AJUSTE_POSITIVO">AJUSTE_POSITIVO</option>
          <option value="AJUSTE_NEGATIVO">AJUSTE_NEGATIVO</option>
        </select>

        <button class="btn btn-outline" @click="cargarTodo">↻ Refrescar Kardex</button>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Producto</th>
              <th>Tipo Movimiento</th>
              <th>Cantidad</th>
              <th>Stock Ant. → Resultante</th>
              <th>Costo USD / Bs.</th>
              <th>Observación</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in kardex" :key="m.id">
              <td class="mono">#{{ m.id }}</td>
              <td style="font-size: 0.78rem">{{ m.created_at }}</td>
              <td>
                <strong>{{ m.producto_nombre }}</strong>
                <div class="mono" style="font-size: 0.72rem; color: var(--text-muted)">{{ m.sku }}</div>
              </td>
              <td>
                <span
                  :class="[
                    'badge',
                    m.tipo_movimiento.includes('ENTRADA') || m.tipo_movimiento.includes('POSITIVO') || m.tipo_movimiento.includes('ANULACION')
                      ? 'badge-usd'
                      : 'badge-danger'
                  ]"
                >
                  {{ m.tipo_movimiento }}
                </span>
              </td>
              <td class="mono">
                <strong>{{ Number(m.cantidad).toFixed(3) }} {{ m.unidad_venta_codigo }}</strong>
              </td>
              <td class="mono">
                {{ Number(m.stock_anterior).toFixed(3) }} → <strong>{{ Number(m.stock_resultante).toFixed(3) }}</strong>
              </td>
              <td class="mono" style="font-size: 0.78rem">
                {{ tasaStore.formatUsd(m.costo_unitario_usd) }}<br />
                <span style="color: var(--text-muted)">Bs. {{ Number(m.costo_unitario_ves).toFixed(2) }}</span>
              </td>
              <td style="font-size: 0.8rem">{{ m.observacion }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Vista Entradas de Compra -->
    <div v-else class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Documento</th>
              <th>Proveedor</th>
              <th>Moneda Ingreso</th>
              <th>Tasa Aplicada</th>
              <th>Total USD</th>
              <th>Total Bs.</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!entradas.length">
              <td colspan="8" style="text-align: center; color: var(--text-muted)">
                No se han registrado facturas de compra adicionales aún.
              </td>
            </tr>
            <tr v-for="e in entradas" :key="e.id">
              <td class="mono">#{{ e.id }}</td>
              <td class="mono"><strong>{{ e.numero_documento }}</strong></td>
              <td>{{ e.proveedor_nombre }}</td>
              <td>
                <span :class="['badge', e.moneda_ingreso === 'USD' ? 'badge-usd' : 'badge-ves']">
                  {{ e.moneda_ingreso }}
                </span>
              </td>
              <td class="mono">Bs. {{ Number(e.tasa_aplicada).toFixed(4) }}</td>
              <td class="mono"><strong>{{ tasaStore.formatUsd(e.total_usd) }}</strong></td>
              <td class="mono">{{ tasaStore.formatVes(e.total_ves) }}</td>
              <td style="font-size: 0.8rem">{{ e.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Registrar Compra -->
    <div v-if="modalEntrada" class="modal-backdrop" @click.self="modalEntrada = false">
      <div class="modal-box" style="max-width: 780px">
        <div class="modal-header">
          <h3>Registrar Entrada de Inventario / Compra a Proveedor</h3>
          <button class="btn btn-outline btn-sm" @click="modalEntrada = false">✕</button>
        </div>

        <form @submit.prevent="registrarEntradaCompra">
          <div class="grid-3">
            <div class="form-group">
              <label class="form-label">Nro. Factura / Nota Entrega *</label>
              <input v-model="entradaForm.numero_documento" type="text" class="form-control mono" placeholder="COMP-00105" required />
            </div>
            <div class="form-group">
              <label class="form-label">Proveedor *</label>
              <input v-model="entradaForm.proveedor_nombre" type="text" class="form-control" placeholder="Distribuidora Ferretera C.A." required />
            </div>
            <div class="form-group">
              <label class="form-label">Moneda de Factura del Proveedor</label>
              <select v-model="entradaForm.moneda_ingreso" class="form-control">
                <option value="USD">Dólares (USD $)</option>
                <option value="VES">Bolívares (VES Bs. - Convierte a USD)</option>
              </select>
            </div>
          </div>

          <h4 style="margin-bottom: 0.5rem; font-size: 0.85rem">Ítems Comprados (Aplica Factor de Conversión Automático)</h4>
          <div v-for="(item, idx) in entradaForm.items" :key="idx" class="grid-4" style="margin-bottom: 0.5rem; align-items: end">
            <div>
              <label class="form-label">Producto</label>
              <select v-model="item.producto_id" class="form-control" required>
                <option v-for="p in productos" :key="p.id" :value="p.id">
                  {{ p.sku }} - {{ p.nombre }} (Compra en {{ p.unidad_compra_codigo }} x {{ p.factor_conversion }})
                </option>
              </select>
            </div>
            <div>
              <label class="form-label">Cant. Comprada</label>
              <input v-model="item.cantidad_comprada" type="number" step="0.001" min="0.001" class="form-control mono" required />
            </div>
            <div>
              <label class="form-label">Costo Unitario ({{ entradaForm.moneda_ingreso }})</label>
              <input v-model="item.costo_unitario" type="number" step="0.01" min="0.01" class="form-control mono" required />
            </div>
            <div>
              <label class="form-label">Nuevo Precio Venta USD (Opc.)</label>
              <input v-model="item.precio_venta_usd" type="number" step="0.01" class="form-control mono" placeholder="Mantener actual" />
            </div>
          </div>

          <button type="button" class="btn btn-outline btn-sm" style="margin-bottom: 1rem" @click="agregarFilaCompra">
            + Añadir otra línea
          </button>

          <div style="display: flex; justify-content: flex-end; gap: 0.5rem">
            <button type="button" class="btn btn-outline" @click="modalEntrada = false">Cancelar</button>
            <button type="submit" class="btn btn-primary">Procesar Entrada y Alimentar Kardex</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Ajuste de Inventario -->
    <div v-if="modalAjuste" class="modal-backdrop" @click.self="modalAjuste = false">
      <div class="modal-box" style="max-width: 500px">
        <div class="modal-header">
          <h3>⚖️ Ajuste Manual de Inventario (Kardex)</h3>
          <button class="btn btn-outline btn-sm" @click="modalAjuste = false">✕</button>
        </div>

        <form @submit.prevent="registrarAjusteKardex">
          <div class="form-group">
            <label class="form-label">Producto *</label>
            <select v-model="ajusteForm.producto_id" class="form-control" required>
              <option v-for="p in productos" :key="p.id" :value="p.id">
                {{ p.sku }} - {{ p.nombre }} (Actual: {{ p.stock_actual }} {{ p.unidad_venta_codigo }})
              </option>
            </select>
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Tipo de Ajuste *</label>
              <select v-model="ajusteForm.tipo_movimiento" class="form-control">
                <option value="AJUSTE_POSITIVO">AJUSTE_POSITIVO (+)</option>
                <option value="AJUSTE_NEGATIVO">AJUSTE_NEGATIVO (-)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Cantidad (Unidad de Venta) *</label>
              <input v-model="ajusteForm.cantidad" type="number" step="0.001" min="0.001" class="form-control mono" required />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Motivo / Observación Auditable *</label>
            <input v-model="ajusteForm.observacion" type="text" class="form-control" placeholder="Ej. Conteo físico de auditoría / merma" required />
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.5rem">
            <button type="button" class="btn btn-outline" @click="modalAjuste = false">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Ajuste en Kardex</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
