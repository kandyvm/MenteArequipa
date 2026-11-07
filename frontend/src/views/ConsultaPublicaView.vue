<!-- Consulta pública: sin inicio de sesión y sin datos personales (necesidad N3). -->
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, mensajeDe } from '../api'
import StepperEstados from '../components/StepperEstados.vue'
import Insignia from '../components/Insignia.vue'
const route = useRoute(), router = useRouter()
const codigo = ref(''), caso = ref(null), error = ref(''), cargando = ref(false)
const FORMATO = /^JOV-[A-Z0-9]{5}$/

async function consultar() {
  caso.value = null; error.value = ''
  codigo.value = codigo.value.trim().toUpperCase()
  if (!FORMATO.test(codigo.value)) { error.value = 'El código tiene el formato JOV-XXXXX (por ejemplo JOV-7K3Q9).'; return }
  cargando.value = true
  try { caso.value = (await api.get(`/api/consulta/${codigo.value}`)).data.data }
  catch (e) { error.value = e.response?.status === 404 ? 'No encontramos ese código. Revisa que esté bien escrito.' : mensajeDe(e) }
  finally { cargando.value = false }
}
onMounted(() => { if (route.params.codigo) { codigo.value = route.params.codigo; consultar() } })
</script>
<template>
  <section>
    <h1 class="text-2xl font-semibold mb-1">Consulta el estado de tu caso</h1>
    <p class="text-muted mb-4">Escribe tu código de seguimiento. No necesitas iniciar sesión ni dar tu nombre; tú o un familiar pueden consultarlo.</p>
    <form class="tarjeta flex flex-col sm:flex-row gap-3 sm:items-end" @submit.prevent="consultar" novalidate>
      <div class="flex-1">
        <label class="etiqueta" for="codigo">Código de seguimiento</label>
        <input id="codigo" v-model="codigo" class="campo font-mono uppercase" maxlength="9" autocomplete="off" placeholder="JOV-7K3Q9"
               :aria-invalid="!!error" :aria-describedby="error ? 'codigo-err' : undefined" />
      </div>
      <button class="btn-primario" :disabled="cargando">{{ cargando ? 'Consultando…' : 'Consultar' }}</button>
    </form>
    <p v-if="error" id="codigo-err" role="alert" class="error-txt mt-3">{{ error }}</p>

    <div v-if="caso" class="tarjeta mt-6" aria-live="polite">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
        <p>Código <span class="font-mono font-medium">{{ caso.codigo }}</span></p>
        <Insignia :valor="caso.estado" />
      </div>
      <StepperEstados :estado="caso.estado" />
      <dl class="mt-5 grid sm:grid-cols-2 gap-3 text-sm">
        <div><dt class="text-muted">Centro asignado</dt><dd class="font-medium">{{ caso.centro ?? 'Aún sin centro' }}</dd></div>
        <div><dt class="text-muted">Última actualización</dt><dd class="font-medium">{{ caso.actualizado_en }}</dd></div>
      </dl>
      <p v-if="['CERRADA','CANCELADA'].includes(caso.estado)" class="mt-4 text-sm">Este caso está {{ caso.estado === 'CERRADA' ? 'cerrado' : 'cancelado' }}.</p>
    </div>
  </section>
</template>
