import { defineStore } from 'pinia'
import { api } from '../api'

/** Sesión: la cookie la maneja el navegador; aquí solo guardamos id y rol para la interfaz. El servidor siempre autoriza con policies. */
export const useAuth = defineStore('auth', {
  state: () => ({ user: JSON.parse(sessionStorage.getItem('user') || 'null') }),
  getters: {
    rol: (s) => s.user?.rol ?? null,
    logueado: (s) => !!s.user,
    inicio: (s) => (s.user?.rol === 'JOVEN' ? '/solicitudes/nueva' : '/panel'),
  },
  actions: {
    guardar(u) { this.user = u; u ? sessionStorage.setItem('user', JSON.stringify(u)) : sessionStorage.removeItem('user') },
    async login(correo, contrasena) {
      await api.get('/sanctum/csrf-cookie')
      const { data } = await api.post('/api/login', { correo, contrasena })
      this.guardar(data.data ?? data)
    },
    async registrar(datos) {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/api/registro', datos)
      await this.login(datos.correo, datos.contrasena)
    },
    async logout() {
      try { await api.post('/api/logout') } finally { this.guardar(null) }
    },
  },
})
