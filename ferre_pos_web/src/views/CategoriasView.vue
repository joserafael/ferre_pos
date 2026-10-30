<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const categorias = ref([])
const unidades = ref([])
const errorMsg = ref('')

// Formulario Categoría
const catForm = ref({ id: null, nombre: '', descripcion: '' })
// Formulario Unidad
const uniForm = ref({ codigo: '', nombre: '', permite_decimales: false })

async function cargarDatos() {
  const [resCat, resUni] = await Promise.all([
    api.get('/categorias'),
    api.get('/unidades-medida')
  ])
  categorias.value = resCat.data || []
  unidades.value = resUni.data || []
}

onMounted(cargarDatos)

async function guardarCategoria() {
  errorMsg.value = ''
  try {
    if (catForm.value.id) {
      await api.put(`/categorias/${catForm.value.id}`, catForm.value)
    } else {
      await api.post('/categorias', catForm.value)
    }
    catForm.value = { id: null, nombre: '', descripcion: '' }
    await cargarDatos()
  } catch (e) {
    errorMsg.value = e.message
  }
}

function editarCategoria(c) {
  catForm.value = { id: c.id, nombre: c.nombre, descripcion: c.descripcion || '' }
}

async function eliminarCategoria(id) {
  errorMsg.value = ''
  try {
    await api.delete(`/categorias/${id}`)
    await cargarDatos()
  } catch (e) {
    errorMsg.value = e.message
  }
}

async function guardarUnidad() {
  errorMsg.value = ''
  try {
    await api.post('/unidades-medida', {
      codigo: uniForm.value.codigo,
      nombre: uniForm.value.nombre,
      permite_decimales: uniForm.value.permite_decimales ? 1 : 0
    })
    uniForm.value = { codigo: '', nombre: '', permite_decimales: false }
    await cargarDatos()
  } catch (e) {
    errorMsg.value = e.message
  }
}
</script>

<template>
  <div>
    <h2 style="font-size: 1.35rem; font-weight: 800; margin-bottom: 0.25rem">
      Categorías y Unidades de Medida
    </h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.25rem">
      Gestión del clasificador ferretero y unidades con control de bandera booleana <code>permite_decimales</code>.
    </p>

    <div v-if="errorMsg" class="badge badge-danger" style="width: 100%; padding: 0.65rem; margin-bottom: 1rem">
      ⚠️ {{ errorMsg }}
    </div>

    <div class="grid-2">
      <!-- CRUD Categorías -->
      <div class="card">
        <h3 style="margin-bottom: 0.85rem">🏷️ Categorías de Ferretería</h3>
        <form style="margin-bottom: 1rem" @submit.prevent="guardarCategoria">
          <div class="grid-2">
            <div class="form-group" style="margin-bottom: 0">
              <input v-model="catForm.nombre" type="text" class="form-control" placeholder="Nombre de categoría *" required />
            </div>
            <div class="form-group" style="margin-bottom: 0; display: flex; gap: 0.45rem">
              <input v-model="catForm.descripcion" type="text" class="form-control" placeholder="Descripción..." />
              <button type="submit" class="btn btn-primary">
                {{ catForm.id ? 'Actualizar' : '+ Crear' }}
              </button>
            </div>
          </div>
        </form>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Productos</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in categorias" :key="c.id">
                <td><strong>{{ c.nombre }}</strong></td>
                <td style="font-size: 0.8rem; color: var(--text-muted)">{{ c.descripcion || '—' }}</td>
                <td class="mono">{{ c.total_productos }}</td>
                <td style="display: flex; gap: 0.35rem">
                  <button class="btn btn-outline btn-sm" @click="editarCategoria(c)">✎</button>
                  <button
                    v-if="c.total_productos === 0"
                    class="btn btn-outline btn-sm"
                    @click="eliminarCategoria(c.id)"
                  >
                    🗑️
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Catálogo Unidades de Medida -->
      <div class="card">
        <h3 style="margin-bottom: 0.85rem">📏 Unidades de Medida (Fraccionamiento)</h3>
        <form style="margin-bottom: 1rem" @submit.prevent="guardarUnidad">
          <div class="grid-3">
            <input v-model="uniForm.codigo" type="text" class="form-control mono" placeholder="Código (ej. GAL)" required />
            <input v-model="uniForm.nombre" type="text" class="form-control" placeholder="Nombre (ej. Galón)" required />
            <div style="display: flex; align-items: center; gap: 0.5rem">
              <label style="font-size: 0.78rem">
                <input v-model="uniForm.permite_decimales" type="checkbox" /> Decimales
              </label>
              <button type="submit" class="btn btn-dark btn-sm">+ Añadir</button>
            </div>
          </div>
        </form>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Unidad</th>
                <th>¿Permite Decimales?</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in unidades" :key="u.id">
                <td class="mono"><strong>{{ u.codigo }}</strong></td>
                <td>{{ u.nombre }}</td>
                <td>
                  <span :class="['badge', u.permite_decimales ? 'badge-amber' : 'badge-ves']">
                    {{ u.permite_decimales ? 'SÍ (Fraccionable 0.001)' : 'NO (Solo Enteros)' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
