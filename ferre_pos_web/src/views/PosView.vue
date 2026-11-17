<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/api'
import { useTasaStore } from '@/stores/tasaStore'
import { usePosStore } from '@/stores/posStore'

const tasaStore = useTasaStore()
const posStore = usePosStore()

const metodosPago = ref([])
const busqueda = ref('')
const resultadosBusqueda = ref([])
const recientes = ref([])
const indiceSeleccionado = ref(0)
const buscandoProductos = ref(false)
const searchInputRef = ref(null)
const cargando = ref(false)
const alertaError = ref('')
const alertaExito = ref('')
let debounceTimer = null

// Modal Multipago
const modalPago = ref(false)
const pagosActuales = ref([])
const metodoSeleccionadoId = ref(null)
const montoPagoInput = ref('')
const referenciaPagoInput = ref('')
const vueltoUsdInput = ref(0)
const vueltoVesInput = ref(0)
const procesandoVenta = ref(false)

// Modal Factura emitida
const facturaEmitida = ref(null)

// Búsqueda y registro de Cliente por Cédula / RIF
const buscandoCliente = ref(false)
const guardandoCliente = ref(false)
const mostrarCamposCliente = ref(false)
const ultimoDocumentoBuscado = ref('')

async function buscarClientePorDocumento(forzar = false) {
  const doc = posStore.clienteDocumento.trim().toUpperCase()
  if (!doc) {
    posStore.clienteEstadoBusqueda = ''
    return
  }
  if (!forzar && doc === ultimoDocumentoBuscado.value && posStore.clienteEstadoBusqueda) {
    return
  }

  posStore.clienteDocumento = doc
  ultimoDocumentoBuscado.value = doc
  buscandoCliente.value = true
  alertaError.value = ''

  try {
    const res = await api.get(`/clientes/buscar?documento=${encodeURIComponent(doc)}`)
    if (res.data) {
      posStore.setCliente(res.data)
      ultimoDocumentoBuscado.value = res.data.documento
      mostrarCamposCliente.value = Boolean(
        res.data.nombre || res.data.telefono || res.data.email || res.data.direccion
      )
    } else {
      posStore.clienteId = null
      posStore.clienteNombre = ''
      posStore.clienteTelefono = ''
      posStore.clienteEmail = ''
      posStore.clienteDireccion = ''
      posStore.clienteEstadoBusqueda = 'nuevo'
      mostrarCamposCliente.value = true
    }
  } catch (e) {
    alertaError.value = e.message
  } finally {
    buscandoCliente.value = false
  }
}

function alCambiarDocumento() {
  if (posStore.clienteDocumento.trim().toUpperCase() !== ultimoDocumentoBuscado.value) {
    posStore.clienteId = null
    posStore.clienteEstadoBusqueda = ''
  }
}

async function guardarClienteRapido() {
  const doc = posStore.clienteDocumento.trim().toUpperCase()
  if (!doc) {
    alertaError.value = 'La Cédula o RIF es obligatoria para registrar el cliente.'
    return
  }
  guardandoCliente.value = true
  alertaError.value = ''
  try {
    const res = await api.post('/clientes', {
      documento: doc,
      nombre: posStore.clienteNombre.trim() || null,
      telefono: posStore.clienteTelefono.trim() || null,
      email: posStore.clienteEmail.trim() || null,
      direccion: posStore.clienteDireccion.trim() || null
    })
    posStore.setCliente(res.data)
    ultimoDocumentoBuscado.value = res.data.documento
    alertaExito.value = `Cliente ${res.data.documento} guardado correctamente.`
  } catch (e) {
    alertaError.value = e.message
  } finally {
    guardandoCliente.value = false
  }
}

async function cargarDatosPos() {
  cargando.value = true
  try {
    const resMet = await api.get('/metodos-pago')
    metodosPago.value = resMet.data || []
    if (metodosPago.value.length && !metodoSeleccionadoId.value) {
      metodoSeleccionadoId.value = metodosPago.value[0].id
    }
  } finally {
    cargando.value = false
  }
}

onMounted(() => {
  cargarDatosPos()
  nextTick(() => searchInputRef.value?.focus())
})

async function ejecutarBusqueda(texto) {
  const q = texto.trim()
  if (!q) {
    resultadosBusqueda.value = []
    indiceSeleccionado.value = 0
    return []
  }

  buscandoProductos.value = true
  try {
    const res = await api.get(
      `/productos?activos=1&page=1&per_page=20&q=${encodeURIComponent(q)}`
    )
    const items = res.data?.items || []
    resultadosBusqueda.value = items
    indiceSeleccionado.value = 0
    return items
  } catch (e) {
    alertaError.value = e.message
    return []
  } finally {
    buscandoProductos.value = false
  }
}

function alEscribirBusqueda() {
  clearTimeout(debounceTimer)
  const q = busqueda.value.trim()
  if (!q) {
    resultadosBusqueda.value = []
    indiceSeleccionado.value = 0
    return
  }
  debounceTimer = setTimeout(() => {
    ejecutarBusqueda(q)
  }, 180)
}

