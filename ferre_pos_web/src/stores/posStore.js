import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useTasaStore } from './tasaStore'

export const usePosStore = defineStore('pos', () => {
  const tasaStore = useTasaStore()

  const items = ref([])
  const clienteId = ref(null)
  const clienteDocumento = ref('')
  const clienteNombre = ref('')
  const clienteTelefono = ref('')
  const clienteEmail = ref('')
  const clienteDireccion = ref('')
  const clienteEstadoBusqueda = ref('') // '' | 'encontrado' | 'nuevo'
  const descuentoUsd = ref(0)

  function setCliente(cliente) {
    if (!cliente) return
    clienteId.value = cliente.id || null
    clienteDocumento.value = cliente.documento || ''
    clienteNombre.value = cliente.nombre || ''
    clienteTelefono.value = cliente.telefono || ''
    clienteEmail.value = cliente.email || ''
    clienteDireccion.value = cliente.direccion || ''
    clienteEstadoBusqueda.value = 'encontrado'
  }

  function limpiarCliente() {
    clienteId.value = null
    clienteDocumento.value = ''
    clienteNombre.value = ''
    clienteTelefono.value = ''
    clienteEmail.value = ''
    clienteDireccion.value = ''
    clienteEstadoBusqueda.value = ''
  }

  function agregarProducto(producto, cantidadSolicitada = 1) {
    const permiteDec = Number(producto.permite_decimales) === 1
    let cant = permiteDec
      ? Number(Number(cantidadSolicitada).toFixed(3))
      : Math.max(1, Math.round(Number(cantidadSolicitada)))

    const index = items.value.findIndex((i) => i.producto.id === producto.id)
    if (index >= 0) {
      const nuevaCant = permiteDec
        ? Number((items.value[index].cantidad + cant).toFixed(3))
        : Math.round(items.value[index].cantidad + cant)

      if (nuevaCant > Number(producto.stock_actual)) {
        throw new Error(
          `Stock insuficiente para "${producto.nombre}". Disponible: ${producto.stock_actual} ${producto.unidad_venta_codigo}`
        )
      }
      items.value[index].cantidad = nuevaCant
    } else {
      if (cant > Number(producto.stock_actual)) {
        throw new Error(
          `Stock insuficiente para "${producto.nombre}". Disponible: ${producto.stock_actual} ${producto.unidad_venta_codigo}`
        )
      }
      items.value.push({
        producto,
        cantidad: cant
      })
    }
  }

  function actualizarCantidad(productoId, nuevaCantidad) {
    const item = items.value.find((i) => i.producto.id === productoId)
    if (!item) return

    const permiteDec = Number(item.producto.permite_decimales) === 1
    let cant = Number(nuevaCantidad)
    if (isNaN(cant) || cant <= 0) {
      eliminarItem(productoId)
      return
    }

    if (!permiteDec && cant % 1 !== 0) {
      cant = Math.floor(cant)
    } else {
      cant = Number(cant.toFixed(3))
    }

    if (cant > Number(item.producto.stock_actual)) {
      cant = Number(item.producto.stock_actual)
    }

    item.cantidad = cant
  }

  function eliminarItem(productoId) {
    items.value = items.value.filter((i) => i.producto.id !== productoId)
  }

  function limpiarCarrito() {
    items.value = []
    limpiarCliente()
    descuentoUsd.value = 0
  }

  const subtotalUsd = computed(() => {
    const sum = items.value.reduce((acc, item) => {
      return acc + Number((item.cantidad * Number(item.producto.precio_venta_usd)).toFixed(2))
    }, 0)
    return Number(sum.toFixed(2))
  })

  const impuestoUsd = computed(() => {
    const sum = items.value.reduce((acc, item) => {
      if (Number(item.producto.exento_iva) === 1) return acc
      const sub = Number((item.cantidad * Number(item.producto.precio_venta_usd)).toFixed(2))
      const iva = Number((sub * (Number(item.producto.porcentaje_iva || 16) / 100)).toFixed(2))
      return acc + iva
    }, 0)
    return Number(sum.toFixed(2))
  })

  const totalUsd = computed(() => {
    const tot = subtotalUsd.value + impuestoUsd.value - Number(descuentoUsd.value || 0)
    return Math.max(0, Number(tot.toFixed(2)))
  })

  const subtotalVes = computed(() => tasaStore.aBolivares(subtotalUsd.value))
  const impuestoVes = computed(() => tasaStore.aBolivares(impuestoUsd.value))
  const descuentoVes = computed(() => tasaStore.aBolivares(descuentoUsd.value))
  const totalVes = computed(() => tasaStore.aBolivares(totalUsd.value))

  return {
    items,
    clienteId,
    clienteDocumento,
    clienteNombre,
    clienteTelefono,
    clienteEmail,
    clienteDireccion,
    clienteEstadoBusqueda,
    descuentoUsd,
    subtotalUsd,
    impuestoUsd,
    totalUsd,
    subtotalVes,
    impuestoVes,
    descuentoVes,
    totalVes,
    setCliente,
    limpiarCliente,
    agregarProducto,
    actualizarCantidad,
    eliminarItem,
    limpiarCarrito
  }
})
