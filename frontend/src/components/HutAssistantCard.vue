<template>
  <v-card
    variant="flat"
    class="hut-assistant-card"
    :to="`/huts/${hut.id}`"
    style="border-radius: 14px; text-decoration: none; min-width: 200px; max-width: 220px; flex-shrink: 0; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);"
  >
    <div class="hut-header">
      <div class="hut-title-row">
        <v-icon :icon="mdiHomeRoof" size="14" color="white" style="opacity:0.9" />
        <span v-if="hut.distance_km !== null && hut.distance_km !== undefined" class="hut-distance">
          {{ hut.distance_km }}km
        </span>
      </div>
      <div class="hut-name">{{ hut.name }}</div>
      <div v-if="hut.club" class="hut-club">{{ hut.club }}</div>
    </div>

    <div class="pa-2 pt-3">
      <div v-if="hut.amenities && hut.amenities.length" class="d-flex flex-wrap ga-1 mb-1">
        <v-chip
          v-for="amenity in hut.amenities.slice(0, 3)"
          :key="amenity"
          size="x-small"
          variant="tonal"
          color="success"
        >
          {{ amenity }}
        </v-chip>
        <span v-if="hut.amenities.length > 3" class="text-caption text-grey align-self-center ml-1">
          +{{ hut.amenities.length - 3 }}
        </span>
      </div>
      <div v-else class="text-caption text-grey">
        Tap for details
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { mdiHomeRoof } from '@mdi/js'

defineProps({
  hut: {
    type: Object,
    required: true,
  },
})
</script>

<style scoped>
.hut-assistant-card {
  transition: box-shadow 0.15s, transform 0.15s;
  cursor: pointer;
  background: #fff;
}

.hut-assistant-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
  transform: translateY(-3px);
}

.hut-header {
  padding: 12px 12px 10px;
  color: white;
  background: linear-gradient(135deg, #6D4C41 0%, #8D6E63 100%);
}

.hut-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.hut-distance {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.95;
  background: rgba(255,255,255,0.22);
  border-radius: 4px;
  padding: 1px 5px;
}

.hut-name {
  font-size: 13px;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.01em;
  text-shadow: 0 1px 2px rgba(0,0,0,0.15);
}

.hut-club {
  font-size: 10px;
  font-weight: 500;
  color: rgba(255,255,255,0.85);
  margin-top: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
