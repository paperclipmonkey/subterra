<template>
  <v-card
    variant="flat"
    class="hut-assistant-card"
    :to="`/huts/${hut.id}`"
  >
    <div class="hut-media">
      <v-img
        v-if="imageUrl && !imageBroken"
        :src="imageUrl"
        :alt="hut.name"
        cover
        height="120"
        @error="imageBroken = true"
      >
        <div class="hut-media-shade" />
      </v-img>
      <div v-else class="hut-media-fallback">
        <v-icon :icon="mdiHomeRoof" size="42" color="white" style="opacity:0.65" />
      </div>

      <span v-if="hut.distance_km !== null && hut.distance_km !== undefined" class="hut-distance">
        {{ hut.distance_km }}km
      </span>
    </div>

    <div class="hut-body">
      <div class="hut-name">{{ hut.name }}</div>
      <div v-if="hut.club" class="hut-club">{{ hut.club }}</div>

      <div v-if="hut.amenities && hut.amenities.length" class="hut-amenities">
        <v-chip
          v-for="amenity in hut.amenities.slice(0, 3)"
          :key="amenity"
          size="x-small"
          variant="tonal"
          color="success"
        >
          {{ amenity }}
        </v-chip>
        <span v-if="hut.amenities.length > 3" class="hut-amenities-more">
          +{{ hut.amenities.length - 3 }}
        </span>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed, ref } from 'vue'
import { mdiHomeRoof } from '@mdi/js'

const props = defineProps({
  hut: { type: Object, required: true },
})

// /default-hut.jpg is the placeholder — treat it as "no image" so we render
// the brown fallback gradient instead. (See Hut::getImageUrlAttribute.)
const imageUrl = computed(() => {
  const u = props.hut.image_url
  if (!u || u === '/default-hut.jpg') return null
  return u
})
const imageBroken = ref(false)
</script>

<style scoped>
.hut-assistant-card {
  border-radius: 16px;
  text-decoration: none;
  width: 230px;
  flex-shrink: 0;
  overflow: hidden;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  transition: box-shadow 0.18s, transform 0.18s;
}
.hut-assistant-card:hover {
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
}

.hut-media {
  position: relative;
  height: 120px;
  width: 100%;
  overflow: hidden;
  background: linear-gradient(135deg, #6D4C41 0%, #8D6E63 100%);
}
.hut-media :deep(.v-img) { height: 100% !important; }
.hut-media-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.hut-media-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(0,0,0,0) 50%, rgba(0,0,0,0.35) 100%);
}

.hut-distance {
  position: absolute;
  top: 8px;
  right: 8px;
  font-size: 11px;
  font-weight: 700;
  background: rgba(0,0,0,0.55);
  color: white;
  border-radius: 999px;
  padding: 3px 9px;
  backdrop-filter: blur(4px);
}

.hut-body {
  padding: 10px 12px 12px;
}
.hut-name {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.25;
  color: #111827;
  margin-bottom: 2px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.hut-club {
  font-size: 11px;
  color: #6b7280;
  margin-bottom: 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.hut-amenities {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
}
.hut-amenities-more {
  font-size: 10px;
  color: #9ca3af;
  align-self: center;
}
</style>
