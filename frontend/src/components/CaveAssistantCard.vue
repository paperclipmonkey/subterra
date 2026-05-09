<template>
  <v-card
    variant="flat"
    class="cave-assistant-card"
    :to="`/cave-systems/${system.slug}`"
    style="border-radius: 14px; text-decoration: none; min-width: 175px; max-width: 210px; flex-shrink: 0; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);"
  >
    <!-- Gradient header -->
    <div class="card-header" :style="{ background: headerGradient }">
      <div class="card-title-row">
        <v-icon :icon="mdiTerrain" size="14" color="white" style="opacity:0.85" />
        <span class="card-grade" v-if="system.grades">{{ system.grades }}</span>
      </div>
      <div class="card-name">{{ system.name }}</div>
    </div>

    <!-- Stats + tags -->
    <div class="pa-2 pt-3">
      <div class="d-flex ga-3 mb-2 text-caption font-weight-medium">
        <span v-if="system.length_m" class="stat-pill">
          <v-icon :icon="mdiArrowLeftRight" size="11" class="mr-1" />{{ formatLength(system.length_m) }}
        </span>
        <span v-if="system.vertical_range_m" class="stat-pill">
          <v-icon :icon="mdiArrowUpDown" size="11" class="mr-1" />{{ system.vertical_range_m }}m
        </span>
      </div>

      <div v-if="system.tags && system.tags.length" class="d-flex flex-wrap ga-1">
        <v-chip
          v-for="tag in system.tags.slice(0, 2)"
          :key="tag"
          size="x-small"
          variant="tonal"
          color="primary"
          :to="`/caves?tags=${encodeURIComponent(tag)}&view=list`"
          @click.stop
        >
          {{ tag }}
        </v-chip>
        <span v-if="system.tags.length > 2" class="text-caption text-grey align-self-center ml-1">+{{ system.tags.length - 2 }}</span>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiArrowLeftRight, mdiArrowUpDown, mdiTerrain } from '@mdi/js'

const props = defineProps({
  system: {
    type: Object,
    required: true,
  },
})

const GRADE_GRADIENTS = {
  easy:     'linear-gradient(135deg, #2E7D32 0%, #43A047 100%)',
  moderate: 'linear-gradient(135deg, #0277BD 0%, #039BE5 100%)',
  hard:     'linear-gradient(135deg, #E65100 0%, #FB8C00 100%)',
  severe:   'linear-gradient(135deg, #B71C1C 0%, #E53935 100%)',
  expert:   'linear-gradient(135deg, #4A148C 0%, #7B1FA2 100%)',
}
const DEFAULT_GRADIENT = 'linear-gradient(135deg, #37474F 0%, #546E7A 100%)'

const headerGradient = computed(() => {
  const g = (props.system.grades || '').toLowerCase()
  for (const [key, grad] of Object.entries(GRADE_GRADIENTS)) {
    if (g.includes(key)) return grad
  }
  return DEFAULT_GRADIENT
})

function formatLength(metres) {
  if (!metres) return null
  return metres >= 1000 ? `${(metres / 1000).toFixed(1)}km` : `${metres}m`
}
</script>

<style scoped>
.cave-assistant-card {
  transition: box-shadow 0.15s, transform 0.15s;
  cursor: pointer;
  background: #fff;
}

.cave-assistant-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
  transform: translateY(-3px);
}

.card-header {
  padding: 12px 12px 10px;
  color: white;
}

.card-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.card-grade {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.9;
  background: rgba(255,255,255,0.2);
  border-radius: 4px;
  padding: 1px 5px;
}

.card-name {
  font-size: 13px;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.01em;
  text-shadow: 0 1px 2px rgba(0,0,0,0.15);
}

.stat-pill {
  display: inline-flex;
  align-items: center;
  color: #546e7a;
  font-size: 11px;
}
</style>
