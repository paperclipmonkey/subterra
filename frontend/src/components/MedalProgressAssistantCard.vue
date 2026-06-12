<template>
  <v-card variant="flat" class="medal-progress-card">
    <!-- Header: count + overall progress -->
    <div class="mp-header">
      <v-icon :icon="mdiMedalOutline" color="amber-darken-2" size="20" class="mr-2 flex-shrink-0" />
      <div class="mp-header-text">
        <span class="mp-title">Medals</span>
        <span class="mp-count">{{ earnedCount }} of {{ totalCount }} earned</span>
      </div>
      <router-link to="/medals" class="mp-viewall">
        View all
        <v-icon :icon="mdiArrowRight" size="13" />
      </router-link>
    </div>

    <v-progress-linear :model-value="totalCount ? (earnedCount / totalCount) * 100 : 0"
                       color="amber-darken-2" bg-color="grey-lighten-3" height="5" rounded class="mb-3" />

    <!-- Medal strip: every medal, earned in colour, locked greyscale -->
    <div class="mp-strip">
      <router-link v-for="medal in allMedals" :key="medal.name" v-tooltip="medal.name" to="/medals"
                   class="mp-thumb" :class="{ 'mp-locked': !medal.earned }">
        <img v-if="medal.image_url" :src="medal.image_url" :alt="medal.name">
        <v-icon v-else :icon="mdiMedalOutline" size="22" :color="medal.earned ? 'amber-darken-2' : 'grey-lighten-1'" />
      </router-link>
    </div>

    <!-- Closest to earn -->
    <div v-if="closest.length" class="mp-closest">
      <div class="mp-closest-label">Closest to earning</div>
      <div v-for="medal in closest" :key="medal.name" class="mp-closest-row">
        <div class="mp-thumb mp-locked mp-thumb-sm flex-shrink-0">
          <img v-if="medal.image_url" :src="medal.image_url" :alt="medal.name">
          <v-icon v-else :icon="mdiMedalOutline" size="18" color="grey-lighten-1" />
        </div>
        <div class="mp-closest-body">
          <div class="mp-closest-name">
            {{ medal.name }}
            <span v-if="medal.progress" class="mp-closest-progress">{{ medal.progress.current }} / {{ medal.progress.target }}</span>
          </div>
          <v-progress-linear v-if="medal.progress"
                             :model-value="(medal.progress.current / medal.progress.target) * 100"
                             color="amber-darken-2" bg-color="grey-lighten-3" height="4" rounded />
          <div v-else class="mp-closest-desc">{{ medal.description }}</div>
        </div>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiArrowRight, mdiMedalOutline } from '@mdi/js'

const props = defineProps({
  data: { type: Object, required: true },
})

const earned = computed(() => (props.data.earned || []).map(m => ({ ...m, earned: true })))
const unearned = computed(() => (props.data.unearned || []).map(m => ({ ...m, earned: false })))

const earnedCount = computed(() => earned.value.length)
const totalCount = computed(() => earned.value.length + unearned.value.length)

// Earned first in the strip; unearned arrive nearest-to-completion first
const allMedals = computed(() => [...earned.value, ...unearned.value])

// Top unearned medals with measurable progress toward them
const closest = computed(() => unearned.value.slice(0, 3))
</script>

<style scoped>
.medal-progress-card {
  border-radius: 16px;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  padding: 14px 16px;
  max-width: 480px;
}

.mp-header {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}
.mp-header-text {
  flex-grow: 1;
  min-width: 0;
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.mp-title {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.mp-count {
  font-size: 12px;
  color: #6b7280;
}
.mp-viewall {
  font-size: 12px;
  font-weight: 600;
  color: #2F6852;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}
.mp-viewall:hover {
  text-decoration: underline;
}

.mp-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 4px;
}
.mp-thumb {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  transition: transform 0.15s;
}
.mp-thumb:hover {
  transform: translateY(-2px);
}
.mp-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.15));
}
.mp-thumb-sm {
  width: 28px;
  height: 28px;
}
.mp-locked img {
  filter: grayscale(1) opacity(0.4);
}

.mp-closest {
  border-top: 1px solid #f3f4f6;
  margin-top: 10px;
  padding-top: 10px;
}
.mp-closest-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #9ca3af;
  margin-bottom: 8px;
}
.mp-closest-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}
.mp-closest-row:last-child {
  margin-bottom: 0;
}
.mp-closest-body {
  flex-grow: 1;
  min-width: 0;
}
.mp-closest-name {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 3px;
}
.mp-closest-progress {
  font-size: 11px;
  font-weight: 700;
  color: #6b7280;
}
.mp-closest-desc {
  font-size: 11px;
  color: #6b7280;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
