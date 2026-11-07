<!-- Panel por rol. JOVEN: sus solicitudes. ORIENTADOR: indicadores y derivación. CENTRO: derivaciones recibidas. ADMIN: lectura. -->
<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api, mensajeDe } from '../api'
import { useAuth } from '../stores/auth'
import Insignia from '../components/Insignia.vue'
const auth = useAuth(), router = useRouter()
const resumen = ref(null), lista = ref([]), cargando = ref(true), error = ref(''), aviso = ref('')
const filtro = reactive({ estado: '', nivel_riesgo: '' })
const ESTADOS = ['REGISTRADA', 'EN_REVISION', 'DERIVADA', 'ACEPTADA', 'ATENDIDA', 'CERRADA', 'CANCELADA']

const tarjetas = computed(() => !resumen.value ? [] : [
  ['Solicitudes nuevas', resumen.value.solicitudes_nuevas], ['Derivaciones en curso', resumen.value.derivaciones_en_curso],
  ['Casos atendidos', resumen.value.casos_atendidos], ['Derivadas a la Línea 113', resumen.value.derivadas_113],
])
async function cargar() {
  cargando.value = true; error.value = ''
  const params = Object.fromEntries(Object.entries(filtro).filter(([, v]) => v))
  try {
    const [s, r] = await Promise.all([
      api.get('/api/solicitudes', { params }),
      auth.rol === 'ORIENTADOR' ? api.get('/api/dashboard/resumen') : Promise.resolve(null),
    ])
    lista.value = s.data.data; resumen.value = r?.data.data ?? null
  } catch (e) { error.value = mensajeDe(e) } finally { cargando.value = false }
}
async function accion(fn, ok) {
  aviso.value = ''; error.value = ''
  try { await fn(); aviso.value = ok; await cargar() } catch (e) { error.value = mensajeDe(e) }
}
const cancelar = (s) => accion(() => api.delete(`/api/solicitudes/${s.id}`), 'Solicitud cancelada.')
const estadoDerivacion = (s, estado) => accion(() => api.patch(`/api/derivaciones/${s.derivacion.id}`, { estado }), 'Derivación actualizada.')
async function revisar(s) {
  try { if (s.estado === 'REGISTRADA') await api.patch(`/api/solicitudes/${s.id}`, { estado: 'EN_REVISION' }); router.push(`/derivar/${s.id}`) }
  catch (e) { error.value = mensajeDe(e) }
}
onMounted(cargar)
</script>
<template>
  <section>
    <h1 class="text-2xl font-semibold mb-4">{{ auth.rol === 'JOVEN' ? 'Mis solicitudes' : 'Panel' }}</h1>

    <div v-if="resumen" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
      <div v-for="[t, v] in tarjetas" :key="t" class="tarjeta">
        <p class="text-3xl font-semibold text-brand">{{ v ?? 0 }}</p><p class="text-sm text-muted">{{ t }}</p>
      </div>
    </div>

    <form v-if="auth.rol !== 'JOVEN'" class="flex flex-wrap gap-3 items-end mb-4" @submit.prevent="cargar">
      <div><label class="etiqueta" for="f-estado">Estado</label>
        <select id="f-estado" v-model="filtro.estado" class="campo"><option value="">Todos</option><option v-for="e in ESTADOS" :key="e" :value="e">{{ e.replace('_', ' ').toLowerCase() }}</option></select></div>
      <div><label class="etiqueta" for="f-riesgo">Riesgo</label>
        <select id="f-riesgo" v-model="filtro.nivel_riesgo" class="campo"><option value="">Todos</option><option v-for="r in ['BAJO','MEDIO','ALTO','CRISIS']" :key="r">{{ r }}</option></select></div>
      <button class="btn-secundario">Filtrar</button>
    </form>

    <p v-if="aviso" role="status" class="mb-3 rounded-lg bg-green-50 p-3 text-green-700">{{ aviso }}</p>
    <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>
    <p v-if="cargando" role="status">Cargando…</p>
    <div v-else-if="!lista.length" class="tarjeta">
      <p>No hay solicitudes para mostrar.</p>
      <router-link v-if="auth.rol === 'JOVEN'" to="/solicitudes/nueva" class="btn-primario mt-3">Crear una solicitud</router-link>
    </div>

    <div v-else class="overflow-x-auto tarjeta p-0">
      <table class="w-full text-left text-sm">
        <caption class="sr-only">Solicitudes</caption>
        <thead class="bg-lav"><tr><th scope="col" class="p-3">Código</th><th scope="col" class="p-3">Motivo</th><th scope="col" class="p-3">Riesgo</th><th scope="col" class="p-3">Estado</th><th scope="col" class="p-3">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="s in lista" :key="s.id" class="border-t border-violet-100 align-middle">
            <td class="p-3 font-mono">{{ s.codigo }}</td><td class="p-3">{{ s.motivo }}</td>
            <td class="p-3"><Insignia :valor="s.nivel_riesgo" /></td><td class="p-3"><Insignia :valor="s.estado" /></td>
            <td class="p-3"><div class="flex flex-wrap gap-2">
              <router-link class="btn-secundario" :to="`/consulta/${s.codigo}`">Ver estado</router-link>
              <button v-if="auth.rol === 'JOVEN' && s.estado === 'REGISTRADA'" class="btn-secundario" @click="cancelar(s)">Cancelar</button>
              <button v-if="auth.rol === 'ORIENTADOR' && ['REGISTRADA','EN_REVISION'].includes(s.estado)" class="btn-primario" @click="revisar(s)">Derivar</button>
              <template v-if="auth.rol === 'CENTRO' && s.derivacion">
                <template v-if="s.derivacion.estado === 'PENDIENTE'">
                  <button class="btn-primario" @click="estadoDerivacion(s, 'ACEPTADA')">Aceptar</button>
                  <button class="btn-secundario" @click="estadoDerivacion(s, 'RECHAZADA')">Rechazar</button>
                </template>
                <button v-else-if="s.derivacion.estado === 'ACEPTADA'" class="btn-primario" @click="estadoDerivacion(s, 'ATENDIDA')">Marcar atendida</button>
              </template>
            </div></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
