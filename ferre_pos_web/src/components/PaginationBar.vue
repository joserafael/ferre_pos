<script setup>
import { computed } from 'vue'

const props = defineProps({
  pagination: {
    type: Object,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  label: {
    type: String,
    default: 'registros'
  }
})

const emit = defineEmits(['page-change', 'per-page-change'])

const paginasVisibles = computed(() => {
  const total = props.pagination.last_page || 1
  const actual = props.pagination.current_page || 1
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }
  const paginas = [1]
  if (actual > 3) paginas.push('...')
  const inicio = Math.max(2, actual - 1)
  const fin = Math.min(total - 1, actual + 1)
  for (let i = inicio; i <= fin; i++) {
    paginas.push(i)
  }
  if (actual < total - 2) paginas.push('...')
  paginas.push(total)
  return paginas
})

function irPagina(pag) {
  if (pag < 1 || pag > props.pagination.last_page || pag === props.pagination.current_page || props.loading) {
    return
  }
  emit('page-change', pag)
}
</script>

<template>
  <div
    v-if="pagination.total > 0"
    style="
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-top: 1rem;
      padding-top: 0.9rem;
      border-top: 1px solid var(--border-color, #e5e7eb);
    "
  >
    <div style="font-size: 0.85rem; color: var(--text-muted)">
      Mostrando <strong>{{ pagination.from }}</strong>–<strong>{{ pagination.to }}</strong> de
      <strong>{{ pagination.total }}</strong> {{ label }}
    </div>

    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center">
      <select
        :value="pagination.per_page"
        class="form-control"
        style="width: 115px; padding: 0.28rem 0.5rem; font-size: 0.8rem; margin-right: 0.35rem"
        :disabled="loading"
        @change="emit('per-page-change', Number($event.target.value))"
      >
        <option :value="10">10 / pág.</option>
        <option :value="25">25 / pág.</option>
        <option :value="50">50 / pág.</option>
        <option :value="100">100 / pág.</option>
      </select>

      <button
        type="button"
        class="btn btn-outline btn-sm"
        :disabled="pagination.current_page <= 1 || loading"
        @click="irPagina(1)"
        title="Primera página"
      >
        «
      </button>
      <button
        type="button"
        class="btn btn-outline btn-sm"
        :disabled="pagination.current_page <= 1 || loading"
        @click="irPagina(pagination.current_page - 1)"
      >
        ‹ Anterior
      </button>

      <template v-for="(pag, idx) in paginasVisibles" :key="idx">
        <span
          v-if="pag === '...'"
          style="padding: 0 0.35rem; color: var(--text-muted); font-size: 0.85rem"
        >
          ...
        </span>
        <button
          v-else
          type="button"
          :class="['btn btn-sm', pag === pagination.current_page ? 'btn-primary' : 'btn-outline']"
          :disabled="loading"
          @click="irPagina(pag)"
        >
          {{ pag }}
        </button>
      </template>

      <button
        type="button"
        class="btn btn-outline btn-sm"
        :disabled="pagination.current_page >= pagination.last_page || loading"
        @click="irPagina(pagination.current_page + 1)"
      >
        Siguiente ›
      </button>
      <button
        type="button"
        class="btn btn-outline btn-sm"
        :disabled="pagination.current_page >= pagination.last_page || loading"
        @click="irPagina(pagination.last_page)"
        title="Última página"
      >
        »
      </button>
    </div>
  </div>
</template>
