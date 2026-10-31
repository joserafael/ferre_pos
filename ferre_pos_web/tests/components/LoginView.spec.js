import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import { useAuthStore } from '@/stores/authStore'

describe('LoginView Component', () => {
  let router

  beforeEach(() => {
    setActivePinia(createPinia())
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div>Home</div>' } },
        { path: '/pos', name: 'pos', component: { template: '<div>POS</div>' } }
      ]
    })
  })

  it('renderiza el formulario con campos de usuario y contraseña', () => {
    const wrapper = mount(LoginView, {
      global: { plugins: [router] }
    })

    expect(wrapper.find('input[type="text"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('FERREPOS VENEZUELA')
  })

  it('los botones de demo rellenan automáticamente las credenciales', async () => {
    const wrapper = mount(LoginView, {
      global: { plugins: [router] }
    })

    const buttons = wrapper.findAll('.demo-credentials button')
    expect(buttons).toHaveLength(2)

    // Clic en Cajero
    await buttons[1].trigger('click')
    expect(wrapper.find('input[type="text"]').element.value).toBe('cajero')
    expect(wrapper.find('input[type="password"]').element.value).toBe('cajero123')
  })

  it('llama a authStore.login con las credenciales ingresadas al enviar el formulario', async () => {
    const authStore = useAuthStore()
    const loginSpy = vi.spyOn(authStore, 'login').mockResolvedValue({
      token: 'mock.token',
      usuario: { rol: 'ADMIN' }
    })

    const wrapper = mount(LoginView, {
      global: { plugins: [router] }
    })

    await wrapper.find('input[type="text"]').setValue('admin')
    await wrapper.find('input[type="password"]').setValue('admin123')
    await wrapper.find('form').trigger('submit.prevent')

    expect(loginSpy).toHaveBeenCalledWith('admin', 'admin123')
  })

  it('muestra mensaje de error cuando la autenticación falla', async () => {
    const authStore = useAuthStore()
    vi.spyOn(authStore, 'login').mockRejectedValue(new Error('Credenciales inválidas'))

    const wrapper = mount(LoginView, {
      global: { plugins: [router] }
    })

    await wrapper.find('form').trigger('submit.prevent')
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.badge-danger').exists()).toBe(true)
    expect(wrapper.text()).toContain('Credenciales inválidas')
  })
})
