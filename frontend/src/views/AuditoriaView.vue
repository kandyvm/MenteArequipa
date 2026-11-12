<!-- Auditoría: solo lectura para el ADMIN. La tabla es inmutable (triggers en PostgreSQL). -->
<script setup>
import { ref, onMounted } from 'vue'
import { api, mensajeDe } from '../api'
const filas = ref([]), pagina = ref(1), ultima = ref(1), total = ref(0), cargando = ref(true), error = ref('')
async function cargar(p = 1) {
  cargando.value = true; error.value = ''
  try {
    const d = (await api.get('/api/auditoria', { params: { page: p } })).data
    filas.value = d.data; pagina.value = d.current_page; ultima.value = d.last_page; total.value = d.total
  } catch (e) { error.value = mensajeDe(e) } finally { cargando.value = false }
}
const fecha = (iso) => iso ? new Date(iso).toLocaleString('es-PE') : '—'
onMounted(() => cargar())
</script>
<template>
  <section>
    <h1 class="text-2xl font-semibold mb-1">Auditoría</h1>
    <p class="text-muted mb-4">Registro de solo lectura: quién hizo qué y desde qué IP. La aplicación no permite modificarlo ni borrarlo (lo impiden triggers de PostgreSQL). Total: {{ total }}.</p>
    <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>
    <p v-if="cargando" role="status">Cargando…</p>
    <div v-else class="overflow-x-auto tarjeta p-0">
      <table class="block sm:table w-full text-left text-sm">
        <caption class="sr-only">Registro de auditoría</caption>
        <thead class="hidden sm:table-header-group bg-lav"><tr><th scope="col" class="p-3">Fecha</th><th scope="col" class="p-3">Usuario</th><th scope="col" class="p-3">Acción</th><th scope="col" class="p-3">Entidad</th><th scope="col" class="p-3">IP</th></tr></thead>
        <tbody class="block sm:table-row-group">
          <tr v-for="a in filas" :key="a.id" class="block sm:table-row border-t border-violet-100">
            <td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Fecha:</span>{{ fecha(a.creado_en) }}</td><td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Usuario:</span>{{ a.usuario_id ?? 'Público' }}</td>
            <td class="block sm:table-cell p-3 font-mono"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Acción:</span>{{ a.accion }}</td><td class="block sm:table-cell p-3"><span class="sm:hidden font-sans font-semibold text-muted mr-2">Entidad:</span>{{ a.entidad }} #{{ a.entidad_id }}</td><td class="block sm:table-cell p-3 font-mono"><span class="sm:hidden font-sans font-semibold text-muted mr-2">IP:</span>{{ a.ip }}</td>
          </tr>
          <tr v-if="!filas.length" class="block sm:table-row"><td colspan="5" class="block sm:table-cell p-3">Sin registros.</td></tr>
        </tbody>
      </table>
    </div>
    <nav class="mt-4 flex items-center gap-3" aria-label="Paginación">
      <button class="btn-secundario" :disabled="pagina <= 1" @click="cargar(pagina - 1)">Anterior</button>
      <span>Página {{ pagina }} de {{ ultima }}</span>
      <button class="btn-secundario" :disabled="pagina >= ultima" @click="cargar(pagina + 1)">Siguiente</button>
    </nav>
  </section>
</template>
