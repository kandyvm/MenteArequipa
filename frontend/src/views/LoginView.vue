<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'
import { mensajeDe } from '../api'
const auth = useAuth(), route = useRoute(), router = useRouter()
const f = reactive({ correo: '', contrasena: '' })
const ver = ref(false), error = ref(''), cargando = ref(false)
async function enviar() {
  error.value = ''; cargando.value = true
  try { await auth.login(f.correo, f.contrasena); router.push(route.query.redirect || auth.inicio) }
  catch (e) { error.value = e.response?.status === 422 ? 'Correo o contraseña incorrectos.' : mensajeDe(e) }
  finally { cargando.value = false }
}
</script>
<template>
  <section class="mx-auto max-w-md tarjeta">
    <h1 class="text-2xl font-semibold mb-4">Iniciar sesión</h1>
    <form @submit.prevent="enviar" novalidate>
      <div class="mb-4">
        <label class="etiqueta" for="correo">Correo</label>
        <input id="correo" v-model="f.correo" type="email" autocomplete="username" required class="campo" />
      </div>
      <div class="mb-4">
        <label class="etiqueta" for="contrasena">Contraseña</label>
        <div class="flex gap-2">
          <input id="contrasena" v-model="f.contrasena" :type="ver ? 'text' : 'password'" autocomplete="current-password" required class="campo" />
          <button type="button" class="btn-secundario" :aria-pressed="ver" @click="ver = !ver">{{ ver ? 'Ocultar' : 'Mostrar' }}</button>
        </div>
      </div>
      <p v-if="error" role="alert" class="error-txt mb-3">{{ error }}</p>
      <button class="btn-primario w-full" :disabled="cargando">{{ cargando ? 'Ingresando…' : 'Ingresar' }}</button>
    </form>
    <p class="mt-4 text-sm">¿Eres joven y aún no tienes cuenta? <router-link class="text-brand underline" to="/registro">Crear cuenta</router-link></p>
  </section>
</template>
