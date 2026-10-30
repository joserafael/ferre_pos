<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { useTasaStore } from '@/stores/tasaStore'

const router = useRouter()
const authStore = useAuthStore()
const tasaStore = useTasaStore()

const modalTasa = ref(false)
const nuevaTasaInput = ref('')
const fuenteTasaInput = ref('BCV Oficial')
const guardandoTasa = ref(false)
const errorTasa = ref('')

onMounted(() => {
  tasaStore.fetchTasaActiva()
})

function abrirModalTasa() {
  nuevaTasaInput.value = tasaStore.tasaVes.toFixed(4)
  fuenteTasaInput.value = tasaStore.tasaActiva?.fuente || 'BCV Oficial'
  errorTasa.value = ''
  tasaStore.fetchHistorial()
  modalTasa.value = true
}

async function guardarNuevaTasa() {
  errorTasa.value = ''
  const valor = Number(nuevaTasaInput.value)
  if (!valor || valor <= 0) {
    errorTasa.value = 'Ingrese una tasa válida mayor a cero.'
    return
  }
  guardandoTasa.value = true
  try {
    await tasaStore.actualizarTasa(valor, fuenteTasaInput.value)
    modalTasa.value = false
  } catch (e) {
    errorTasa.value = e.message
  } finally {
    guardandoTasa.value = false
  }
}

function cerrarSesion() {
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-icon">🛠️</div>
        <div>
          <div class="brand-title">FERRE<span style="color: var(--amber-primary)">POS</span></div>
          <div class="brand-sub">Bimonetario USD / VES</div>
        </div>
      </div>

      <nav class="nav-menu">
        <router-link to="/pos" class="nav-link" active-class="active">
          <span>🛒</span> Punto de Venta (POS)
        </router-link>
        <router-link to="/dashboard" class="nav-link" active-class="active">
          <span>📊</span> Dashboard Bimonetario
        </router-link>
        <router-link to="/productos" class="nav-link" active-class="active">
          <span>🔩</span> Catálogo & Conversión
        </router-link>
        <router-link to="/categorias" class="nav-link" active-class="active">
          <span>🏷️</span> Categorías & Unidades
        </router-link>
        <router-link to="/inventario" class="nav-link" active-class="active">
          <span>📦</span> Compras & Kardex
        </router-link>
        <router-link to="/ventas" class="nav-link" active-class="active">
          <span>🧾</span> Facturas & Multipago
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info">
          <div class="user-name">{{ authStore.usuario?.nombre || 'Administrador' }}</div>
          <div class="user-role">{{ authStore.usuario?.rol || 'ADMIN' }}</div>
        </div>
        <button class="btn btn-outline btn-sm" style="width: 100%" @click="cerrarSesion">
          Cerrar Sesión
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="main-area">
      <!-- Top Header Bimonetario -->
      <header class="topbar">
        <div class="topbar-left">
          <span class="badge badge-amber">SISTEMA FERRETERO VENEZUELA</span>
          <span style="color: var(--text-muted); font-size: 0.82rem">
            Inventario Fraccionado (DEC 12,3) · Moneda Ancla USD
          </span>
        </div>

        <div class="topbar-right">
          <div class="rate-pill" @click="abrirModalTasa" title="Clic para actualizar tasa de cambio">
            <span class="rate-label">TASA ACTIVA ({{ tasaStore.tasaActiva?.fuente || 'BCV' }}):</span>
            <strong class="mono rate-val">Bs. {{ tasaStore.tasaVes.toFixed(4) }} / $1</strong>
            <span class="rate-edit">Cambiar ✎</span>
          </div>
        </div>
      </header>

      <main class="page-content">
        <router-view />
      </main>
    </div>

    <!-- Modal Actualizar Tasa de Cambio -->
    <div v-if="modalTasa" class="modal-backdrop" @click.self="modalTasa = false">
      <div class="modal-box" style="max-width: 520px">
        <div class="modal-header">
          <h3>Actualizar Tasa de Cambio (USD → VES)</h3>
          <button class="btn btn-outline btn-sm" @click="modalTasa = false">✕</button>
        </div>

        <form @submit.prevent="guardarNuevaTasa">
          <div v-if="errorTasa" class="badge badge-danger" style="margin-bottom: 0.85rem; width: 100%; padding: 0.5rem">
            {{ errorTasa }}
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Tasa en Bolívares (DECIMAL 12,4)</label>
              <input
                v-model="nuevaTasaInput"
                type="number"
                step="0.0001"
                min="0.0001"
                class="form-control mono"
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label">Fuente / Referencia</label>
              <input
                v-model="fuenteTasaInput"
                type="text"
                class="form-control"
                placeholder="BCV Oficial"
                required
              />
            </div>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-bottom: 1.2rem">
            <button type="button" class="btn btn-outline" @click="modalTasa = false">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="guardandoTasa">
              {{ guardandoTasa ? 'Guardando...' : 'Activar Nueva Tasa' }}
            </button>
          </div>
        </form>

        <h4 style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.5rem">
          HISTORIAL DE TASAS RECIENTES
        </h4>
        <div class="table-wrap" style="max-height: 220px; overflow-y: auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tasa Bs/$</th>
                <th>Fuente</th>
                <th>Estado</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in tasaStore.historial" :key="t.id">
                <td class="mono">#{{ t.id }}</td>
                <td class="mono"><strong>Bs. {{ Number(t.tasa_ves).toFixed(4) }}</strong></td>
                <td>{{ t.fuente }}</td>
                <td>
                  <span :class="['badge', Number(t.activa) === 1 ? 'badge-usd' : 'badge-ves']">
                    {{ Number(t.activa) === 1 ? 'ACTIVA' : 'Histórica' }}
                  </span>
                </td>
                <td style="font-size: 0.78rem">{{ t.created_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 245px;
  background: var(--bg-sidebar);
  color: #f8fafc;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem 1.15rem;
  border-bottom: 1px solid #1e293b;
}

.brand-icon {
  font-size: 1.6rem;
}

.brand-title {
  font-weight: 800;
  font-size: 1.15rem;
  letter-spacing: 0.03em;
}

.brand-sub {
  font-size: 0.72rem;
  color: #94a3b8;
}

.nav-menu {
  padding: 1rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  flex: 1;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  color: #cbd5e1;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.875rem;
  transition: all 0.15s;
}

.nav-link:hover {
  background: var(--bg-sidebar-hover);
  color: #ffffff;
}

.nav-link.active {
  background: var(--amber-primary);
  color: #0f172a;
  font-weight: 700;
}

.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid #1e293b;
}

.user-info {
  margin-bottom: 0.65rem;
}

.user-name {
  font-weight: 600;
  font-size: 0.85rem;
}

.user-role {
  font-size: 0.72rem;
  color: var(--amber-primary);
  font-weight: 700;
}

.main-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.topbar {
  height: 60px;
  background: #ffffff;
  border-bottom: 1px solid var(--border-color);
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.rate-pill {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  background: #0f172a;
  color: #ffffff;
  padding: 0.4rem 0.85rem;
  border-radius: 999px;
  cursor: pointer;
  font-size: 0.82rem;
  transition: transform 0.12s;
}

.rate-pill:hover {
  transform: translateY(-1px);
}

.rate-label {
  color: #94a3b8;
  font-size: 0.75rem;
}

.rate-val {
  color: #fde047;
  font-size: 0.92rem;
}

.rate-edit {
  background: var(--amber-primary);
  color: #0f172a;
  padding: 0.12rem 0.5rem;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.72rem;
}

.page-content {
  padding: 1.35rem 1.5rem;
  flex: 1;
}
</style>
