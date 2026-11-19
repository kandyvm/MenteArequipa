import { createApp } from 'vue'
import { createPinia } from 'pinia'
import '@fontsource/inter/400.css'
import '@fontsource/inter/600.css'
import '@fontsource/jetbrains-mono/500.css'
import './style.css'
import App from './App.vue'
import router from './router'
import { api } from './api'
import { useAuth } from './stores/auth'

const pinia = createPinia()
// Si la sesión expira (401) o caduca el token CSRF (419), volver al login sin dejar datos viejos en pantalla.
api.interceptors.response.use(null, (e) => {
  if ([401, 419].includes(e.response?.status) && useAuth(pinia).logueado) {
    useAuth(pinia).guardar(null)
    router.push('/login')
  }
  // Cuenta o centro desactivado: el servidor ya cerró la sesión; se vuelve al login mostrando el motivo
  if (e.response?.status === 403 && e.response.data?.cuenta_desactivada && useAuth(pinia).logueado) {
    useAuth(pinia).guardar(null)
    // Solo se pasa una clave fija; el texto lo elige LoginView (nadie puede inyectar mensajes por la URL)
    router.push({ path: '/login', query: { motivo: /centro/i.test(e.response.data.message ?? '') ? 'centro' : 'cuenta' } })
  }
  return Promise.reject(e)
})
createApp(App).use(pinia).use(router).mount('#app')
