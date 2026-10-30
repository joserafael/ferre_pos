<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const username = ref('admin')
const password = ref('admin123')
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  errorMsg.value = ''
  loading.value = true
  try {
    await authStore.login(username.value, password.value)
    router.push('/pos')
  } catch (e) {
    errorMsg.value = e.message || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}

function usarDemo(user, pass) {
  username.value = user
  password.value = pass
}
</script>

<template>
  <div class="login-page">
    <div class="login-card card">
      <div class="login-header">
        <div class="logo-badge">🛠️</div>
        <h1>FERRE<span style="color: var(--amber-primary)">POS</span> VENEZUELA</h1>
        <p>Sistema de Punto de Venta, Inventario Fraccionado y Facturación Bimonetaria (USD / Bs.)</p>
      </div>

      <div v-if="errorMsg" class="badge badge-danger" style="width: 100%; padding: 0.65rem; margin-bottom: 1rem">
        {{ errorMsg }}
      </div>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label class="form-label">Usuario o Correo</label>
          <input v-model="username" type="text" class="form-control" required autofocus />
        </div>

        <div class="form-group">
          <label class="form-label">Contraseña</label>
          <input v-model="password" type="password" class="form-control" required />
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem" :disabled="loading">
          {{ loading ? 'Autenticando JWT...' : 'Ingresar al Sistema POS' }}
        </button>
      </form>

      <div class="demo-credentials">
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.4rem">
          Accesos rápidos configurados en Seeder:
        </div>
        <div style="display: flex; gap: 0.5rem">
          <button type="button" class="btn btn-outline btn-sm" style="flex: 1" @click="usarDemo('admin', 'admin123')">
            Admin (admin / admin123)
          </button>
          <button type="button" class="btn btn-outline btn-sm" style="flex: 1" @click="usarDemo('cajero', 'cajero123')">
            Cajero (cajero / cajero123)
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  background: radial-gradient(circle at top right, #1e293b, #0f172a);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.login-card {
  width: 100%;
  max-width: 430px;
  padding: 2rem;
}

.login-header {
  text-align: center;
  margin-bottom: 1.5rem;
}

.logo-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: #0f172a;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  margin-bottom: 0.75rem;
}

.login-header h1 {
  font-size: 1.35rem;
  font-weight: 800;
  margin-bottom: 0.35rem;
}

.login-header p {
  font-size: 0.82rem;
  color: var(--text-muted);
}

.demo-credentials {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px dashed var(--border-color);
}
</style>