function limpiarBusqueda() {
  clearTimeout(debounceTimer)
  busqueda.value = ''
  resultadosBusqueda.value = []
  indiceSeleccionado.value = 0
  nextTick(() => searchInputRef.value?.focus())
}

async function manejarTeclaBusqueda(e) {
  if (e.key === 'ArrowDown') {
    if (resultadosBusqueda.value.length) {
      e.preventDefault()
      indiceSeleccionado.value =
        (indiceSeleccionado.value + 1) % resultadosBusqueda.value.length
    }
    return
  }

  if (e.key === 'ArrowUp') {
    if (resultadosBusqueda.value.length) {
      e.preventDefault()
      indiceSeleccionado.value =
        (indiceSeleccionado.value - 1 + resultadosBusqueda.value.length) %
        resultadosBusqueda.value.length
    }
    return
  }

  if (e.key === 'Escape') {
    limpiarBusqueda()
    return
  }

  if (e.key === 'Enter') {
    e.preventDefault()
    const q = busqueda.value.trim()
    if (!q) return

    clearTimeout(debounceTimer)
    // Si aún no hay resultados (ej. escaneo rápido de código de barras), consultamos de inmediato
    let lista = resultadosBusqueda.value
    if (!lista.length || buscandoProductos.value) {
      lista = await ejecutarBusqueda(q)
    }
    if (!lista.length) return

    // Priorizar coincidencia exacta de código de barras o SKU; si no, el ítem resaltado
    const qLower = q.toLowerCase()
    const exacto = lista.find(
      (p) =>
        (p.codigo_barra && p.codigo_barra.toLowerCase() === qLower) ||
        p.sku.toLowerCase() === qLower
    )
    const elegido = exacto || lista[indiceSeleccionado.value] || lista[0]
    if (elegido && Number(elegido.stock_actual) > 0) {
      clickProducto(elegido, 1, true)
    } else if (elegido) {
      alertaError.value = `El producto "${elegido.nombre}" no tiene stock disponible.`
    }
  }
}

function clickProducto(prod, cant = 1, limpiarDespues = false) {
  alertaError.value = ''
  try {
    posStore.agregarProducto(prod, cant)
    // Guardar en accesos recientes de esta sesión (máximo 6)
    recientes.value = [
      prod,
      ...recientes.value.filter((r) => r.id !== prod.id)
    ].slice(0, 6)
    if (limpiarDespues) {
      limpiarBusqueda()
    }
  } catch (e) {
    alertaError.value = e.message
  }
}

const metodoSeleccionadoObj = computed(() =>
  metodosPago.value.find((m) => m.id === Number(metodoSeleccionadoId.value))
)

const totalPagadoEquivalenteUsd = computed(() => {
  const sum = pagosActuales.value.reduce((acc, p) => acc + Number(p.monto_equivalente_usd), 0)
  return Number(sum.toFixed(2))
})

const restantePorPagarUsd = computed(() => {
  const diff = posStore.totalUsd - totalPagadoEquivalenteUsd.value
  return diff > 0.009 ? Number(diff.toFixed(2)) : 0
})

const restantePorPagarVes = computed(() => tasaStore.aBolivares(restantePorPagarUsd.value))

const excedenteCambioUsd = computed(() => {
  const diff = totalPagadoEquivalenteUsd.value - posStore.totalUsd
  return diff > 0.009 ? Number(diff.toFixed(2)) : 0
})

const excedenteCambioVes = computed(() => tasaStore.aBolivares(excedenteCambioUsd.value))

async function abrirCheckoutMultipago() {
  alertaError.value = ''
  if (!posStore.items.length) {
    alertaError.value = 'Agregue al menos un producto al carrito antes de facturar.'
    return
  }
  if (!posStore.clienteDocumento.trim()) {
    alertaError.value = 'Debe ingresar la Cédula o RIF del cliente (único campo obligatorio).'
    return
  }
  if (!posStore.clienteEstadoBusqueda) {
    await buscarClientePorDocumento(true)
  }
  pagosActuales.value = []
  referenciaPagoInput.value = ''
  vueltoUsdInput.value = 0
  vueltoVesInput.value = 0

  if (metodosPago.value.length) {
    metodoSeleccionadoId.value = metodosPago.value[0].id
    autocompletarMontoRestante()
  }
  modalPago.value = true
}

function autocompletarMontoRestante() {
  const m = metodoSeleccionadoObj.value
  if (!m) return
  if (m.moneda === 'USD') {
    montoPagoInput.value = restantePorPagarUsd.value > 0 ? restantePorPagarUsd.value.toFixed(2) : ''
  } else {
    montoPagoInput.value = restantePorPagarVes.value > 0 ? restantePorPagarVes.value.toFixed(2) : ''
  }
}

