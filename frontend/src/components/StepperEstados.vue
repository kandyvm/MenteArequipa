<!-- Línea de tiempo de 5 etapas: horizontal en escritorio, vertical en móvil de 360 px. -->
<script setup>
import { computed } from 'vue'
import { ETAPAS } from '../constantes'
const props = defineProps({ estado: String })
const actual = computed(() => ETAPAS.findIndex(([v]) => v === props.estado))
</script>
<template>
  <ol class="flex flex-col md:flex-row gap-3 md:gap-0" aria-label="Etapas del caso">
    <li v-for="([v, t], i) in ETAPAS" :key="v" class="flex md:flex-col md:flex-1 items-center md:text-center gap-3 md:gap-2"
        :aria-current="i === actual ? 'step' : undefined">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 font-semibold"
            :class="i < actual ? 'bg-brand border-brand text-white' : i === actual ? 'border-brand bg-lav text-brand ring-2 ring-brand' : 'border-gray-400 text-muted bg-white'">
        {{ i < actual ? '✓' : i + 1 }}
      </span>
      <span :class="i === actual ? 'font-semibold' : 'text-muted'">{{ t }}<span class="sr-only">{{ i < actual ? ' (completada)' : i === actual ? ' (etapa actual)' : ' (pendiente)' }}</span></span>
    </li>
  </ol>
</template>
