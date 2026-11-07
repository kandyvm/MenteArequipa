<!-- Insignia de estado o riesgo. Siempre lleva texto, no solo color (WCAG 1.4.1). -->
<script setup>
import { computed } from 'vue'
const props = defineProps({ valor: String })
const E = {
  REGISTRADA: ['Registrada', 'bg-violet-100 text-violet-800'], EN_REVISION: ['En revisión', 'bg-amber-50 text-amber-700'],
  DERIVADA: ['Derivada', 'bg-blue-50 text-blue-800'], ACEPTADA: ['Aceptada', 'bg-green-50 text-green-700'],
  ATENDIDA: ['Atendida', 'bg-green-50 text-green-700'], CERRADA: ['Cerrada', 'bg-gray-100 text-gray-700'],
  CANCELADA: ['Cancelada', 'bg-gray-100 text-gray-700'], PENDIENTE: ['Pendiente', 'bg-amber-50 text-amber-700'], RECHAZADA: ['Rechazada', 'bg-red-50 text-red-700'],
  BAJO: ['Riesgo bajo', 'bg-green-50 text-green-700'], MEDIO: ['Riesgo medio', 'bg-amber-50 text-amber-700'],
  ALTO: ['Riesgo alto', 'bg-red-50 text-red-700'], CRISIS: ['Crisis', 'bg-crisis text-white'],
}
const info = computed(() => E[props.valor] ?? [props.valor, 'bg-gray-100 text-gray-700'])
</script>
<template><span class="inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold" :class="info[1]">{{ info[0] }}</span></template>
