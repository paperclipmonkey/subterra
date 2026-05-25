<template>
  <v-card
    variant="flat"
    class="cave-assistant-card"
    :to="cardLink"
  >
    <!-- Hero image with grade-tinted gradient fallback -->
    <div class="cac-media" :style="!imageUrl ? { background: headerGradient } : null">
      <v-img
        v-if="imageUrl"
        :src="imageUrl"
        :alt="system.name"
        cover
        height="120"
      >
        <div class="cac-media-shade" />
      </v-img>
      <div v-else class="cac-media-fallback">
        <v-icon :icon="mdiTerrain" size="42" color="white" style="opacity:0.65" />
      </div>

      <span v-if="system.grades" class="cac-badge">{{ system.grades }}</span>
      <div v-if="system.entrance_count > 1" class="cac-pill">
        <v-icon :icon="mdiDoorOpen" size="11" class="mr-1" />{{ system.entrance_count }}
      </div>
    </div>

    <div class="cac-body">
      <div class="cac-name">{{ system.name }}</div>
      <div v-if="system.location_name" class="cac-location">
        <v-icon :icon="mdiMapMarkerOutline" size="11" class="mr-1" />{{ system.location_name }}
      </div>

      <div class="cac-stats">
        <span v-if="system.length_m" class="cac-stat">
          <v-icon :icon="mdiArrowLeftRight" size="11" class="mr-1" />{{ formatLength(system.length_m) }}
        </span>
        <span v-if="system.vertical_range_m" class="cac-stat">
          <v-icon :icon="mdiArrowUpDown" size="11" class="mr-1" />{{ system.vertical_range_m }}m
        </span>
      </div>

      <div v-if="system.tags && system.tags.length" class="cac-tags">
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
        <span v-if="system.tags.length > 2" class="cac-tags-more">
          +{{ system.tags.length - 2 }}
        </span>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiArrowLeftRight, mdiArrowUpDown, mdiDoorOpen, mdiMapMarkerOutline, mdiTerrain } from '@mdi/js'

const props = defineProps({
  system: { type: Object, required: true },
})

const cardLink = computed(() => {
  if (props.system.preferred_link) return props.system.preferred_link
  if (props.system.entrance_count === 1 && props.system.primary_cave_slug) {
    return `/caves/${props.system.primary_cave_slug}`
  }
  return `/cave-systems/${props.system.slug}`
})

const imageUrl = computed(() => props.system.image_url || null)

// Grade-tinted gradient as a colourful fallback when there's no hero image
const GRADE_GRADIENTS = {
  easy:     'linear-gradient(135deg, #2E7D32 0%, #43A047 100%)',
  moderate: 'linear-gradient(135deg, #0277BD 0%, #039BE5 100%)',
  hard:     'linear-gradient(135deg, #E65100 0%, #FB8C00 100%)',
  severe:   'linear-gradient(135deg, #B71C1C 0%, #E53935 100%)',
  expert:   'linear-gradient(135deg, #4A148C 0%, #7B1FA2 100%)',
  beginner: 'linear-gradient(135deg, #2E7D32 0%, #66BB6A 100%)',
  sporting: 'linear-gradient(135deg, #1565C0 0%, #1E88E5 100%)',
}
const DEFAULT_GRADIENT = 'linear-gradient(135deg, #37474F 0%, #546E7A 100%)'
const headerGradient = computed(() => {
  const g = (props.system.grades || '').toLowerCase()
  for (const [key, grad] of Object.entries(GRADE_GRADIENTS)) {
    if (g.includes(key)) return grad
  }
  // Fall back to checking tags for a hint
  const tagBlob = (props.system.tags || []).join(' ').toLowerCase()
  for (const [key, grad] of Object.entries(GRADE_GRADIENTS)) {
    if (tagBlob.includes(key)) return grad
  }
  return DEFAULT_GRADIENT
})

function formatLength(m) {
  if (!m) return null
  return m >= 1000 ? `${(m / 1000).toFixed(1)}km` : `${m}m`
}
</script>

<style scoped>
.cave-assistant-card {
  border-radius: 16px;
  text-decoration: none;
  width: 240px;
  flex-shrink: 0;
  overflow: hidden;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  transition: box-shadow 0.18s, transform 0.18s;
}
.cave-assistant-card:hover {
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
}

.cac-media {
  position: relative;
  height: 120px;
  width: 100%;
  overflow: hidden;
}
.cac-media-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cac-media :deep(.v-img) {
  height: 100% !important;
}
.cac-media-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(0,0,0,0) 50%, rgba(0,0,0,0.35) 100%);
}

.cac-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: rgba(0,0,0,0.55);
  color: white;
  border-radius: 4px;
  padding: 2px 7px;
  backdrop-filter: blur(4px);
}
.cac-pill {
  position: absolute;
  top: 8px;
  right: 8px;
  font-size: 10px;
  font-weight: 600;
  background: rgba(255,255,255,0.85);
  color: #374151;
  border-radius: 999px;
  padding: 2px 8px;
  display: inline-flex;
  align-items: center;
}

.cac-body {
  padding: 10px 12px 12px;
}
.cac-name {
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
.cac-location {
  display: flex;
  align-items: center;
  font-size: 11px;
  color: #6b7280;
  margin-bottom: 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cac-stats {
  display: flex;
  gap: 10px;
  margin-bottom: 8px;
}
.cac-stat {
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  color: #4b5563;
  font-weight: 500;
}
.cac-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
}
.cac-tags-more {
  font-size: 10px;
  color: #9ca3af;
  align-self: center;
}
</style>
