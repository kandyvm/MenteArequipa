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
    <p class="text-muted mb-4">Registro de solo lectura: quién hizo qué y desde qué IP. Nadie puede modificarlo ni borrarlo, ni siquiera el administrador. Total: {{ total }}.</p>
    <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>
    <p v-if="cargando" role="status">Cargando…</p>
    <div v-else class="overflow-x-auto tarjeta p-0">
      <table class="w-full text-left text-sm">
        <caption class="sr-only">Registro de auditoría</caption>
        <thead class="bg-lav"><tr><th scope="col" class="p-3">Fecha</th><th scope="col" class="p-3">Usuario</th><th scope="col" class="p-3">Acción</th><th scope="col" class="p-3">Entidad</th><th scope="col" class="p-3">IP</th></tr></thead>
        <tbody>
          <tr v-for="a in filas" :key="a.id" class="border-t border-violet-100">
            <td class="p-3">{{ fecha(a.creado_en) }}</td><td class="p-3">{{ a.usuario_id ?? 'Público' }}</td>
            <td class="p-3 font-mono">{{ a.accion }}</td><td class="p-3">{{ a.entidad }} #{{ a.entidad_id }}</td><td class="p-3 font-mono">{{ a.ip }}</td>
          </tr>
          <tr v-if="!filas.length"><td colspan="5" class="p-3">Sin registros.</td></tr>
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
