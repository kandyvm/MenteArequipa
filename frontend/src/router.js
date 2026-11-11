import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from './stores/auth'

const rutas = [
  { path: '/', redirect: '/consulta' },
  { path: '/login', component: () => import('./views/LoginView.vue'), meta: { invitado: true, titulo: 'Iniciar sesión' } },
  { path: '/registro', component: () => import('./views/RegistroView.vue'), meta: { invitado: true, titulo: 'Crear cuenta' } },
  { path: '/consulta/:codigo?', component: () => import('./views/ConsultaPublicaView.vue'), meta: { titulo: 'Consulta pública' } },
  { path: '/solicitudes/nueva', component: () => import('./views/NuevaSolicitudView.vue'), meta: { roles: ['JOVEN'], titulo: 'Nueva solicitud' } },
  { path: '/panel', component: () => import('./views/DashboardView.vue'), meta: { roles: ['JOVEN', 'ORIENTADOR', 'CENTRO', 'ADMIN'], titulo: 'Panel' } },
  { path: '/derivar/:id', component: () => import('./views/DerivacionView.vue'), meta: { roles: ['ORIENTADOR'], titulo: 'Derivación asistida' } },
  { path: '/centros', component: () => import('./views/CentrosView.vue'), meta: { roles: ['ADMIN', 'CENTRO'], titulo: 'Centros' } },
  { path: '/auditoria', component: () => import('./views/AuditoriaView.vue'), meta: { roles: ['ADMIN'], titulo: 'Auditoría' } },
  { path: '/:pathMatch(.*)*', redirect: '/consulta' },
]

const router = createRouter({ history: createWebHistory(), routes: rutas })

router.beforeEach((to) => {
  const auth = useAuth()
  if (to.meta.invitado && auth.logueado) return auth.inicio
  if (to.meta.roles) {
    if (!auth.logueado) return { path: '/login', query: { redirect: to.fullPath } }
    if (!to.meta.roles.includes(auth.rol)) return auth.inicio
  }
})
router.afterEach((to) => { document.title = `${to.meta.titulo ?? 'MENTE'} · MENTE Arequipa` })
export default router
