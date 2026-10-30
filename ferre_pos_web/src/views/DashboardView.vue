<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useTasaStore } from '@/stores/tasaStore'

const tasaStore = useTasaStore()
const data = ref(null)
const loading = ref(true)

async function cargarDashboard() {
  loading.value = true
  try {
    const res = await api.get('/dashboard')
    data.value = res.data
    if (res.data?.tasa_activa) {
      tasaStore.setTasaActiva(res.data.tasa_activa)
    }
  } finally {
    loading.value = false
  }
}

onMounted(cargarDashboard)
</script>

<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem">
      <div>
        <h2 style="font-size: 1.35rem; font-weight: 800">Dashboard Operativo Bimonetario</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem">
          Resumen en tiempo real de facturación USD/VES, recaudación multipago e inventario crítico.
        </p>
      </div>
      <button class="btn btn-outline" @click="cargarDashboard">↻ Actualizar Indicadores</button>
    </div>

    <div v-if="loading" class="card">Cargando métricas del sistema...</div>

    <template v-else-if="data">
      <!-- KPIs Superiores -->
      <div class="grid-4" style="margin-bottom: 1.25rem">
        <div class="card">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700">VENTAS DE HOY (USD / BS)</div>
          <div class="mono" style="font-size: 1.45rem; font-weight: 800; color: var(--usd-green); margin-top: 0.35rem">
            {{ tasaStore.formatUsd(data.ventas_hoy.total_usd) }}
          </div>
          <div class="mono" style="font-size: 0.9rem; color: var(--ves-blue); font-weight: 600">
            {{ tasaStore.formatVes(data.ventas_hoy.total_ves) }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem">
            {{ data.ventas_hoy.cantidad_facturas }} factura(s) emitidas hoy
          </div>
        </div>

        <div class="card">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700">FACTURACIÓN ACUMULADA</div>
          <div class="mono" style="font-size: 1.45rem; font-weight: 800; margin-top: 0.35rem">
            {{ tasaStore.formatUsd(data.ventas_totales.total_usd) }}
          </div>
          <div class="mono" style="font-size: 0.9rem; color: var(--ves-blue); font-weight: 600">
            {{ tasaStore.formatVes(data.ventas_totales.total_ves) }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem">
            Total histórico: {{ data.ventas_totales.cantidad_facturas }} facturas
          </div>
        </div>

        <div class="card">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700">VALORIZACIÓN INVENTARIO (COSTO)</div>
          <div class="mono" style="font-size: 1.45rem; font-weight: 800; margin-top: 0.35rem">
            {{ tasaStore.formatUsd(data.valor_inventario.costo_total_usd) }}
          </div>
          <div class="mono" style="font-size: 0.9rem; color: var(--text-muted)">
            Venta est.: {{ tasaStore.formatUsd(data.valor_inventario.venta_estimada_usd) }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem">
            {{ data.valor_inventario.total_productos }} productos activos
          </div>
        </div>

        <div class="card">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700">TASA BCV APLICADA</div>
          <div class="mono" style="font-size: 1.45rem; font-weight: 800; color: var(--amber-dark); margin-top: 0.35rem">
            Bs. {{ Number(data.tasa_activa?.tasa_ves || tasaStore.tasaVes).toFixed(4) }}
          </div>
          <div style="font-size: 0.82rem; font-weight: 600">
            Fuente: {{ data.tasa_activa?.fuente || 'BCV Oficial' }}
          </div>
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem">
            Moneda Ancla: USD ($1.00)
          </div>
        </div>
      </div>

      <div class="grid-2">
        <!-- Recaudación por Método de Pago -->
        <div class="card">
          <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.85rem">
            💳 Recaudación por Método de Pago (Multipago)
          </h3>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Método</th>
                  <th>Moneda</th>
                  <th>Monto Original</th>
                  <th>Equivalente USD</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!data.pagos_por_metodo.length">
                  <td colspan="4" style="text-align: center; color: var(--text-muted)">
                    Sin pagos registrados aún.
                  </td>
                </tr>
                <tr v-for="m in data.pagos_por_metodo" :key="m.codigo">
                  <td>
                    <strong>{{ m.nombre }}</strong>
                    <div style="font-size: 0.72rem; color: var(--text-muted)">{{ m.transacciones }} op.</div>
                  </td>
                  <td>
                    <span :class="['badge', m.moneda === 'USD' ? 'badge-usd' : 'badge-ves']">
                      {{ m.moneda }}
                    </span>
                  </td>
                  <td class="mono">
                    {{ m.moneda === 'USD' ? tasaStore.formatUsd(m.total_moneda_origen) : tasaStore.formatVes(m.total_moneda_origen) }}
                  </td>
                  <td class="mono"><strong>{{ tasaStore.formatUsd(m.total_equivalente_usd) }}</strong></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Alertas de Stock Mínimo -->
        <div class="card">
          <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.85rem">
            ⚠️ Productos con Stock Crítico / Mínimo
          </h3>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Producto</th>
                  <th>Disponible</th>
                  <th>Mínimo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!data.bajo_stock.length">
                  <td colspan="4" style="text-align: center; color: var(--usd-green); font-weight: 600">
                    ✓ Todos los productos superan el stock mínimo.
                  </td>
                </tr>
                <tr v-for="p in data.bajo_stock" :key="p.id">
                  <td class="mono">{{ p.sku }}</td>
                  <td>{{ p.nombre }}</td>
                  <td class="mono">
                    <span class="badge badge-danger">{{ Number(p.stock_actual).toFixed(3) }} {{ p.unidad_venta_codigo }}</span>
                  </td>
                  <td class="mono">{{ Number(p.stock_minimo).toFixed(3) }} {{ p.unidad_venta_codigo }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
