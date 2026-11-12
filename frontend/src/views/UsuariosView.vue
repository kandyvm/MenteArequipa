<!-- Gestión de usuarios (solo ADMIN): crear cuentas de ORIENTADOR, CENTRO o ADMIN, editar, desactivar y reactivar. -->
<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { api, erroresDe, mensajeDe } from '../api'
const usuarios = ref([]), centros = ref([]), pagina = ref(1), ultima = ref(1), total = ref(0)
const filtroRol = ref(''), cargando = ref(true), error = ref(''), aviso = ref('')
const editando = ref(null), errores = ref({}), guardando = ref(false)
const ROLES = ['JOVEN', 'ORIENTADOR', 'CENTRO', 'ADMIN']
const ROLES_NUEVOS = ['ORIENTADOR', 'CENTRO', 'ADMIN']
const vacio = () => ({ correo: '', password: '', rol: 'ORIENTADOR', centro_id: '' })
const f = reactive(vacio())

const esNuevo = computed(() => editando.value === 'nuevo')
const editado = computed(() => usuarios.value.find((u) => u.id === editando.value) ?? null)
const pideCentro = computed(() => (esNuevo.value ? f.rol : editado.value?.rol) === 'CENTRO')
const nombreCentro = (id) => centros.value.find((c) => c.id === id)?.nombre ?? '—'

async function cargar() {
  cargando.value = true; error.value = ''
  try {
    const params = { page: pagina.value }
    if (filtroRol.value) params.rol = filtroRol.value
    const [u, c] = await Promise.all([api.get('/api/usuarios', { params }), api.get('/api/centros')])
    usuarios.value = u.data.data; ultima.value = u.data.last_page ?? 1; total.value = u.data.total ?? usuarios.value.length
    centros.value = c.data.data
  } catch (e) { error.value = mensajeDe(e) } finally { cargando.value = false }
}
function filtrar() { pagina.value = 1; cargar() }
function irA(n) { pagina.value = n; cargar() }
function nuevo() { Object.assign(f, vacio()); editando.value = 'nuevo'; errores.value = {}; aviso.value = '' }
function editar(u) { Object.assign(f, { correo: u.correo, password: '', rol: u.rol, centro_id: u.centro_id ?? '' }); editando.value = u.id; errores.value = {}; aviso.value = '' }

