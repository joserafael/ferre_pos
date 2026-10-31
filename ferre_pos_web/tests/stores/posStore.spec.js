import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { usePosStore } from '@/stores/posStore'
import { useTasaStore } from '@/stores/tasaStore'

describe('posStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const tasaStore = useTasaStore()
    tasaStore.setTasaActiva({ tasa_ves: 40.0 }) // Tasa fija para pruebas matemáticas
  })

  const productoUnidad = {
    id: 10,
    nombre: 'Alicate de Presión 10"',
    precio_venta_usd: 5.0,
    porcentaje_iva: 16.0,
    exento_iva: 0,
    permite_decimales: 0,
    stock_actual: 15.0,
    unidad_venta_codigo: 'UND'
  }

  const productoFraccionado = {
    id: 20,
    nombre: 'Cable THW #12',
    precio_venta_usd: 1.0,
    porcentaje_iva: 16.0,
    exento_iva: 0,
    permite_decimales: 1,
    stock_actual: 50.0,
    unidad_venta_codigo: 'M'
  }

  const productoExento = {
    id: 30,
    nombre: 'Arena Cernida',
    precio_venta_usd: 2.0,
    porcentaje_iva: 16.0,
    exento_iva: 1, // Exento de IVA
    permite_decimales: 1,
    stock_actual: 100.0,
    unidad_venta_codigo: 'M3'
  }

  it('inicia con el carrito y totales vacíos', () => {
    const pos = usePosStore()
    expect(pos.items).toHaveLength(0)
    expect(pos.subtotalUsd).toBe(0)
    expect(pos.impuestoUsd).toBe(0)
    expect(pos.totalUsd).toBe(0)
    expect(pos.totalVes).toBe(0)
  })

  it('agrega producto por unidad y redondea cantidades a entero si no permite decimales', () => {
    const pos = usePosStore()
    pos.agregarProducto(productoUnidad, 2.7) // Solicita 2.7 pero la unidad es UND

    expect(pos.items).toHaveLength(1)
    expect(pos.items[0].cantidad).toBe(3) // Redondeado a entero
    expect(pos.subtotalUsd).toBe(15.0) // 3 * 5.00
  })

  it('soporta venta fraccionada con decimales (3 decimales) si permite_decimales = 1', () => {
    const pos = usePosStore()
    pos.agregarProducto(productoFraccionado, 3.456)

    expect(pos.items).toHaveLength(1)
    expect(pos.items[0].cantidad).toBe(3.456)
  })

  it('lanza excepción si se intenta agregar más cantidad que el stock disponible', () => {
    const pos = usePosStore()

    expect(() => {
      pos.agregarProducto(productoUnidad, 20) // Stock es 15
    }).toThrowError(/Stock insuficiente/)
  })

  it('actualiza la cantidad de un ítem y lo elimina si la cantidad es 0 o negativa', () => {
    const pos = usePosStore()
    pos.agregarProducto(productoUnidad, 2)
    expect(pos.items[0].cantidad).toBe(2)

    pos.actualizarCantidad(productoUnidad.id, 5)
    expect(pos.items[0].cantidad).toBe(5)

    // Si se actualiza a 0, se remueve del carrito
    pos.actualizarCantidad(productoUnidad.id, 0)
    expect(pos.items).toHaveLength(0)
  })

  it('calcula subtotal, IVA (16%), exención de IVA y totales bimonetarios reactivos', () => {
    const pos = usePosStore()

    // 2 unidades de Alicate = 10 USD + 1.60 IVA (16%) = 11.60 USD
    pos.agregarProducto(productoUnidad, 2)
    // 5 metros de arena exenta = 10 USD + 0 IVA = 10.00 USD
    pos.agregarProducto(productoExento, 5)

    expect(pos.subtotalUsd).toBe(20.0) // 10 + 10
    expect(pos.impuestoUsd).toBe(1.6) // Solo el alicate paga IVA
    expect(pos.totalUsd).toBe(21.6)

    // Bimonetario a tasa 40.00 VES por USD:
    // 20 USD * 40 = 800 VES
    expect(pos.subtotalVes).toBe(800.0)
    // 1.60 USD * 40 = 64 VES
    expect(pos.impuestoVes).toBe(64.0)
    // 21.60 USD * 40 = 864 VES
    expect(pos.totalVes).toBe(864.0)
  })

  it('aplica descuento en USD y recalcula totales en USD y VES', () => {
    const pos = usePosStore()
    pos.agregarProducto(productoUnidad, 2) // Total sin descuento: 11.60 USD

    pos.descuentoUsd = 1.6

    expect(pos.totalUsd).toBe(10.0) // 11.60 - 1.60 = 10.00 USD
    expect(pos.totalVes).toBe(400.0) // 10.00 USD * 40 VES = 400.00 VES
  })

  it('gestiona la asignación y limpieza del cliente en el POS', () => {
    const pos = usePosStore()
    pos.setCliente({
      id: 5,
      documento: 'V-12345678',
      nombre: 'Juan Pérez',
      telefono: '04121112233'
    })

    expect(pos.clienteId).toBe(5)
    expect(pos.clienteDocumento).toBe('V-12345678')
    expect(pos.clienteNombre).toBe('Juan Pérez')
    expect(pos.clienteEstadoBusqueda).toBe('encontrado')

    pos.limpiarCliente()
    expect(pos.clienteId).toBeNull()
    expect(pos.clienteDocumento).toBe('')
    expect(pos.clienteEstadoBusqueda).toBe('')
  })

  it('limpiarCarrito resetea los ítems, cliente y descuentos', () => {
    const pos = usePosStore()
    pos.agregarProducto(productoUnidad, 1)
    pos.setCliente({ documento: 'V-999' })
    pos.descuentoUsd = 2

    pos.limpiarCarrito()

    expect(pos.items).toHaveLength(0)
    expect(pos.clienteDocumento).toBe('')
    expect(pos.descuentoUsd).toBe(0)
    expect(pos.totalUsd).toBe(0)
  })
})

