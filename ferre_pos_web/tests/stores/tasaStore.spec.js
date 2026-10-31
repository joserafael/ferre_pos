import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useTasaStore } from '@/stores/tasaStore'

describe('tasaStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('inicia con la tasa oficial por defecto de 42.50', () => {
    const tasaStore = useTasaStore()
    expect(tasaStore.tasaVes).toBe(42.5)
    expect(tasaStore.tasaActiva.fuente).toBe('BCV Oficial')
  })

  it('permite actualizar la tasa activa en memoria', () => {
    const tasaStore = useTasaStore()
    tasaStore.setTasaActiva({
      id: 2,
      tasa_ves: 45.8,
      fuente: 'BCV Oficial',
      created_at: '2026-10-31 08:00:00'
    })
    expect(tasaStore.tasaVes).toBe(45.8)
    expect(tasaStore.tasaActiva.id).toBe(2)
  })

  it('calcula la conversión exacta de USD a Bolívares (VES)', () => {
    const tasaStore = useTasaStore()
    tasaStore.setTasaActiva({ tasa_ves: 40.0 })

    // 10 USD a tasa 40 = 400.00 VES
    expect(tasaStore.aBolivares(10)).toBe(400.0)
    // 12.34 USD a tasa 40 = 493.60 VES
    expect(tasaStore.aBolivares(12.34)).toBe(493.6)
    // 0 USD = 0 VES
    expect(tasaStore.aBolivares(0)).toBe(0)
  })

  it('calcula la conversión exacta de Bolívares (VES) a USD', () => {
    const tasaStore = useTasaStore()
    tasaStore.setTasaActiva({ tasa_ves: 50.0 })

    // 100 VES a tasa 50 = 2.00 USD
    expect(tasaStore.aDolares(100)).toBe(2.0)

    // setTasaActiva ignora tasas <= 0 para proteger contra división por cero
    tasaStore.setTasaActiva({ tasa_ves: 0 })
    expect(tasaStore.tasaVes).toBe(50.0)
    expect(tasaStore.aDolares(100)).toBe(2.0)
  })

  it('formatea montos en USD y VES correctamente', () => {
    const tasaStore = useTasaStore()
    expect(tasaStore.formatUsd(1234.5)).toContain('1,234.50')
    expect(tasaStore.formatUsd(1234.5)).toContain('$')

    const vesFormatted = tasaStore.formatVes(1234.5)
    expect(vesFormatted).toContain('Bs.')
  })
})
