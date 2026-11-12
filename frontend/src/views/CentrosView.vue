<!-- CRUD de centros. ADMIN: alta, edición, desactivar y reactivar. CENTRO: edita solo su propio centro. -->
<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { api, erroresDe, mensajeDe } from '../api'
import { useAuth } from '../stores/auth'
import { DISTRITOS } from '../constantes'
const auth = useAuth()
const esAdmin = computed(() => auth.rol === 'ADMIN')
const centros = ref([]), propio = ref(null), cargando = ref(true), error = ref(''), aviso = ref('')
const editando = ref(null), errores = ref({}), guardando = ref(false)
const vacio = () => ({ nombre: '', tipo: 'MINSA', distrito: '', direccion: '', servicios: '', latitud: '', longitud: '' })
const f = reactive(vacio())

async function cargar() {
  cargando.value = true; error.value = ''
  try {
    if (!esAdmin.value) propio.value = (await api.get('/api/yo')).data.centro_id
    centros.value = (await api.get('/api/centros')).data.data
  } catch (e) { error.value = mensajeDe(e) } finally { cargando.value = false }
}
const puedeEditar = (c) => esAdmin.value || c.id === propio.value
function nuevo() { Object.assign(f, vacio()); editando.value = 'nuevo'; errores.value = {} }
function editar(c) { Object.assign(f, vacio(), c, { servicios: c.servicios ?? '', latitud: c.latitud ?? '', longitud: c.longitud ?? '' }); editando.value = c.id; errores.value = {}; window.scrollTo({ top: 0, behavior: 'smooth' }) }
async function guardar() {
  guardando.value = true; errores.value = {}; error.value = ''
  const d = { ...f, latitud: f.latitud === '' ? null : f.latitud, longitud: f.longitud === '' ? null : f.longitud, servicios: f.servicios || null }
  if (!esAdmin.value) { delete d.tipo }
  delete d.id; delete d.activo
  try {
    if (editando.value === 'nuevo') await api.post('/api/centros', d)
    else await api.patch(`/api/centros/${editando.value}`, d)
    aviso.value = 'Centro guardado.'; editando.value = null; await cargar()
  } catch (e) { errores.value = erroresDe(e); error.value = mensajeDe(e) } finally { guardando.value = false }
}
async function cambiarActivo(c) {
  error.value = ''; aviso.value = ''
  try {
    if (c.activo) await api.delete(`/api/centros/${c.id}`); else await api.patch(`/api/centros/${c.id}`, { activo: true })
    aviso.value = c.activo ? 'Centro desactivado.' : 'Centro reactivado.'; await cargar()
  } catch (e) { error.value = mensajeDe(e) }
}
onMounted(cargar)
</script>
<template>
  <section>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h1 class="text-2xl font-semibold">Centros de atención</h1>
      <button v-if="esAdmin && !editando" class="btn-primario" @click="nuevo">Agregar centro</button>
    </div>
    <p v-if="aviso" role="status" class="mb-3 rounded-lg bg-green-50 p-3 text-green-700">{{ aviso }}</p>
    <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>

    <form v-if="editando" class="tarjeta mb-6 grid sm:grid-cols-2 gap-4" @submit.prevent="guardar" novalidate>
      <h2 class="sm:col-span-2 text-lg font-semibold">{{ editando === 'nuevo' ? 'Nuevo centro' : 'Editar centro' }}</h2>
      <div v-for="c in [{ id: 'nombre', t: 'Nombre' }, { id: 'direccion', t: 'Dirección' }]" :key="c.id">
        <label class="etiqueta" :for="c.id">{{ c.t }}</label>
        <input :id="c.id" v-model="f[c.id]" class="campo" :aria-invalid="!!errores[c.id]" :aria-describedby="errores[c.id] ? c.id + '-err' : undefined" />
        <p v-if="errores[c.id]" :id="c.id + '-err'" class="error-txt">{{ errores[c.id] }}</p>
      </div>
      <div v-if="esAdmin">
        <label class="etiqueta" for="tipo">Tipo</label>
        <select id="tipo" v-model="f.tipo" class="campo"><option v-for="t in ['MINSA','ESSALUD','ONG','PRIVADO']" :key="t">{{ t }}</option></select>
      </div>
      <div>
        <label class="etiqueta" for="distrito">Distrito</label>
        <select id="distrito" v-model="f.distrito" class="campo" :aria-invalid="!!errores.distrito"><option value="" disabled>Elige…</option><option v-for="d in DISTRITOS" :key="d">{{ d }}</option></select>
        <p v-if="errores.distrito" class="error-txt">{{ errores.distrito }}</p>
      </div>
      <div><label class="etiqueta" for="lat">Latitud (opcional)</label><input id="lat" v-model="f.latitud" type="number" step="any" class="campo" /></div>
      <div><label class="etiqueta" for="lng">Longitud (opcional)</label><input id="lng" v-model="f.longitud" type="number" step="any" class="campo" /></div>
      <div class="sm:col-span-2">
        <label class="etiqueta" for="servicios">Servicios y turnos (opcional)</label>
        <textarea id="servicios" v-model="f.servicios" rows="3" maxlength="1000" class="campo"></textarea>
      </div>
      <div class="sm:col-span-2 flex flex-wrap gap-3">
        <button class="btn-primario" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar' }}</button>
        <button type="button" class="btn-secundario" @click="editando = null">Cancelar</button>
      </div>
    </form>

    <p v-if="cargando" role="status">Cargando…</p>
    <div v-else class="overflow-x-auto tarjeta p-0">
      <table class="block sm:table w-full text-left text-sm">
        <caption class="sr-only">Directorio de centros</caption>
        <thead class="hidden sm:table-header-group bg-lav"><tr><th scope="col" class="p-3">Nombre</th><th scope="col" class="p-3">Tipo</th><th scope="col" class="p-3">Distrito</th><th scope="col" class="p-3">Estado</th><th scope="col" class="p-3">Acciones</th></tr></thead>
        <tbody class="block sm:table-row-group">
          <tr v-for="c in centros" :key="c.id" class="block sm:table-row border-t border-violet-100">
            <td class="block sm:table-cell p-3 font-medium">{{ c.nombre }}<span class="block text-muted font-normal">{{ c.direccion }}</span></td>
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Tipo:</span>{{ c.tipo }}</td><td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Distrito:</span>{{ c.distrito }}</td>
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Estado:</span><span class="rounded-full px-2.5 py-0.5 font-semibold" :class="c.activo ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700'">{{ c.activo ? 'Activo' : 'Desactivado' }}</span></td>
            <td class="block sm:table-cell p-3"><div class="flex flex-wrap gap-2">
              <button v-if="puedeEditar(c)" class="btn-secundario" @click="editar(c)">Editar</button>
              <button v-if="esAdmin" class="btn-secundario" @click="cambiarActivo(c)">{{ c.activo ? 'Desactivar' : 'Reactivar' }}</button>
            </div></td>
          </tr>
          <tr v-if="!centros.length" class="block sm:table-row"><td colspan="5" class="block sm:table-cell p-3">No hay centros registrados.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
