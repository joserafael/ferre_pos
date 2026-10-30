import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('ferrepos_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    const status = error.response?.status
    if (status === 401 && !error.config?.url?.includes('/auth/login')) {
      localStorage.removeItem('ferrepos_token')
      localStorage.removeItem('ferrepos_user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    const msg =
      error.response?.data?.messages ||
      error.message ||
      'Error de comunicación con el servidor.'
    return Promise.reject(new Error(msg))
  }
)

export default api