async function guardar() {
  guardando.value = true; errores.value = {}; error.value = ''; aviso.value = ''
  try {
    if (esNuevo.value) {
      const d = { correo: f.correo, password: f.password, rol: f.rol }
      if (f.rol === 'CENTRO') d.centro_id = f.centro_id || null
      await api.post('/api/usuarios', d)
      aviso.value = 'Usuario creado.'
    } else {
      const d = { correo: f.correo }
      if (f.password) d.password = f.password
      if (editado.value?.rol === 'CENTRO') d.centro_id = f.centro_id || null
      await api.patch(`/api/usuarios/${editando.value}`, d)
      aviso.value = 'Usuario actualizado.'
    }
    editando.value = null; await cargar()
  } catch (e) { errores.value = erroresDe(e); error.value = mensajeDe(e) } finally { guardando.value = false }
}
async function cambiarActivo(u) {
  error.value = ''; aviso.value = ''
  try {
    if (u.activo) await api.delete(`/api/usuarios/${u.id}`); else await api.patch(`/api/usuarios/${u.id}`, { activo: true })
    aviso.value = u.activo ? 'Usuario desactivado.' : 'Usuario reactivado.'; await cargar()
  } catch (e) { error.value = mensajeDe(e) }
}
onMounted(cargar)
</script>
<template>
  <section>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h1 class="text-2xl font-semibold">Usuarios</h1>
      <button v-if="!editando" class="btn-primario" @click="nuevo">Agregar usuario</button>
    </div>
    <p v-if="aviso" role="status" class="mb-3 rounded-lg bg-green-50 p-3 text-green-700">{{ aviso }}</p>
    <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>

    <form v-if="editando" class="tarjeta mb-6 grid sm:grid-cols-2 gap-4" @submit.prevent="guardar" novalidate>
      <h2 class="sm:col-span-2 text-lg font-semibold">{{ esNuevo ? 'Nuevo usuario' : 'Editar usuario' }}</h2>
      <div>
        <label class="etiqueta" for="u-correo">Correo</label>
        <input id="u-correo" v-model="f.correo" type="email" autocomplete="off" class="campo" :aria-invalid="!!errores.correo" :aria-describedby="errores.correo ? 'u-correo-err' : undefined" />
        <p v-if="errores.correo" id="u-correo-err" class="error-txt">{{ errores.correo }}</p>
      </div>
      <div>
        <label class="etiqueta" for="u-password">{{ esNuevo ? 'Contraseña' : 'Nueva contraseña (opcional)' }}</label>
        <input id="u-password" v-model="f.password" type="password" autocomplete="new-password" minlength="10" class="campo" :aria-invalid="!!errores.password" aria-describedby="u-password-ayuda" />
        <p id="u-password-ayuda" class="text-sm text-muted mt-1">{{ esNuevo ? 'Mínimo 10 caracteres.' : 'Déjala vacía para no cambiarla. Si la escribes, mínimo 10 caracteres.' }}</p>
        <p v-if="errores.password" class="error-txt">{{ errores.password }}</p>
      </div>
      <div v-if="esNuevo">
        <label class="etiqueta" for="u-rol">Rol</label>
        <select id="u-rol" v-model="f.rol" class="campo"><option v-for="r in ROLES_NUEVOS" :key="r">{{ r }}</option></select>
        <p v-if="errores.rol" class="error-txt">{{ errores.rol }}</p>
      </div>
      <div v-if="pideCentro">
        <label class="etiqueta" for="u-centro">Centro de atención</label>
        <select id="u-centro" v-model="f.centro_id" class="campo" :aria-invalid="!!errores.centro_id">
          <option value="" disabled>Elige…</option>
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
        <p v-if="errores.centro_id" class="error-txt">{{ errores.centro_id }}</p>
      </div>
      <p class="sm:col-span-2 text-sm text-muted">Las cuentas de jóvenes las crea cada persona desde "Crear cuenta". Por seguridad, la contraseña nunca se muestra después de guardarla.</p>
      <div class="sm:col-span-2 flex flex-wrap gap-3">
        <button class="btn-primario" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar' }}</button>
        <button type="button" class="btn-secundario" @click="editando = null">Cancelar</button>
      </div>
    </form>

    <form class="flex flex-wrap gap-3 items-end mb-4" @submit.prevent="filtrar">
      <div>
        <label class="etiqueta" for="u-filtro">Rol</label>
        <select id="u-filtro" v-model="filtroRol" class="campo"><option value="">Todos</option><option v-for="r in ROLES" :key="r">{{ r }}</option></select>
      </div>
      <button class="btn-secundario">Filtrar</button>
    </form>

    <p v-if="cargando" role="status">Cargando…</p>
    <div v-else class="overflow-x-auto tarjeta p-0">
      <table class="block sm:table w-full text-left text-sm">
        <caption class="sr-only">Usuarios del sistema</caption>
        <thead class="hidden sm:table-header-group bg-lav"><tr><th scope="col" class="p-3">Correo</th><th scope="col" class="p-3">Rol</th><th scope="col" class="p-3">Centro</th><th scope="col" class="p-3">Estado</th><th scope="col" class="p-3">Acciones</th></tr></thead>
        <tbody class="block sm:table-row-group">
          <tr v-for="u in usuarios" :key="u.id" class="block sm:table-row border-t border-violet-100">
            <td class="block sm:table-cell p-3 font-medium">{{ u.correo }}</td>
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Rol:</span>{{ u.rol }}</td>
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Centro:</span>{{ u.centro_id ? nombreCentro(u.centro_id) : '—' }}</td>
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Estado:</span><span class="rounded-full px-2.5 py-0.5 font-semibold" :class="u.activo ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700'">{{ u.activo ? 'Activo' : 'Desactivado' }}</span></td>
            <td class="block sm:table-cell p-3"><div class="flex flex-wrap gap-2">
              <button class="btn-secundario" @click="editar(u)">Editar</button>
              <button class="btn-secundario" @click="cambiarActivo(u)">{{ u.activo ? 'Desactivar' : 'Reactivar' }}</button>
            </div></td>
          </tr>
          <tr v-if="!usuarios.length" class="block sm:table-row"><td colspan="5" class="block sm:table-cell p-3">No hay usuarios para mostrar.</td></tr>
        </tbody>
      </table>
    </div>
    <nav v-if="!cargando && ultima > 1" class="flex flex-wrap items-center gap-3 mt-4" aria-label="Paginación de usuarios">
      <button class="btn-secundario" :disabled="pagina <= 1" @click="irA(pagina - 1)">Anterior</button>
      <span class="text-sm text-muted">Página {{ pagina }} de {{ ultima }} ({{ total }} usuarios)</span>
      <button class="btn-secundario" :disabled="pagina >= ultima" @click="irA(pagina + 1)">Siguiente</button>
    </nav>
  </section>
</template>
