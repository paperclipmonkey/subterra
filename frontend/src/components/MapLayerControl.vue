<template>
  <div class="map-layer-control">
    <v-menu
      v-model="menu"
      :close-on-content-click="false"
      location="bottom end"
      transition="scale-transition"
    >
      <template #activator="{ props }">
        <v-btn
          v-bind="props"
          icon
          size="small"
          color="white"
          elevation="2"
          class="map-control-btn"
        >
          <v-icon :icon="mdiLayersOutline" />
        </v-btn>
      </template>

      <v-card min-width="180">
        <v-list density="compact">
          <v-list-subheader>Base Maps</v-list-subheader>
          <v-list-item
            v-for="item in styleOptions"
            :key="item.value"
            :active="selectedStyle === item.value"
            @click="selectStyle(item.value)"
          >
            <template #prepend>
              <v-icon :icon="item.icon" size="small" />
            </template>
            <v-list-item-title class="text-caption">{{ item.title }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-card>
    </v-menu>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { mdiLayersOutline, mdiTerrain, mdiSatelliteVariant, mdiRoad } from '@mdi/js'

const props = defineProps({
  modelValue: {
    type: [String, Object],
    required: true
  },
  customOptions: {
    type: Array,
    default: () => []
  },
  maptilerKey: {
    type: String,
    default: '0gGMv4po9Mjrpd64A528' // Default key found in codebase
  }
})

const emit = defineEmits(['update:modelValue'])

const menu = ref(false)

const defaultOptions = computed(() => {
  const key = props.maptilerKey
  return [
    {
      title: 'Topographic',
      value: `https://api.maptiler.com/maps/topo/style.json?key=${key}`,
      icon: mdiTerrain
    },
    {
      title: 'Satellite Hybrid',
      value: `https://api.maptiler.com/maps/hybrid/style.json?key=${key}`,
      icon: mdiSatelliteVariant
    },
    {
      title: 'Streets',
      value: `https://api.maptiler.com/maps/streets/style.json?key=${key}`,
      icon: mdiRoad
    }
  ]
})

const styleOptions = computed(() => {
  if (props.customOptions.length > 0) {
    return props.customOptions
  }
  return defaultOptions.value
})

const selectedStyle = ref(props.modelValue)

const selectStyle = (val) => {
  selectedStyle.value = val
  emit('update:modelValue', val)
  menu.value = false
}

// Watch for external style updates
watch(() => props.modelValue, (newVal) => {
  selectedStyle.value = newVal
})
</script>

<style scoped>
.map-layer-control {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 1000;
}
.map-control-btn {
  background-color: white !important;
  color: #333 !important;
  opacity: 0.9;
}
.map-control-btn:hover {
  opacity: 1;
}
</style>
