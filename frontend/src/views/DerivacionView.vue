<!-- Derivación asistida (necesidades N4 y N6): centro por distrito + prioridad 1, 3 o 5. Sin diagnóstico clínico. -->
<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, erroresDe, mensajeDe } from '../api'
import { DISTRITOS } from '../constantes'
import Insignia from '../components/Insignia.vue'
const route = useRoute(), router = useRouter()
const solicitud = ref(null), centros = ref([]), distrito = ref(''), error = ref(''), errores = ref({}), cargando = ref(false)
const f = reactive({ centro_id: null, prioridad: 3, observaciones: '' })
const PRIORIDADES = [[1, 'Alta'], [3, 'Media'], [5, 'Baja']]

async function cargarCentros() {
  try { centros.value = (await api.get('/api/centros', { params: distrito.value ? { distrito: distrito.value } : {} })).data.data; f.centro_id = null }
  catch (e) { error.value = mensajeDe(e) }
}
async function enviar() {
  errores.value = {}; error.value = ''
  if (!f.centro_id) { errores.value = { centro_id: 'Elige un centro.' }; return }
  cargando.value = true
  try { await api.post(`/api/solicitudes/${route.params.id}/derivaciones`, f); router.push('/panel') }
  catch (e) { errores.value = erroresDe(e); error.value = mensajeDe(e) } finally { cargando.value = false }
}
watch(distrito, cargarCentros)
onMounted(async () => {
  try { solicitud.value = (await api.get(`/api/solicitudes/${route.params.id}`)).data.data; await cargarCentros() }
  catch (e) { error.value = mensajeDe(e) }
})
</script>
<template>
  <section>
    <h1 class="text-2xl font-semibold mb-4">Derivación asistida</h1>
    <p v-if="error" role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-700">{{ error }}</p>
    <div v-if="solicitud" class="grid md:grid-cols-5 gap-4">
      <div class="tarjeta md:col-span-3">
        <h2 class="font-semibold mb-2">Solicitud <span class="font-mono">{{ solicitud.codigo }}</span></h2>
        <div class="flex gap-2 mb-3"><Insignia :valor="solicitud.nivel_riesgo" /><Insignia :valor="solicitud.estado" /></div>
        <dl class="text-sm space-y-2">
          <div><dt class="text-muted">Motivo</dt><dd>{{ solicitud.motivo }}</dd></div>
          <div><dt class="text-muted">Descripción</dt><dd>{{ solicitud.descripcion }}</dd></div>
        </dl>
        <div v-if="solicitud.derivada_113" role="note" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">Riesgo CRISIS: la persona recibió el aviso de la Línea 113. Prioriza este caso.</div>
      </div>

      <form class="tarjeta md:col-span-2" @submit.prevent="enviar" novalidate>
        <label class="etiqueta" for="distrito">Filtrar centros por distrito</label>
        <select id="distrito" v-model="distrito" class="campo mb-4"><option value="">Todos</option><option v-for="d in DISTRITOS" :key="d">{{ d }}</option></select>

        <fieldset class="mb-4">
          <legend class="etiqueta">Centro de atención</legend>
          <p v-if="!centros.length" class="text-sm text-muted">No hay centros activos en este distrito.</p>
          <label v-for="c in centros" :key="c.id" class="flex items-start gap-3 min-h-[44px] py-2">
            <input v-model="f.centro_id" type="radio" name="centro" :value="c.id" class="mt-1 h-5 w-5" />
            <span><span class="font-medium">{{ c.nombre }}</span><br /><span class="text-sm text-muted">{{ c.tipo }} · {{ c.distrito }}</span></span>
          </label>
          <p v-if="errores.centro_id" role="alert" class="error-txt">{{ errores.centro_id }}</p>
        </fieldset>

        <label class="etiqueta" for="prioridad">Prioridad</label>
        <select id="prioridad" v-model.number="f.prioridad" class="campo mb-4"><option v-for="[v, t] in PRIORIDADES" :key="v" :value="v">{{ v }} · {{ t }}</option></select>

        <label class="etiqueta" for="obs">Observaciones administrativas</label>
        <textarea id="obs" v-model="f.observaciones" rows="3" class="campo" aria-describedby="obs-ayuda"></textarea>
        <p id="obs-ayuda" class="text-sm text-muted mt-1">Sin diagnóstico ni notas clínicas.</p>
        <button class="btn-primario w-full mt-4" :disabled="cargando">{{ cargando ? 'Derivando…' : 'Derivar al centro' }}</button>
      </form>
    </div>
  </section>
</template>
