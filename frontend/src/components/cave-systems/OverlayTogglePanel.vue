<template>
  <div v-if="overlays.length" class="overlay-toggle-panel">
    <div class="overlay-toggle-title">
      <v-icon size="16" :icon="mdiLayers" /> Overlays
    </div>
    <div v-for="ov in overlays" :key="ov.id" class="overlay-toggle-row">
      <v-switch
        :model-value="visibility[ov.id] !== false"
        color="primary"
        density="compact"
        hide-details
        :label="ov.name"
        :loading="loading[ov.id]"
        @update:model-value="(val) => $emit('toggle', ov.id, val)"
      />
    </div>
  </div>
</template>

<script setup>
import { mdiLayers } from '@mdi/js'

defineProps({
  overlays: {
    type: Array,
    default: () => [],
  },
  visibility: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Object,
    default: () => ({}),
  },
})

defineEmits(['toggle'])
</script>

<style scoped>
.overlay-toggle-panel {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 2;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 8px;
  padding: 6px 12px 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
  max-width: 240px;
}

.overlay-toggle-title {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #555;
  margin-bottom: 2px;
}

.overlay-toggle-row :deep(.v-selection-control) {
  min-height: 32px;
}

.overlay-toggle-row :deep(.v-label) {
  font-size: 13px;
  opacity: 1;
}
</style>
