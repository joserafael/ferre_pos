<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useTasaStore } from '@/stores/tasaStore'

const tasaStore = useTasaStore()
const ventas = ref([])
const busqueda = ref('')
const facturaDetalle = ref(null)
const errorMsg = ref('')
const exitoMsg = ref('')

async function cargarVentas() {
  const res = await api.get(`/ventas${busqueda.value ? `?q=${encodeURIComponent(busqueda.value)}` : ''}`)
  ventas.value = res.data || []
}

onMounted(cargarVentas)

async function verDetalle(id) {
  const res = await api.get(`/ventas/${id}`)
  facturaDetalle.value = res.data
}

async function anularFactura(venta) {
  errorMsg.value = ''
  exitoMsg.value = ''
  const motivo = window.prompt(
    `¿Confirma anular la factura ${venta.numero_factura} y devolver los productos al Kardex?\nIngrese el motivo de anulación:`,
    'Devolución de cliente en tienda'
  )
  if (!motivo) return

  try {
    const res = await api.post(`/ventas/${venta.id}/anular`, { motivo })
    exitoMsg.value = `Factura ${venta.numero_factura} anulada y stock revertido en Kardex.`
    facturaDetalle.value = res.data
    await cargarVentas()
  } catch (e) {
    errorMsg.value = e.message
  }
}
</script>

<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800">Historial de Facturas & Multipago</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem">
          Consulta de ventas con tasa histórica congelada, desglose de pagos mixtos y anulación con reversión de Kardex.
        </p>
      </div>
      <div style="display: flex; gap: 0.5rem">
        <input
          v-model="busqueda"
          type="text"
          class="form-control"
          placeholder="Buscar por Nro. Factura o Cliente..."
          @keyup.enter="cargarVentas"
        />
        <button class="btn btn-dark" @click="cargarVentas">Buscar</button>
      </div>
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
              <th>Factura</th>
              <th>Cliente</th>
              <th>Tasa Congelada</th>
              <th>Total USD ($)</th>
              <th>Total Bs.</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!ventas.length">
              <td colspan="8" style="text-align: center; color: var(--text-muted)">
                No hay facturas registradas aún. Realice una venta desde el POS.
              </td>
            </tr>
            <tr v-for="v in ventas" :key="v.id">
              <td class="mono"><strong>{{ v.numero_factura }}</strong></td>
              <td>
                <strong>{{ v.cliente_nombre }}</strong>
                <div class="mono" style="font-size: 0.72rem; color: var(--text-muted)">{{ v.cliente_documento }}</div>
              </td>
              <td class="mono">Bs. {{ Number(v.tasa_aplicada).toFixed(4) }}</td>
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
              <td style="font-size: 0.8rem">{{ v.created_at }}</td>
              <td style="display: flex; gap: 0.4rem">
                <button class="btn btn-outline btn-sm" @click="verDetalle(v.id)">Ver Detalle</button>
                <button
                  v-if="v.estado === 'COMPLETADA'"
                  class="btn btn-danger btn-sm"
                  @click="anularFactura(v)"
                >
                  Anular
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Detalle Completo de Factura y Pagos -->
    <div v-if="facturaDetalle" class="modal-backdrop" @click.self="facturaDetalle = null">
      <div class="modal-box" style="max-width: 720px">
        <div class="modal-header">
          <div>
            <h3>Factura {{ facturaDetalle.numero_factura }}</h3>
            <span :class="['badge', facturaDetalle.estado === 'COMPLETADA' ? 'badge-usd' : 'badge-danger']">
              {{ facturaDetalle.estado }}
            </span>
          </div>
          <button class="btn btn-outline btn-sm" @click="facturaDetalle = null">✕</button>
        </div>

        <div class="grid-3" style="margin-bottom: 1rem; font-size: 0.83rem">
          <div>
            <strong>Cliente:</strong> {{ facturaDetalle.cliente_nombre }}<br />
            <strong>Documento:</strong> <span class="mono">{{ facturaDetalle.cliente_documento }}</span>
          </div>
          <div>
            <strong>Tasa Congelada:</strong> <span class="mono">Bs. {{ Number(facturaDetalle.tasa_aplicada).toFixed(4) }}</span><br />
            <strong>Fecha:</strong> {{ facturaDetalle.created_at }}
          </div>
          <div>
            <strong>Vuelto USD:</strong> <span class="mono">{{ tasaStore.formatUsd(facturaDetalle.vuelto_usd) }}</span><br />
            <strong>Vuelto Bs.:</strong> <span class="mono">{{ tasaStore.formatVes(facturaDetalle.vuelto_ves) }}</span>
          </div>
        </div>

        <h4 style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.4rem">DETALLE DE PRODUCTOS</h4>
        <div class="table-wrap" style="margin-bottom: 1rem">
          <table class="data-table">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unit. (USD / Bs.)</th>
                <th>Subtotal (USD / Bs.)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in facturaDetalle.detalles" :key="d.id">
                <td class="mono">{{ d.sku }}</td>
                <td>{{ d.producto_nombre }}</td>
                <td class="mono"><strong>{{ Number(d.cantidad) }} {{ d.unidad_codigo }}</strong></td>
                <td class="mono">
                  {{ tasaStore.formatUsd(d.precio_unitario_usd) }} / {{ tasaStore.formatVes(d.precio_unitario_ves) }}
                </td>
                <td class="mono">
                  <strong>{{ tasaStore.formatUsd(d.subtotal_usd) }}</strong> / {{ tasaStore.formatVes(d.subtotal_ves) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <h4 style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.4rem">DESGLOSE MULTIPAGO (venta_pagos)</h4>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Método de Pago</th>
                <th>Moneda</th>
                <th>Monto Original</th>
                <th>Equivalente USD</th>
                <th>Referencia / Lote</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in facturaDetalle.pagos" :key="p.id">
                <td><strong>{{ p.metodo_nombre }}</strong> ({{ p.metodo_codigo }})</td>
                <td>
                  <span :class="['badge', p.moneda === 'USD' ? 'badge-usd' : 'badge-ves']">{{ p.moneda }}</span>
                </td>
                <td class="mono">
                  {{ p.moneda === 'USD' ? tasaStore.formatUsd(p.monto_original) : tasaStore.formatVes(p.monto_original) }}
                </td>
                <td class="mono"><strong>{{ tasaStore.formatUsd(p.monto_equivalente_usd) }}</strong></td>
                <td class="mono">{{ p.referencia_transaccion || 'Efectivo / Sin Ref.' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
