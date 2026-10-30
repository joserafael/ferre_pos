<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useTasaStore } from '@/stores/tasaStore'

const tasaStore = useTasaStore()
const productos = ref([])
const categorias = ref([])
const unidades = ref([])
const busqueda = ref('')
const loading = ref(false)
const modalAbierto = ref(false)
const editandoId = ref(null)
const errorForm = ref('')
const guardando = ref(false)

const form = ref({
  sku: '',
  codigo_barra: '',
  nombre: '',
  categoria_id: '',
  unidad_compra_id: '',
  unidad_venta_id: '',
  factor_conversion: 1.0,
  costo_usd: 0.0,
  precio_venta_usd: 0.0,
  porcentaje_iva: 16.0,
  exento_iva: false,
  stock_actual: 0.0,
  stock_minimo: 5.0
})

async function cargarCatalogos() {
  loading.value = true
  try {
    const [resProd, resCat, resUni] = await Promise.all([
      api.get(`/productos${busqueda.value ? `?q=${encodeURIComponent(busqueda.value)}` : ''}`),
      api.get('/categorias'),
      api.get('/unidades-medida')
    ])
    productos.value = resProd.data || []
    categorias.value = resCat.data || []
    unidades.value = resUni.data || []
  } finally {
    loading.value = false
  }
}

onMounted(cargarCatalogos)

function abrirNuevo() {
  editandoId.value = null
  errorForm.value = ''
  form.value = {
    sku: '',
    codigo_barra: '',
    nombre: '',
    categoria_id: categorias.value[0]?.id || 1,
    unidad_compra_id: unidades.value[0]?.id || 1,
    unidad_venta_id: unidades.value[0]?.id || 1,
    factor_conversion: 1.0,
    costo_usd: 1.0,
    precio_venta_usd: 1.5,
    porcentaje_iva: 16.0,
    exento_iva: false,
    stock_actual: 0.0,
    stock_minimo: 5.0
  }
  modalAbierto.value = true
}

function abrirEditar(p) {
  editandoId.value = p.id
  errorForm.value = ''
  form.value = {
    sku: p.sku,
    codigo_barra: p.codigo_barra || '',
    nombre: p.nombre,
    categoria_id: p.categoria_id,
    unidad_compra_id: p.unidad_compra_id,
    unidad_venta_id: p.unidad_venta_id,
    factor_conversion: p.factor_conversion,
    costo_usd: p.costo_usd,
    precio_venta_usd: p.precio_venta_usd,
    porcentaje_iva: p.porcentaje_iva,
    exento_iva: Boolean(p.exento_iva),
    stock_actual: p.stock_actual,
    stock_minimo: p.stock_minimo
  }
  modalAbierto.value = true
}