function agregarLineaPago() {
  alertaError.value = ''
  const m = metodoSeleccionadoObj.value
  if (!m) return

  const monto = Number(montoPagoInput.value)
  if (!monto || monto <= 0) {
    alertaError.value = 'El monto del pago debe ser mayor a cero.'
    return
  }

  if (Number(m.requiere_referencia) === 1 && !referenciaPagoInput.value.trim()) {
    alertaError.value = `El método "${m.nombre}" requiere obligatoriamente número de referencia o lote.`
    return
  }

  const equivUsd =
    m.moneda === 'USD'
      ? Number(monto.toFixed(2))
      : Number((monto / tasaStore.tasaVes).toFixed(2))

  pagosActuales.value.push({
    metodo_pago_id: m.id,
    codigo: m.codigo,
    nombre: m.nombre,
    moneda: m.moneda,
    monto_original: Number(monto.toFixed(2)),
    monto_equivalente_usd: equivUsd,
    referencia_transaccion: referenciaPagoInput.value.trim() || null
  })

  referenciaPagoInput.value = ''
  autocompletarMontoRestante()
  vueltoUsdInput.value = excedenteCambioUsd.value
  vueltoVesInput.value = 0
}

function eliminarLineaPago(idx) {
  pagosActuales.value.splice(idx, 1)
  autocompletarMontoRestante()
  vueltoUsdInput.value = excedenteCambioUsd.value
}

async function confirmarFacturaBimonetaria() {
  alertaError.value = ''
  if (!pagosActuales.value.length) {
    alertaError.value = 'Debe agregar al menos un pago.'
    return
  }
  if (restantePorPagarUsd.value > 0.02) {
    alertaError.value = `Aún resta por cubrir ${tasaStore.formatUsd(restantePorPagarUsd.value)} (${tasaStore.formatVes(restantePorPagarVes.value)}).`
    return
  }

  procesandoVenta.value = true
  try {
    const payload = {
      cliente_documento: posStore.clienteDocumento.trim().toUpperCase(),
      cliente_nombre: posStore.clienteNombre.trim() || null,
      cliente_telefono: posStore.clienteTelefono.trim() || null,
      cliente_email: posStore.clienteEmail.trim() || null,
      cliente_direccion: posStore.clienteDireccion.trim() || null,
      descuento_usd: Number(posStore.descuentoUsd || 0),
      vuelto_usd: Number(vueltoUsdInput.value || 0),
      vuelto_ves: Number(vueltoVesInput.value || 0),
      items: posStore.items.map((i) => ({
        producto_id: i.producto.id,
        cantidad: Number(i.cantidad)
      })),
      pagos: pagosActuales.value.map((p) => ({
        metodo_pago_id: p.metodo_pago_id,
        monto_original: p.monto_original,
        referencia_transaccion: p.referencia_transaccion
      }))
    }

    const res = await api.post('/ventas', payload)
    facturaEmitida.value = res.data
    modalPago.value = false
    posStore.limpiarCarrito()
    ultimoDocumentoBuscado.value = ''
    mostrarCamposCliente.value = false
    alertaExito.value = `Factura ${res.data.numero_factura} emitida correctamente.`
    await cargarDatosPos()
  } catch (e) {
    alertaError.value = e.message
  } finally {
    procesandoVenta.value = false
  }
}
</script>

