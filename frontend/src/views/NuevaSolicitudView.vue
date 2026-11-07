<!-- Formulario guiado de 3 pasos (necesidad N1). El código de seguimiento se muestra al final (N2). -->
<script setup>
import { reactive, ref, computed } from 'vue'
import { api, erroresDe, mensajeDe } from '../api'
import { MOTIVOS, RIESGOS } from '../constantes'
const paso = ref(1), cargando = ref(false), errores = ref({}), general = ref(''), creada = ref(null), copiado = ref(false)
const f = reactive({ motivo: '', descripcion: '', nivel_riesgo: '', canal: 'WEB', acepta_consentimiento: false })
const restantes = computed(() => 500 - f.descripcion.length)

function validarPaso() {
  const e = {}
  if (paso.value === 1) {
    if (!f.motivo) e.motivo = 'Elige un motivo.'
    if (!f.descripcion.trim()) e.descripcion = 'Cuéntanos brevemente cómo te sientes.'
  } else if (paso.value === 2) {
    if (!f.nivel_riesgo) e.nivel_riesgo = 'Elige una opción.'
  } else if (!f.acepta_consentimiento) e.acepta_consentimiento = 'Debes aceptar el consentimiento para enviar.'
  errores.value = e
  return !Object.keys(e).length
}
const siguiente = () => { if (validarPaso()) paso.value++ }
async function enviar() {
  if (!validarPaso()) return
  cargando.value = true; general.value = ''
  try { creada.value = (await api.post('/api/solicitudes', f)).data.data }
  catch (e) { errores.value = erroresDe(e); general.value = mensajeDe(e) }
  finally { cargando.value = false }
}
async function copiar() { try { await navigator.clipboard.writeText(creada.value.codigo); copiado.value = true } catch { /* el código sigue visible */ } }
</script>
<template>
  <section class="mx-auto max-w-2xl">
    <!-- Resultado -->
    <div v-if="creada" class="tarjeta" aria-live="polite">
      <h1 class="text-2xl font-semibold mb-2">Recibimos tu solicitud</h1>
      <p>Guarda tu código de seguimiento. Con él puedes ver el estado de tu caso sin iniciar sesión.</p>
      <p class="my-4 rounded-lg bg-lav p-4 text-center font-mono text-3xl font-medium tracking-wider">{{ creada.codigo }}</p>
      <div class="flex flex-wrap gap-3">
        <button class="btn-secundario" @click="copiar">{{ copiado ? 'Código copiado' : 'Copiar código' }}</button>
        <router-link class="btn-primario" :to="`/consulta/${creada.codigo}`">Ver estado de mi caso</router-link>
      </div>
      <div v-if="creada.derivada_113" role="alert" class="mt-5 rounded-lg border-2 border-crisis bg-red-50 p-4">
        <p class="font-semibold text-red-700">Si estás en peligro ahora, llama de inmediato a la Línea 113, opción 5.</p>
        <a href="tel:113" class="btn-peligro mt-3">Llamar al 113</a>
      </div>
    </div>

    <!-- Formulario -->
    <div v-else class="tarjeta">
      <h1 class="text-2xl font-semibold">Nueva solicitud</h1>
      <p class="text-muted mb-4" aria-live="polite">Paso {{ paso }} de 3</p>
      <div class="h-2 rounded bg-violet-100 mb-5" role="progressbar" :aria-valuenow="paso" aria-valuemin="1" aria-valuemax="3"><div class="h-2 rounded bg-brand" :style="{ width: paso * 33.3 + '%' }"></div></div>
      <form @submit.prevent="paso < 3 ? siguiente() : enviar()" novalidate>
        <template v-if="paso === 1">
          <h2 class="text-lg font-semibold mb-3">Cuéntanos sobre ti</h2>
          <label class="etiqueta" for="motivo">Motivo de consulta</label>
          <select id="motivo" v-model="f.motivo" class="campo" :aria-invalid="!!errores.motivo" :aria-describedby="errores.motivo ? 'motivo-err' : undefined">
            <option value="" disabled>Elige…</option><option v-for="m in MOTIVOS" :key="m">{{ m }}</option>
          </select>
          <p v-if="errores.motivo" id="motivo-err" class="error-txt">{{ errores.motivo }}</p>
          <label class="etiqueta mt-4" for="descripcion">Descripción breve</label>
          <textarea id="descripcion" v-model="f.descripcion" rows="5" maxlength="500" class="campo" :aria-invalid="!!errores.descripcion" aria-describedby="desc-ayuda"></textarea>
          <p id="desc-ayuda" class="text-sm text-muted mt-1">No escribas diagnósticos ni datos de otras personas. Te quedan {{ restantes }} caracteres.</p>
          <p v-if="errores.descripcion" role="alert" class="error-txt">{{ errores.descripcion }}</p>
        </template>

        <template v-else-if="paso === 2">
          <fieldset>
            <legend class="text-lg font-semibold mb-1">¿Cómo describirías tu situación?</legend>
            <p class="text-sm text-muted mb-3">Nos ayuda a priorizar tu caso.</p>
            <div class="grid grid-cols-2 gap-3">
              <label v-for="r in RIESGOS" :key="r.v" class="cursor-pointer rounded-xl border-2 p-3 min-h-[44px] has-[:checked]:border-brand has-[:checked]:bg-lav" :class="r.v === 'CRISIS' ? 'border-red-300' : 'border-gray-300'">
                <input v-model="f.nivel_riesgo" type="radio" name="riesgo" :value="r.v" class="sr-only" />
                <span class="block font-semibold">{{ r.t }}</span><span class="block text-sm text-muted">{{ r.d }}</span>
              </label>
            </div>
            <p v-if="errores.nivel_riesgo" role="alert" class="error-txt">{{ errores.nivel_riesgo }}</p>
          </fieldset>
          <!-- Aviso de riesgo CRISIS (necesidad N8) -->
          <div v-if="f.nivel_riesgo === 'CRISIS'" role="alert" class="mt-4 rounded-lg border-2 border-crisis bg-red-50 p-4">
            <p class="font-semibold text-red-700">Esta plataforma no atiende emergencias. Si piensas en hacerte daño, llama ahora a la Línea 113, opción 5 (gratuita, 24 horas).</p>
            <a href="tel:113" class="btn-peligro mt-3">Llamar al 113</a>
            <p class="mt-3 text-sm">Si aun así envías la solicitud, la marcaremos para que un orientador la vea primero.</p>
          </div>
          <label class="etiqueta mt-4" for="canal">¿Cómo prefieres que te atiendan?</label>
          <select id="canal" v-model="f.canal" class="campo"><option value="WEB">Por la plataforma</option><option value="PRESENCIAL">Presencial</option></select>
        </template>

        <template v-else>
          <h2 class="text-lg font-semibold mb-3">Consentimiento y envío</h2>
          <dl class="rounded-lg bg-lav p-3 text-sm mb-4">
            <div class="flex gap-2"><dt class="text-muted">Motivo:</dt><dd>{{ f.motivo }}</dd></div>
            <div class="flex gap-2"><dt class="text-muted">Situación:</dt><dd>{{ RIESGOS.find(r => r.v === f.nivel_riesgo)?.t }}</dd></div>
          </dl>
          <p class="text-sm mb-3">Usaremos estos datos solo para orientarte y derivarte a un centro. No guardamos diagnósticos. Tu código de seguimiento no muestra tu nombre.</p>
          <label class="flex items-start gap-3 min-h-[44px]">
            <input v-model="f.acepta_consentimiento" type="checkbox" class="mt-1 h-5 w-5" />
            <span>Acepto el consentimiento informado (Ley N.º 29733).</span>
          </label>
          <p v-if="errores.acepta_consentimiento" role="alert" class="error-txt">{{ errores.acepta_consentimiento }}</p>
        </template>

        <p v-if="general" role="alert" class="error-txt mt-3">{{ general }}</p>
        <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-between gap-3">
          <button v-if="paso > 1" type="button" class="btn-secundario" @click="paso--; errores = {}">Atrás</button><span v-else></span>
          <button class="btn-primario" :disabled="cargando">{{ paso < 3 ? 'Siguiente' : cargando ? 'Enviando…' : 'Enviar solicitud' }}</button>
        </div>
      </form>
    </div>
  </section>
</template>
