<!-- Recursos de orientación. ADMIN: alta, edición, publicar/ocultar y eliminar. Otros roles: lista de recursos publicados. -->
<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { api, erroresDe, mensajeDe } from '../api'
import { useAuth } from '../stores/auth'
const auth = useAuth()
const esAdmin = computed(() => auth.rol === 'ADMIN')
const recursos = ref([]), cargando = ref(true), error = ref(''), aviso = ref('')
const editando = ref(null), errores = ref({}), guardando = ref(false)
const TIPOS = [['GUIA', 'Guía'], ['ENLACE', 'Enlace'], ['PDF', 'PDF']]
const etiquetaTipo = (t) => TIPOS.find(([v]) => v === t)?.[1] ?? t
const vacio = () => ({ titulo: '', tipo: 'GUIA', url: '', publicado: false })
const f = reactive(vacio())
// Solo se enlazan direcciones http/https (el servidor ya lo valida; esto es una segunda barrera).
const seguro = (u) => (/^https?:\/\//i.test(u) ? u : '#')

async function cargar() {
  cargando.value = true; error.value = ''
  try { recursos.value = (await api.get('/api/recursos')).data.data }
  catch (e) { error.value = mensajeDe(e) } finally { cargando.value = false }
}
function nuevo() { Object.assign(f, vacio()); editando.value = 'nuevo'; errores.value = {}; aviso.value = '' }
function editar(r) { Object.assign(f, vacio(), { titulo: r.titulo, tipo: r.tipo, url: r.url, publicado: !!r.publicado }); editando.value = r.id; errores.value = {}; aviso.value = ''; window.scrollTo({ top: 0, behavior: 'smooth' }) }
async function guardar() {
  guardando.value = true; errores.value = {}; error.value = ''; aviso.value = ''
  const d = { titulo: f.titulo, tipo: f.tipo, url: f.url, publicado: f.publicado }
  try {
    if (editando.value === 'nuevo') await api.post('/api/recursos', d)
    else await api.patch(`/api/recursos/${editando.value}`, d)
    aviso.value = 'Recurso guardado.'; editando.value = null; await cargar()
  } catch (e) { errores.value = erroresDe(e); error.value = mensajeDe(e) } finally { guardando.value = false }
}
async function alternarPublicado(r) {
  error.value = ''; aviso.value = ''
  try { await api.patch(`/api/recursos/${r.id}`, { publicado: !r.publicado }); aviso.value = r.publicado ? 'Recurso oculto.' : 'Recurso publicado.'; await cargar() }
  catch (e) { error.value = mensajeDe(e) }
}
async function eliminar(r) {
  if (!window.confirm(`¿Eliminar "${r.titulo}"? Esta acción no se puede deshacer.`)) return
  error.value = ''; aviso.value = ''
  try { await api.delete(`/api/recursos/${r.id}`); aviso.value = 'Recurso eliminado.'; await cargar() }
  catch (e) { error.value = mensajeDe(e) }
}
onMounted(cargar)
</script>
<template>
  <section>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h1 class="text-2xl font-semibold">Recursos de orientación</h1>
      <button v-if="esAdmin && !editando" class="btn-primario" @click="nuevo">Agregar recurso</button>
    </div>
    <p v-if="aviso" role="status" class="mb-3 rounded-lg bg-green-50 p-3 text-green-700">{{ aviso }}</p>
    <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>

    <form v-if="esAdmin && editando" class="tarjeta mb-6 grid sm:grid-cols-2 gap-4" @submit.prevent="guardar" novalidate>
      <h2 class="sm:col-span-2 text-lg font-semibold">{{ editando === 'nuevo' ? 'Nuevo recurso' : 'Editar recurso' }}</h2>
      <div class="sm:col-span-2">
        <label class="etiqueta" for="r-titulo">Título</label>
        <input id="r-titulo" v-model="f.titulo" maxlength="150" class="campo" :aria-invalid="!!errores.titulo" :aria-describedby="errores.titulo ? 'r-titulo-err' : undefined" />
        <p v-if="errores.titulo" id="r-titulo-err" class="error-txt">{{ errores.titulo }}</p>
      </div>
      <div>
        <label class="etiqueta" for="r-tipo">Tipo</label>
        <select id="r-tipo" v-model="f.tipo" class="campo"><option v-for="[v, t] in TIPOS" :key="v" :value="v">{{ t }}</option></select>
        <p v-if="errores.tipo" class="error-txt">{{ errores.tipo }}</p>
      </div>
      <div>
        <label class="etiqueta" for="r-url">Enlace (http o https)</label>
        <input id="r-url" v-model="f.url" type="url" maxlength="255" placeholder="https://" class="campo" :aria-invalid="!!errores.url" :aria-describedby="errores.url ? 'r-url-err' : undefined" />
        <p v-if="errores.url" id="r-url-err" class="error-txt">{{ errores.url }}</p>
      </div>
      <label class="sm:col-span-2 flex items-center gap-3 min-h-[44px]">
        <input v-model="f.publicado" type="checkbox" class="h-5 w-5" />
        <span>Publicado (visible para todos los usuarios con sesión)</span>
      </label>
      <div class="sm:col-span-2 flex flex-wrap gap-3">
        <button class="btn-primario" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar' }}</button>
        <button type="button" class="btn-secundario" @click="editando = null">Cancelar</button>
      </div>
    </form>

    <p v-if="cargando" role="status">Cargando…</p>

    <div v-else-if="esAdmin" class="overflow-x-auto tarjeta p-0">
      <table class="block sm:table w-full text-left text-sm">
        <caption class="sr-only">Recursos de orientación</caption>
        <thead class="hidden sm:table-header-group bg-lav"><tr><th scope="col" class="p-3">Título</th><th scope="col" class="p-3">Tipo</th><th scope="col" class="p-3">Estado</th><th scope="col" class="p-3">Acciones</th></tr></thead>
        <tbody class="block sm:table-row-group">
          <tr v-for="r in recursos" :key="r.id" class="block sm:table-row border-t border-violet-100">
            <td class="block sm:table-cell p-3 font-medium">{{ r.titulo }}<a :href="seguro(r.url)" target="_blank" rel="noopener noreferrer" class="block text-brand underline font-normal break-all">{{ r.url }}</a></td>
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Tipo:</span>{{ etiquetaTipo(r.tipo) }}</td>
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Estado:</span><span class="rounded-full px-2.5 py-0.5 font-semibold" :class="r.publicado ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700'">{{ r.publicado ? 'Publicado' : 'Oculto' }}</span></td>
            <td class="block sm:table-cell p-3"><div class="flex flex-wrap gap-2">
              <button class="btn-secundario" @click="editar(r)">Editar</button>
              <button class="btn-secundario" @click="alternarPublicado(r)">{{ r.publicado ? 'Ocultar' : 'Publicar' }}</button>
              <button class="btn-secundario" @click="eliminar(r)">Eliminar</button>
            </div></td>
          </tr>
          <tr v-if="!recursos.length" class="block sm:table-row"><td colspan="4" class="block sm:table-cell p-3">Todavía no hay recursos.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-else>
      <p v-if="!recursos.length" class="tarjeta">Por ahora no hay recursos publicados.</p>
      <ul v-else class="grid sm:grid-cols-2 gap-4">
        <li v-for="r in recursos" :key="r.id" class="tarjeta">
          <p class="text-sm text-muted">{{ etiquetaTipo(r.tipo) }}</p>
          <h2 class="font-semibold mb-3">{{ r.titulo }}</h2>
          <a :href="seguro(r.url)" target="_blank" rel="noopener noreferrer" class="btn-secundario">Abrir recurso</a>
        </li>
      </ul>
    </div>
  </section>
</template>