<template>
  <div class="pos-layout">
    <!-- Columna Izquierda: Buscador Inteligente Autocomplete -->
    <div class="pos-catalog">
      <div class="card search-card" style="margin-bottom: 1rem">
        <div class="search-input-wrap">
          <input
            ref="searchInputRef"
            v-model="busqueda"
            type="text"
            class="form-control search-input-lg"
            placeholder="🔍 Escribe el nombre, SKU o escanea el código de barras..."
            autofocus
            @input="alEscribirBusqueda"
            @keydown="manejarTeclaBusqueda"
          />
          <span v-if="buscandoProductos" class="search-status">Buscando...</span>
          <button
            v-else-if="busqueda"
            type="button"
            class="btn btn-outline btn-sm clear-search-btn"
            @click="limpiarBusqueda"
            title="Limpiar búsqueda (Esc)"
          >
            ✕
          </button>
        </div>

        <div class="search-hints">
          <span><kbd>↑</kbd> <kbd>↓</kbd> Navegar</span>
          <span><kbd>Enter</kbd> Agregar al ticket</span>
          <span><kbd>Esc</kbd> Limpiar</span>
        </div>
      </div>

      <div v-if="alertaError" class="badge badge-danger" style="width: 100%; padding: 0.65rem; margin-bottom: 0.85rem">
        ⚠️ {{ alertaError }}
      </div>
      <div v-if="alertaExito" class="badge badge-usd" style="width: 100%; padding: 0.65rem; margin-bottom: 0.85rem">
        ✓ {{ alertaExito }}
      </div>

      <!-- Resultados del Autocomplete en tiempo real -->
      <div v-if="busqueda.trim()" class="card autocomplete-panel">
        <div v-if="buscandoProductos && !resultadosBusqueda.length" class="autocomplete-empty">
          Buscando coincidencias para "<strong>{{ busqueda }}</strong>"...
        </div>

        <div v-else-if="!resultadosBusqueda.length" class="autocomplete-empty">
          No se encontraron productos activos para "<strong>{{ busqueda }}</strong>".
        </div>

        <div v-else class="autocomplete-list">
          <div
            v-for="(p, idx) in resultadosBusqueda"
            :key="p.id"
            class="autocomplete-item"
            :class="{
              'is-active': idx === indiceSeleccionado,
              'out-of-stock': p.stock_actual <= 0
            }"
            @mouseenter="indiceSeleccionado = idx"
          >
            <div class="ac-main" @click="p.stock_actual > 0 && clickProducto(p, 1, true)">
              <div class="ac-meta">
                <span class="mono ac-sku">{{ p.sku }}</span>
                <span v-if="p.codigo_barra" class="mono ac-barcode">· {{ p.codigo_barra }}</span>
                <span v-if="p.categoria_nombre" class="badge badge-amber ac-cat">{{ p.categoria_nombre }}</span>
                <span :class="['badge', p.permite_decimales ? 'badge-amber' : 'badge-ves']">
                  {{ p.unidad_venta_codigo }}
                </span>
              </div>
              <div class="ac-title">{{ p.nombre }}</div>
              <div class="ac-stock">
                Stock disponible:
                <strong class="mono" :style="{ color: p.stock_actual <= p.stock_minimo ? 'var(--danger)' : 'inherit' }">
                  {{ Number(p.stock_actual).toFixed(p.permite_decimales ? 3 : 0) }} {{ p.unidad_venta_codigo }}
                </strong>
              </div>
            </div>

            <div class="ac-right">
              <div class="ac-prices">
                <div class="price-usd mono">{{ tasaStore.formatUsd(p.precio_venta_usd) }}</div>
                <div class="price-ves mono">{{ tasaStore.formatVes(p.precio_venta_usd * tasaStore.tasaVes) }}</div>
              </div>

              <div class="ac-actions">
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="p.stock_actual <= 0"
                  @click="clickProducto(p, 1, true)"
                >
                  + Agregar
                </button>
                <div v-if="p.permite_decimales && p.stock_actual > 0" class="ac-fractions">
                  <button type="button" class="btn btn-outline btn-sm" @click="clickProducto(p, 0.25, true)">+0.25</button>
                  <button type="button" class="btn btn-outline btn-sm" @click="clickProducto(p, 0.5, true)">+0.50</button>
                  <button type="button" class="btn btn-outline btn-sm" @click="clickProducto(p, 2.5, true)">+2.50</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Estado Inicial Limpio (cuando no se está escribiendo) -->
      <div v-else class="card pos-idle-card">
        <div class="idle-hero">
          <div style="font-size: 2rem; margin-bottom: 0.4rem">🛒</div>
          <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 0.35rem">
            Listo para facturar
          </h3>
          <p style="color: var(--text-muted); font-size: 0.88rem; max-width: 440px; margin: 0 auto">
            Empieza a escribir el nombre o SKU del producto en el buscador superior, o escanea directamente el código de barras para añadirlo al ticket.
          </p>
        </div>

        <div v-if="recientes.length" class="recents-section">
          <div class="recents-title">Agregados recientemente en esta sesión</div>
          <div class="recents-grid">
            <button
              v-for="r in recientes"
              :key="r.id"
              type="button"
              class="recent-chip"
              :disabled="r.stock_actual <= 0"
              @click="clickProducto(r, 1, false)"
            >
              <span class="recent-name">{{ r.nombre }}</span>
              <span class="mono recent-price">{{ tasaStore.formatUsd(r.precio_venta_usd) }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Columna Derecha: Carrito Bimonetario en Tiempo Real -->
    <div class="pos-cart card">
      <div class="cart-header">
        <h3>🧾 Ticket de Venta Bimonetario</h3>
        <button v-if="posStore.items.length" class="btn btn-outline btn-sm" @click="posStore.limpiarCarrito">
          Vaciar
        </button>
      </div>

      <!-- Búsqueda y Registro de Cliente por Cédula / RIF -->
      <div class="client-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem">
          <label class="form-label" style="margin-bottom: 0">
            Cédula / RIF del Cliente <span style="color: var(--danger)">* (Obligatorio)</span>
          </label>
          <button
            type="button"
            class="btn btn-outline btn-sm"
            style="padding: 0.15rem 0.5rem; font-size: 0.72rem"
            @click="mostrarCamposCliente = !mostrarCamposCliente"
          >
            {{ mostrarCamposCliente ? 'Ocultar datos opcionales' : '+ Datos del cliente (Opcional)' }}
          </button>
        </div>

        <div style="display: flex; gap: 0.45rem">
          <input
            v-model="posStore.clienteDocumento"
            type="text"
            class="form-control mono"
            placeholder="Ej: V-12345678 o J-40123456-7"
            @input="alCambiarDocumento"
            @keyup.enter="buscarClientePorDocumento(true)"
            @blur="buscarClientePorDocumento(false)"
          />
          <button
            type="button"
            class="btn btn-dark btn-sm"
            :disabled="buscandoCliente || !posStore.clienteDocumento.trim()"
            @click="buscarClientePorDocumento(true)"
          >
            {{ buscandoCliente ? '...' : 'Buscar' }}
          </button>
          <button
            v-if="posStore.clienteDocumento"
            type="button"
            class="btn btn-outline btn-sm"
            title="Limpiar cliente"
            @click="posStore.limpiarCliente(); ultimoDocumentoBuscado = ''; mostrarCamposCliente = false"
          >
            ✕
          </button>
        </div>

        <!-- Estado de búsqueda del cliente -->
        <div
          v-if="posStore.clienteEstadoBusqueda === 'encontrado'"
          class="badge badge-usd"
          style="width: 100%; margin-top: 0.45rem; padding: 0.35rem 0.55rem; justify-content: space-between"
        >
          <span>
            ✓ Cliente registrado:
            <strong>{{ posStore.clienteNombre || 'Sin nombre registrado' }}</strong>
            <span v-if="posStore.clienteTelefono" class="mono"> · {{ posStore.clienteTelefono }}</span>
          </span>
          <a
            href="#"
            style="color: inherit; text-decoration: underline; font-size: 0.72rem"
            @click.prevent="mostrarCamposCliente = !mostrarCamposCliente"
          >
            {{ mostrarCamposCliente ? 'Cerrar' : 'Ver/Editar' }}
          </a>
        </div>

        <div
          v-else-if="posStore.clienteEstadoBusqueda === 'nuevo'"
          class="badge badge-amber"
          style="width: 100%; margin-top: 0.45rem; padding: 0.35rem 0.55rem"
        >
          Cliente nuevo ({{ posStore.clienteDocumento }}): los siguientes datos son opcionales y se guardarán con la venta.
        </div>

        <!-- Campos opcionales del cliente: Nombre, Teléfono, Email, Dirección -->
        <div v-if="mostrarCamposCliente" class="client-optional-fields">
          <div class="grid-2" style="gap: 0.5rem; margin-bottom: 0.5rem">
            <div>
              <label class="form-label" style="font-size: 0.72rem">Nombre / Razón Social (Opcional)</label>
              <input
                v-model="posStore.clienteNombre"
                type="text"
                class="form-control"
                placeholder="Ej. Juan Pérez / Constructora"
              />
            </div>
            <div>
              <label class="form-label" style="font-size: 0.72rem">Teléfono (Opcional)</label>
              <input
                v-model="posStore.clienteTelefono"
                type="text"
                class="form-control mono"
                placeholder="Ej. 0414-1234567"
              />
            </div>
          </div>

          <div class="grid-2" style="gap: 0.5rem">
            <div>
              <label class="form-label" style="font-size: 0.72rem">Email (Opcional)</label>
              <input
                v-model="posStore.clienteEmail"
                type="email"
                class="form-control"
                placeholder="cliente@correo.com"
              />
            </div>
            <div>
              <label class="form-label" style="font-size: 0.72rem">Dirección (Opcional)</label>
              <input
                v-model="posStore.clienteDireccion"
                type="text"
                class="form-control"
                placeholder="Av. / Calle / Sector"
              />
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem">
            <button
              type="button"
              class="btn btn-outline btn-sm"
              :disabled="guardandoCliente || !posStore.clienteDocumento.trim()"
              @click="guardarClienteRapido"
            >
              {{ guardandoCliente ? 'Guardando...' : 'Guardar / Actualizar ficha del cliente' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Lista de Ítems -->
      <div class="cart-items">
        <div v-if="!posStore.items.length" class="empty-cart">
          Seleccione productos del catálogo o escanee un código de barras para comenzar.
        </div>

        <div v-for="item in posStore.items" :key="item.producto.id" class="cart-row">
          <div style="flex: 1; min-width: 0">
            <div style="font-weight: 700; font-size: 0.84rem">{{ item.producto.nombre }}</div>
            <div class="mono" style="font-size: 0.75rem; color: var(--text-muted)">
              {{ tasaStore.formatUsd(item.producto.precio_venta_usd) }} /
              {{ tasaStore.formatVes(item.producto.precio_venta_usd * tasaStore.tasaVes) }}
              por {{ item.producto.unidad_venta_codigo }}
            </div>
          </div>

          <div class="cart-qty">
            <input
              :value="item.cantidad"
              type="number"
              :step="item.producto.permite_decimales ? '0.001' : '1'"
              :min="item.producto.permite_decimales ? '0.001' : '1'"
              :max="item.producto.stock_actual"
              class="form-control mono qty-input"
              @change="(e) => posStore.actualizarCantidad(item.producto.id, e.target.value)"
            />
            <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted)">
              {{ item.producto.unidad_venta_codigo }}
            </span>
          </div>

          <div class="cart-line-total mono">
            <div style="font-weight: 700; color: var(--usd-green)">
              {{ tasaStore.formatUsd(item.cantidad * item.producto.precio_venta_usd) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--ves-blue)">
              {{ tasaStore.formatVes(item.cantidad * item.producto.precio_venta_usd * tasaStore.tasaVes) }}
            </div>
          </div>

          <button class="btn btn-outline btn-sm" @click="posStore.eliminarItem(item.producto.id)">✕</button>
        </div>
      </div>

      <!-- Totales Simultáneos USD ($) y VES (Bs.) -->
      <div class="cart-summary">
        <div class="summary-line">
          <span>Subtotal:</span>
          <span class="mono">
            <strong>{{ tasaStore.formatUsd(posStore.subtotalUsd) }}</strong>
            <span style="color: var(--text-muted)"> · {{ tasaStore.formatVes(posStore.subtotalVes) }}</span>
          </span>
        </div>
        <div class="summary-line">
          <span>IVA (16%):</span>
          <span class="mono">
            <strong>{{ tasaStore.formatUsd(posStore.impuestoUsd) }}</strong>
            <span style="color: var(--text-muted)"> · {{ tasaStore.formatVes(posStore.impuestoVes) }}</span>
          </span>
        </div>

        <div class="total-dual-box">
          <div>
            <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 700">TOTAL EN DÓLARES (ANCLA)</div>
            <div class="mono" style="font-size: 1.55rem; font-weight: 800; color: #34d399">
              {{ tasaStore.formatUsd(posStore.totalUsd) }}
            </div>
          </div>
          <div style="text-align: right">
            <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 700">
              TOTAL EN BOLÍVARES (Tasa {{ tasaStore.tasaVes.toFixed(2) }})
            </div>
            <div class="mono" style="font-size: 1.35rem; font-weight: 800; color: #60a5fa">
              {{ tasaStore.formatVes(posStore.totalVes) }}
            </div>
          </div>
        </div>

        <button
          class="btn btn-primary"
          style="width: 100%; padding: 0.9rem; font-size: 1rem; margin-top: 0.85rem"
          :disabled="!posStore.items.length"
          @click="abrirCheckoutMultipago"
        >
          💳 Cobrar / Multipago Mixto (USD + Bs.)
        </button>
      </div>
    </div>

    <!-- Modal Multipago Bimonetario -->
    <div v-if="modalPago" class="modal-backdrop" @click.self="modalPago = false">
      <div class="modal-box" style="max-width: 780px">
        <div class="modal-header">
          <div>
            <h3>Cobro Multipago Bimonetario (USD / VES)</h3>
            <div style="font-size: 0.8rem; color: var(--text-muted)">
              Tasa congelada para esta factura: <strong class="mono">Bs. {{ tasaStore.tasaVes.toFixed(4) }} / $1</strong>
            </div>
          </div>
          <button class="btn btn-outline btn-sm" @click="modalPago = false">✕</button>
        </div>

        <!-- Resumen de saldos -->
        <div class="grid-3" style="margin-bottom: 1rem">
          <div class="card" style="background: #f8fafc; padding: 0.85rem">
            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700">TOTAL FACTURA</div>
            <div class="mono" style="font-size: 1.2rem; font-weight: 800">{{ tasaStore.formatUsd(posStore.totalUsd) }}</div>
            <div class="mono" style="font-size: 0.85rem; color: var(--ves-blue)">{{ tasaStore.formatVes(posStore.totalVes) }}</div>
          </div>

          <div class="card" style="background: var(--usd-bg); padding: 0.85rem">
            <div style="font-size: 0.72rem; color: var(--usd-green); font-weight: 700">ABONADO (EQUIV. USD)</div>
            <div class="mono" style="font-size: 1.2rem; font-weight: 800; color: var(--usd-green)">
              {{ tasaStore.formatUsd(totalPagadoEquivalenteUsd) }}
            </div>
            <div class="mono" style="font-size: 0.85rem; color: var(--usd-green)">
              {{ tasaStore.formatVes(tasaStore.aBolivares(totalPagadoEquivalenteUsd)) }}
            </div>
          </div>

          <div class="card" :style="{ background: restantePorPagarUsd > 0 ? '#fef2f2' : '#eff6ff', padding: '0.85rem' }">
            <div style="font-size: 0.72rem; font-weight: 700" :style="{ color: restantePorPagarUsd > 0 ? 'var(--danger)' : 'var(--ves-blue)' }">
              {{ restantePorPagarUsd > 0 ? 'FALTA POR COBRAR' : 'EXCEDENTE / VUELTO' }}
            </div>
            <div class="mono" style="font-size: 1.2rem; font-weight: 800">
              {{ tasaStore.formatUsd(restantePorPagarUsd > 0 ? restantePorPagarUsd : excedenteCambioUsd) }}
            </div>
            <div class="mono" style="font-size: 0.85rem">
              {{ tasaStore.formatVes(restantePorPagarUsd > 0 ? restantePorPagarVes : excedenteCambioVes) }}
            </div>
          </div>
        </div>

        <div v-if="alertaError" class="badge badge-danger" style="width: 100%; padding: 0.6rem; margin-bottom: 0.85rem">
          ⚠️ {{ alertaError }}
        </div>

        <!-- Formulario para añadir un método de pago -->
        <div class="card" style="background: #f8fafc; margin-bottom: 1rem">
          <div class="grid-3">
            <div class="form-group" style="margin-bottom: 0">
              <label class="form-label">Método de Pago</label>
              <select v-model="metodoSeleccionadoId" class="form-control" @change="autocompletarMontoRestante">
                <option v-for="m in metodosPago" :key="m.id" :value="m.id">
                  {{ m.nombre }} ({{ m.moneda }}) {{ Number(m.requiere_referencia) === 1 ? '· Req. Ref' : '' }}
                </option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom: 0">
              <label class="form-label">
                Monto en {{ metodoSeleccionadoObj?.moneda || 'USD' }}
              </label>
              <input
                v-model="montoPagoInput"
                type="number"
                step="0.01"
                min="0.01"
                class="form-control mono"
                placeholder="0.00"
              />
            </div>

            <div class="form-group" style="margin-bottom: 0">
              <label class="form-label">
                Referencia / Lote {{ Number(metodoSeleccionadoObj?.requiere_referencia) === 1 ? '(Obligatorio *)' : '(Opcional)' }}
              </label>
              <div style="display: flex; gap: 0.45rem">
                <input
                  v-model="referenciaPagoInput"
                  type="text"
                  class="form-control mono"
                  :placeholder="Number(metodoSeleccionadoObj?.requiere_referencia) === 1 ? 'Nro. Ref / Order ID' : 'Efectivo'"
                />
                <button type="button" class="btn btn-dark" @click="agregarLineaPago">+ Añadir</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabla de pagos agregados -->
        <div class="table-wrap" style="margin-bottom: 1rem">
          <table class="data-table">
            <thead>
              <tr>
                <th>Método</th>
                <th>Moneda</th>
                <th>Monto Original</th>
                <th>Equivalente USD</th>
                <th>Referencia</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!pagosActuales.length">
                <td colspan="6" style="text-align: center; color: var(--text-muted)">
                  Añada uno o varios métodos de pago (ej. $10 Efectivo USD + resto en Pago Móvil Bs.)
                </td>
              </tr>
              <tr v-for="(p, index) in pagosActuales" :key="index">
                <td><strong>{{ p.nombre }}</strong></td>
                <td>
                  <span :class="['badge', p.moneda === 'USD' ? 'badge-usd' : 'badge-ves']">{{ p.moneda }}</span>
                </td>
                <td class="mono">
                  {{ p.moneda === 'USD' ? tasaStore.formatUsd(p.monto_original) : tasaStore.formatVes(p.monto_original) }}
                </td>
                <td class="mono"><strong>{{ tasaStore.formatUsd(p.monto_equivalente_usd) }}</strong></td>
                <td class="mono">{{ p.referencia_transaccion || '—' }}</td>
                <td>
                  <button class="btn btn-outline btn-sm" @click="eliminarLineaPago(index)">✕</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Registro de Vuelto (si hay excedente) -->
        <div v-if="excedenteCambioUsd > 0" class="grid-2" style="margin-bottom: 1rem">
          <div class="form-group" style="margin-bottom: 0">
            <label class="form-label">Vuelto entregado en USD ($)</label>
            <input v-model="vueltoUsdInput" type="number" step="0.01" min="0" class="form-control mono" />
          </div>
          <div class="form-group" style="margin-bottom: 0">
            <label class="form-label">Vuelto entregado en Bolívares (Bs.)</label>
            <input v-model="vueltoVesInput" type="number" step="0.01" min="0" class="form-control mono" />
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.65rem">
          <button type="button" class="btn btn-outline" @click="modalPago = false">Cancelar</button>
          <button
            type="button"
            class="btn btn-success"
            :disabled="procesandoVenta || !pagosActuales.length || restantePorPagarUsd > 0.02"
            @click="confirmarFacturaBimonetaria"
          >
            {{ procesandoVenta ? 'Procesando Transacción...' : '✓ Emitir Factura y Descontar Kardex' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Comprobante de Factura Emitida -->
    <div v-if="facturaEmitida" class="modal-backdrop" @click.self="facturaEmitida = null">
      <div class="modal-box" style="max-width: 520px">
        <div class="modal-header">
          <h3>🧾 Comprobante Fiscal Bimonetario</h3>
          <button class="btn btn-outline btn-sm" @click="facturaEmitida = null">✕</button>
        </div>

        <div class="mono" style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px dashed #cbd5e1">
          <div style="text-align: center; margin-bottom: 0.75rem">
            <strong>FERRETERÍA POS VENEZUELA</strong><br />
            Factura: <strong>{{ facturaEmitida.numero_factura }}</strong><br />
            Fecha: {{ facturaEmitida.created_at }}<br />
            Cédula / RIF: <strong>{{ facturaEmitida.cliente_documento }}</strong><br />
            <span v-if="facturaEmitida.cliente_nombre">Cliente: {{ facturaEmitida.cliente_nombre }}<br /></span>
            <span v-if="facturaEmitida.cliente_telefono">Tel: {{ facturaEmitida.cliente_telefono }}<br /></span>
            <span v-if="facturaEmitida.cliente_direccion">Dir: {{ facturaEmitida.cliente_direccion }}<br /></span>
            Tasa BCV Aplicada: Bs. {{ Number(facturaEmitida.tasa_aplicada).toFixed(4) }} / $1
          </div>
          <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0.5rem 0" />
          <div v-for="d in facturaEmitida.detalles" :key="d.id" style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 0.25rem">
            <span>{{ Number(d.cantidad) }} {{ d.unidad_codigo }} x {{ d.producto_nombre }}</span>
            <span>{{ tasaStore.formatUsd(d.subtotal_usd) }}</span>
          </div>
          <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0.5rem 0" />
          <div style="display: flex; justify-content: space-between; font-weight: 700">
            <span>TOTAL USD:</span>
            <span>{{ tasaStore.formatUsd(facturaEmitida.total_usd) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between; font-weight: 700; color: var(--ves-blue)">
            <span>TOTAL BOLÍVARES:</span>
            <span>{{ tasaStore.formatVes(facturaEmitida.total_ves) }}</span>
          </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem">
          <button class="btn btn-primary" @click="facturaEmitida = null">Listo · Nueva Venta</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pos-layout {
  display: grid;
  grid-template-columns: 1fr 430px;
  gap: 1.25rem;
  align-items: start;
}

.search-card {
  padding: 1rem;
}

.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.search-input-lg {
  font-size: 1rem;
  padding: 0.75rem 4.5rem 0.75rem 0.9rem;
  border-width: 2px;
}

.search-status {
  position: absolute;
  right: 0.85rem;
  font-size: 0.78rem;
  color: var(--text-muted);
  font-weight: 600;
}

.clear-search-btn {
  position: absolute;
  right: 0.65rem;
  padding: 0.2rem 0.5rem;
}

.search-hints {
  display: flex;
  gap: 1rem;
  margin-top: 0.55rem;
  font-size: 0.75rem;
  color: var(--text-muted);
  flex-wrap: wrap;
}

.search-hints kbd {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.08rem 0.35rem;
  font-size: 0.7rem;
  font-family: inherit;
  font-weight: 700;
  color: #334155;
}

.autocomplete-panel {
  padding: 0;
  overflow: hidden;
}

.autocomplete-empty {
  padding: 2rem 1.25rem;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.autocomplete-list {
  max-height: calc(100vh - 235px);
  overflow-y: auto;
}

.autocomplete-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.1s;
}

.autocomplete-item:last-child {
  border-bottom: none;
}

.autocomplete-item.is-active {
  background: #f8fafc;
  box-shadow: inset 3px 0 0 var(--primary, #f59e0b);
}

.autocomplete-item.out-of-stock {
  opacity: 0.55;
}

.ac-main {
  flex: 1;
  min-width: 0;
  cursor: pointer;
}

.ac-meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-bottom: 0.25rem;
}

.ac-sku {
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--text-muted);
}

.ac-barcode {
  font-size: 0.72rem;
  color: var(--text-muted);
}

.ac-title {
  font-weight: 700;
  font-size: 0.95rem;
  line-height: 1.3;
  margin-bottom: 0.25rem;
  word-break: break-word;
}

.ac-stock {
  font-size: 0.78rem;
  color: var(--text-muted);
}

.ac-right {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  flex-shrink: 0;
}

.ac-prices {
  text-align: right;
  min-width: 95px;
}

.ac-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.35rem;
}

.ac-fractions {
  display: flex;
  gap: 0.25rem;
}

.ac-fractions button {
  font-size: 0.68rem;
  padding: 0.15rem 0.35rem;
}

.pos-idle-card {
  padding: 2.25rem 1.5rem;
}

.idle-hero {
  text-align: center;
}

.recents-section {
  margin-top: 1.75rem;
  padding-top: 1.25rem;
  border-top: 1px dashed var(--border-color);
}

.recents-title {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-muted);
  margin-bottom: 0.65rem;
}

.recents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 0.55rem;
}

.recent-chip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 0.75rem;
  background: #f8fafc;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.12s, background-color 0.12s;
}

.recent-chip:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: var(--primary, #f59e0b);
}

.recent-name {
  font-size: 0.8rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.recent-price {
  font-size: 0.8rem;
  font-weight: 800;
  color: var(--usd-green);
  flex-shrink: 0;
}

.price-usd {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--usd-green);
}

.price-ves {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--ves-blue);
}

.pos-cart {
  position: sticky;
  top: 1rem;
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 95px);
}

