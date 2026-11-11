<script setup>
import { useRouter } from 'vue-router'
import { useAuth } from './stores/auth'
import Linea113 from './components/Linea113.vue'
const auth = useAuth()
const router = useRouter()
async function salir() { await auth.logout(); router.push('/login') }
const enlace = 'min-h-[44px] inline-flex items-center px-3 rounded-lg font-medium text-brand hover:bg-violet-100'
</script>
<template>
  <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">Saltar al contenido</a>
  <Linea113 />
  <header class="bg-white border-b border-violet-200">
    <nav class="mx-auto max-w-5xl px-4 py-2 flex flex-wrap items-center gap-1" aria-label="Principal">
      <router-link to="/" class="font-bold text-brand text-lg mr-auto min-h-[44px] inline-flex items-center">MENTE Arequipa</router-link>
      <router-link to="/consulta" :class="enlace">Consultar caso</router-link>
      <template v-if="auth.logueado">
        <router-link v-if="auth.rol === 'JOVEN'" to="/solicitudes/nueva" :class="enlace">Nueva solicitud</router-link>
        <router-link to="/panel" :class="enlace">{{ auth.rol === 'JOVEN' ? 'Mis solicitudes' : 'Panel' }}</router-link>
        <router-link v-if="['ADMIN', 'CENTRO'].includes(auth.rol)" to="/centros" :class="enlace">Centros</router-link>
        <router-link v-if="auth.rol === 'ADMIN'" to="/auditoria" :class="enlace">Auditoría</router-link>
        <button class="btn-secundario" @click="salir">Cerrar sesión</button>
      </template>
      <template v-else>
        <router-link to="/login" :class="enlace">Iniciar sesión</router-link>
        <router-link to="/registro" class="btn-primario">Crear cuenta</router-link>
      </template>
    </nav>
  </header>
  <main id="contenido" tabindex="-1" class="mx-auto max-w-5xl px-4 py-6"><router-view /></main>
  <footer class="mx-auto max-w-5xl px-4 pb-8 text-sm text-muted">
    MENTE Arequipa orienta y da seguimiento a derivaciones. No es un servicio de emergencia ni emite diagnósticos.
  </footer>
</template>