async function guardarProducto() {
  errorForm.value = ''
  guardando.value = true
  try {
    if (editandoId.value) {
      await api.put(`/productos/${editandoId.value}`, form.value)
    } else {
      await api.post('/productos', form.value)
    }
    modalAbierto.value = false
    await cargarCatalogos()
  } catch (e) {
    errorForm.value = e.message
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800">Catálogo de Productos & Factor de Conversión</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem">
          Precios anclados en USD con conversión dinámica a Bolívares y soporte de venta fraccionada (M, KG, L).
        </p>
      </div>
      <button class="btn btn-primary" @click="abrirNuevo">+ Nuevo Producto Ferretero</button>
    </div>

    <div class="card" style="margin-bottom: 1rem">
      <div style="display: flex; gap: 0.75rem">
        <input
          v-model="busqueda"
          type="text"
          class="form-control"
          placeholder="Filtrar por SKU, código de barras o nombre..."
          @keyup.enter="cargarCatalogos"
        />
        <button class="btn btn-dark" @click="cargarCatalogos">Buscar</button>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>SKU / Código</th>
              <th>Producto / Categoría</th>
              <th>Conversión Compra → Venta</th>
              <th>Costo USD</th>
              <th>Precio USD ($)</th>
              <th>Precio Bs. (Tasa {{ tasaStore.tasaVes.toFixed(2) }})</th>
              <th>Stock Actual</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in productos" :key="p.id">
              <td class="mono">
                <strong>{{ p.sku }}</strong>
                <div style="font-size: 0.72rem; color: var(--text-muted)">{{ p.codigo_barra || 'Sin barra' }}</div>
              </td>
              <td>
                <div style="font-weight: 700">{{ p.nombre }}</div>
                <span class="badge badge-amber" style="margin-top: 0.2rem">{{ p.categoria_nombre }}</span>
              </td>
              <td class="mono" style="font-size: 0.8rem">
                1 {{ p.unidad_compra_codigo }} = <strong>{{ Number(p.factor_conversion).toFixed(3) }} {{ p.unidad_venta_codigo }}</strong>
                <div style="font-size: 0.72rem; color: var(--text-muted)">
                  {{ p.permite_decimales ? '✓ Permite decimales' : 'Solo enteros' }}
                </div>
              </td>
              <td class="mono">{{ tasaStore.formatUsd(p.costo_usd) }}</td>
              <td class="mono">
                <strong style="color: var(--usd-green)">{{ tasaStore.formatUsd(p.precio_venta_usd) }}</strong>
              </td>
              <td class="mono">
                <strong style="color: var(--ves-blue)">
                  {{ tasaStore.formatVes(p.precio_venta_usd * tasaStore.tasaVes) }}
                </strong>
              </td>
              <td class="mono">
                <span :class="['badge', p.stock_actual <= p.stock_minimo ? 'badge-danger' : 'badge-usd']">
                  {{ Number(p.stock_actual).toFixed(p.permite_decimales ? 3 : 0) }} {{ p.unidad_venta_codigo }}
                </span>
              </td>
              <td>
                <button class="btn btn-outline btn-sm" @click="abrirEditar(p)">Editar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Crear/Editar Producto -->
    <div v-if="modalAbierto" class="modal-backdrop" @click.self="modalAbierto = false">
      <div class="modal-box">
        <div class="modal-header">
          <h3>{{ editandoId ? 'Editar Producto' : 'Registrar Nuevo Producto' }}</h3>
          <button class="btn btn-outline btn-sm" @click="modalAbierto = false">✕</button>
        </div>

        <div v-if="errorForm" class="badge badge-danger" style="width: 100%; padding: 0.6rem; margin-bottom: 0.9rem">
          {{ errorForm }}
        </div>

        <form @submit.prevent="guardarProducto">
          <div class="grid-3">
            <div class="form-group">
              <label class="form-label">SKU *</label>
              <input v-model="form.sku" type="text" class="form-control mono" placeholder="ELE-CAB-12" required />
            </div>
            <div class="form-group">
              <label class="form-label">Código de Barras</label>
              <input v-model="form.codigo_barra" type="text" class="form-control mono" placeholder="759100..." />
            </div>
            <div class="form-group">
              <label class="form-label">Categoría *</label>
              <select v-model="form.categoria_id" class="form-control" required>
                <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Nombre Descriptivo del Producto *</label>
            <input v-model="form.nombre" type="text" class="form-control" required />
          </div>

          <div class="grid-3">
            <div class="form-group">
              <label class="form-label">Unidad de Compra (Proveedor)</label>
              <select v-model="form.unidad_compra_id" class="form-control" required>
                <option v-for="u in unidades" :key="u.id" :value="u.id">
                  {{ u.nombre }} ({{ u.codigo }})
                </option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Unidad de Venta (POS)</label>
              <select v-model="form.unidad_venta_id" class="form-control" required>
                <option v-for="u in unidades" :key="u.id" :value="u.id">
                  {{ u.nombre }} ({{ u.codigo }}) {{ u.permite_decimales ? '· Decimales' : '· Entero' }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Factor de Conversión (DEC 12,3)</label>
              <input v-model="form.factor_conversion" type="number" step="0.001" min="0.001" class="form-control mono" required />
            </div>
          </div>

          <div class="grid-3">
            <div class="form-group">
              <label class="form-label">Costo Unitario USD ($)</label>
              <input v-model="form.costo_usd" type="number" step="0.01" min="0" class="form-control mono" required />
            </div>
            <div class="form-group">
              <label class="form-label">Precio Venta USD ($)</label>
              <input v-model="form.precio_venta_usd" type="number" step="0.01" min="0.01" class="form-control mono" required />
            </div>
            <div class="form-group">
              <label class="form-label">Equivalente actual en Bs.</label>
              <input
                :value="tasaStore.formatVes(form.precio_venta_usd * tasaStore.tasaVes)"
                type="text"
                class="form-control mono"
                disabled
              />
            </div>
          </div>

          <div class="grid-3">
            <div v-if="!editandoId" class="form-group">
              <label class="form-label">Stock Inicial (Genera Kardex)</label>
              <input v-model="form.stock_actual" type="number" step="0.001" min="0" class="form-control mono" />
            </div>
            <div class="form-group">
              <label class="form-label">Stock Mínimo (Alerta)</label>
              <input v-model="form.stock_minimo" type="number" step="0.001" min="0" class="form-control mono" />
            </div>
            <div class="form-group">
              <label class="form-label">Impuesto IVA (%)</label>
              <div style="display: flex; align-items: center; gap: 0.5rem">
                <input v-model="form.porcentaje_iva" type="number" step="0.01" class="form-control mono" :disabled="form.exento_iva" />
                <label style="font-size: 0.78rem; white-space: nowrap">
                  <input v-model="form.exento_iva" type="checkbox" /> Exento
                </label>
              </div>
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem">
            <button type="button" class="btn btn-outline" @click="modalAbierto = false">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="guardando">
              {{ guardando ? 'Guardando...' : 'Guardar Producto' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