.cart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  padding-bottom: 0.65rem;
  border-bottom: 1px solid var(--border-color);
}

.client-box {
  background: #f8fafc;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.65rem;
  margin-bottom: 0.75rem;
}

.client-optional-fields {
  margin-top: 0.6rem;
  padding-top: 0.6rem;
  border-top: 1px dashed var(--border-color);
}

.cart-items {
  flex: 1;
  overflow-y: auto;
  min-height: 180px;
  max-height: 330px;
  border-top: 1px solid var(--border-color);
  border-bottom: 1px solid var(--border-color);
  padding: 0.5rem 0;
}

.empty-cart {
  text-align: center;
  color: var(--text-muted);
  padding: 2.5rem 1rem;
  font-size: 0.85rem;
}

.cart-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 0;
  border-bottom: 1px dashed var(--border-color);
}

.cart-qty {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.qty-input {
  width: 78px;
  text-align: right;
  padding: 0.35rem 0.45rem;
}

.cart-line-total {
  width: 105px;
  text-align: right;
}

.cart-summary {
  padding-top: 0.85rem;
}

.summary-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  margin-bottom: 0.35rem;
}

.total-dual-box {
  background: #0f172a;
  color: #ffffff;
  padding: 0.9rem 1rem;
  border-radius: 10px;
  margin-top: 0.6rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

@media (max-width: 1100px) {
  .pos-layout {
    grid-template-columns: 1fr;
  }
}
</style>
