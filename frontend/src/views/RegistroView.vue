<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'
import { erroresDe, mensajeDe } from '../api'
import { DISTRITOS } from '../constantes'
const auth = useAuth(), router = useRouter()
const f = reactive({ correo: '', contrasena: '', contrasena_confirmation: '', fecha_nacimiento: '', distrito: '', telefono: '', acepta_consentimiento: false })
const errores = ref({}), general = ref(''), cargando = ref(false)

function edad(iso) {
  const n = new Date(iso), h = new Date()
  let a = h.getFullYear() - n.getFullYear()
  if (h < new Date(h.getFullYear(), n.getMonth(), n.getDate())) a--
  return a
}
/** Validación rápida en el navegador; la validación real y obligatoria está en el servidor. */
function validar() {
  const e = {}
  if (!f.correo) e.correo = 'Escribe tu correo.'
  if (f.contrasena.length < 10) e.contrasena = 'Usa al menos 10 caracteres.'
  if (f.contrasena !== f.contrasena_confirmation) e.contrasena_confirmation = 'Las contraseñas no coinciden.'
  if (!f.fecha_nacimiento) e.fecha_nacimiento = 'Indica tu fecha de nacimiento.'
  else if (edad(f.fecha_nacimiento) < 18 || edad(f.fecha_nacimiento) > 29) e.fecha_nacimiento = 'La plataforma es para jóvenes de 18 a 29 años.'
  if (!f.distrito) e.distrito = 'Elige tu distrito.'
  if (!f.acepta_consentimiento) e.acepta_consentimiento = 'Debes aceptar el consentimiento para continuar.'
  return e
}
async function enviar() {
  general.value = ''; errores.value = validar()
  if (Object.keys(errores.value).length) return
  cargando.value = true
  try { await auth.registrar({ ...f, telefono: f.telefono || null }); router.push('/solicitudes/nueva') }
  catch (e) { errores.value = erroresDe(e); general.value = mensajeDe(e) }
  finally { cargando.value = false }
}
</script>
<template>
  <section class="mx-auto max-w-lg tarjeta">
    <h1 class="text-2xl font-semibold mb-1">Crear cuenta</h1>
    <p class="text-muted mb-4">Solo para jóvenes de 18 a 29 años. Pedimos los datos mínimos.</p>
    <form @submit.prevent="enviar" novalidate>
      <div v-for="c in [
        { id: 'correo', t: 'Correo', tipo: 'email', ac: 'username' },
        { id: 'contrasena', t: 'Contraseña (mínimo 10 caracteres)', tipo: 'password', ac: 'new-password' },
        { id: 'contrasena_confirmation', t: 'Repite la contraseña', tipo: 'password', ac: 'new-password' },
        { id: 'fecha_nacimiento', t: 'Fecha de nacimiento', tipo: 'date', ac: 'bday' },
        { id: 'telefono', t: 'Teléfono (opcional)', tipo: 'tel', ac: 'tel' },
      ]" :key="c.id" class="mb-4">
        <label class="etiqueta" :for="c.id">{{ c.t }}</label>
        <input :id="c.id" v-model="f[c.id]" :type="c.tipo" :autocomplete="c.ac" class="campo"
               :aria-invalid="!!errores[c.id]" :aria-describedby="errores[c.id] ? c.id + '-err' : undefined" />
        <p v-if="errores[c.id]" :id="c.id + '-err'" class="error-txt">{{ errores[c.id] }}</p>
      </div>
      <div class="mb-4">
        <label class="etiqueta" for="distrito">Distrito</label>
        <select id="distrito" v-model="f.distrito" class="campo" :aria-invalid="!!errores.distrito" :aria-describedby="errores.distrito ? 'distrito-err' : undefined">
          <option value="" disabled>Elige…</option>
          <option v-for="d in DISTRITOS" :key="d">{{ d }}</option>
        </select>
        <p v-if="errores.distrito" id="distrito-err" class="error-txt">{{ errores.distrito }}</p>
      </div>
      <details class="mb-3 rounded-lg bg-lav p-3 text-sm">
        <summary class="cursor-pointer font-medium min-h-[44px] flex items-center">Consentimiento informado (Ley N.º 29733)</summary>
        <p class="mt-2">Usaremos tus datos solo para orientarte y dar seguimiento a tus derivaciones. No pedimos diagnóstico ni historia clínica. Guardamos la fecha, tu IP y tu navegador al aceptar. Puedes consultar tu caso con un código, sin dar tu nombre.</p>
                <p class="mt-2">Es un proyecto académico en fase de prueba, así que no ingreses datos reales. La información se guarda en servidores de Render, fuera del Perú.</p>
        <p class="mt-2">Puedes pedir ver, corregir o eliminar tus datos, u oponerte a su uso (derechos ARCO), escribiendo al equipo del proyecto.</p>
      </details>
      <label class="flex items-start gap-3 mb-1 min-h-[44px]">
        <input v-model="f.acepta_consentimiento" type="checkbox" class="mt-1 h-5 w-5" :aria-describedby="errores.acepta_consentimiento ? 'cons-err' : undefined" />
        <span>He leído y acepto el consentimiento informado.</span>
      </label>
      <p v-if="errores.acepta_consentimiento" id="cons-err" class="error-txt mb-2">{{ errores.acepta_consentimiento }}</p>
      <p v-if="general" role="alert" class="error-txt mb-3">{{ general }}</p>
      <button class="btn-primario w-full mt-2" :disabled="cargando">{{ cargando ? 'Creando…' : 'Crear cuenta' }}</button>
    </form>
  </section>
</template>
