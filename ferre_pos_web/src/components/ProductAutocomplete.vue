<script setup>
import { ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: ''
  },
  placeholder: {
    type: String,
    default: '🔍 Buscar producto por SKU, barra o nombre...'
  },
  required: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'select'])

const texto = ref('')
const abierto = ref(false)
const buscando = ref(false)
const resultados = ref([])
const indice = ref(0)
let timer = null

watch(
  () => props.modelValue,
  (val) => {
    if (!val) {
      texto.value = ''
    }
  }
)

async function consultar(q) {
  buscando.value = true
  try {
    const res = await api.get(`/productos?page=1&per_page=15&q=${encodeURIComponent(q)}`)
    resultados.value = res.data?.items || []
    indice.value = 0
  } catch {
    resultados.value = []
  } finally {
    buscando.value = false
  }
}

function alEscribir() {
  clearTimeout(timer)
  const q = texto.value.trim()
  if (props.modelValue) {
    emit('update:modelValue', '')
  }
  if (!q) {
    resultados.value = []
    abierto.value = false
    return
  }
  abierto.value = true
  timer = setTimeout(() => consultar(q), 180)
}

function seleccionar(prod) {
  texto.value = `${prod.sku} - ${prod.nombre}`
  abierto.value = false
  emit('update:modelValue', prod.id)
  emit('select', prod)
}

function limpiar() {
  clearTimeout(timer)
  texto.value = ''
  resultados.value = []
  abierto.value = false
  emit('update:modelValue', '')
  emit('select', null)
}

function manejarTecla(e) {
  if (!abierto.value || !resultados.value.length) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    indice.value = (indice.value + 1) % resultados.value.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    indice.value = (indice.value - 1 + resultados.value.length) % resultados.value.length
  } else if (e.key === 'Enter') {
    e.preventDefault()
    seleccionar(resultados.value[indice.value])
  } else if (e.key === 'Escape') {
    abierto.value = false
  }
}

function alPerderFoco() {
  setTimeout(() => {
    abierto.value = false
  }, 180)
}
</script>

<template>
  <div class="prod-ac-wrap">
    <div style="position: relative; display: flex; align-items: center">
      <input
        v-model="texto"
        type="text"
        class="form-control"
        style="padding-right: 2rem"
        :placeholder="placeholder"
        :required="required && !modelValue"
        @input="alEscribir"
        @focus="texto.trim() && resultados.length && (abierto = true)"
        @blur="alPerderFoco"
        @keydown="manejarTecla"
      />
      <button
        v-if="texto || modelValue"
        type="button"
        class="btn btn-outline btn-sm"
        style="position: absolute; right: 0.3rem; padding: 0.1rem 0.4rem; font-size: 0.72rem"
        @click="limpiar"
      >
        ✕
      </button>
    </div>

    <div v-if="abierto" class="prod-ac-dropdown">
      <div v-if="buscando" class="prod-ac-msg">Buscando...</div>
      <div v-else-if="!resultados.length" class="prod-ac-msg">Sin coincidencias</div>
      <button
        v-else
        v-for="(p, idx) in resultados"
        :key="p.id"
        type="button"
        class="prod-ac-option"
        :class="{ active: idx === indice }"
        @mousedown.prevent="seleccionar(p)"
      >
        <div style="font-weight: 700; font-size: 0.82rem; text-align: left">
          <span class="mono" style="color: var(--text-muted)">{{ p.sku }}</span> · {{ p.nombre }}
        </div>
        <div class="mono" style="font-size: 0.72rem; color: var(--text-muted); text-align: left">
          Stock: {{ Number(p.stock_actual).toFixed(p.permite_decimales ? 3 : 0) }} {{ p.unidad_venta_codigo }}
          · Compra: {{ p.unidad_compra_codigo }} (x{{ p.factor_conversion }})
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.prod-ac-wrap {
  position: relative;
}

.prod-ac-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 60;
  background: #ffffff;
  border: 1px solid var(--border-color, #cbd5e1);
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.14);
  max-height: 240px;
  overflow-y: auto;
}

.prod-ac-msg {
  padding: 0.65rem 0.8rem;
  font-size: 0.8rem;
  color: var(--text-muted);
}

.prod-ac-option {
  display: block;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: none;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  background: #ffffff;
  cursor: pointer;
}

.prod-ac-option:last-child {
  border-bottom: none;
}

.prod-ac-option:hover,
.prod-ac-option.active {
  background: #f8fafc;
}
</style>
