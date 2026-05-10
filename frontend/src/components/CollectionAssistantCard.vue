<template>
  <v-card
    variant="flat"
    class="collection-assistant-card"
    :to="collection.url || `/collections/${collection.slug}`"
  >
    <!-- Hero image with purple-themed fallback gradient -->
    <div class="col-media">
      <v-img
        v-if="imageUrl"
        :src="imageUrl"
        :alt="collection.name"
        cover
        height="120"
      >
        <div class="col-media-shade" />
      </v-img>
      <div v-else class="col-media-fallback">
        <v-icon :icon="mdiFormatListChecks" size="42" color="white" style="opacity:0.7" />
      </div>

      <span v-if="collection.user_progress" class="col-progress-badge">
        {{ collection.user_progress }}
      </span>

      <div v-if="isComplete" class="col-complete-badge">
        <v-icon :icon="mdiCheckCircle" size="12" class="mr-1" />Complete
      </div>
    </div>

    <div class="col-body">
      <div class="col-name">{{ collection.name }}</div>

      <p v-if="collection.description" class="col-description">
        {{ collection.description }}
      </p>

      <v-progress-linear
        v-if="totalCaves > 0"
        :model-value="progressPercent"
        :color="isComplete ? 'success' : 'primary'"
        height="4"
        rounded
        class="mb-1"
      />

      <div class="col-meta">
        <span>{{ visitedCount }} of {{ totalCaves }} done</span>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiCheckCircle, mdiFormatListChecks } from '@mdi/js'

const props = defineProps({
  collection: { type: Object, required: true },
})

const totalCaves = computed(() => Number(props.collection.cave_count ?? 0))
const visitedCount = computed(() => Number(props.collection.user_visited_count ?? 0))
const progressPercent = computed(() =>
  totalCaves.value === 0 ? 0 : Math.round((visitedCount.value / totalCaves.value) * 100)
)
const isComplete = computed(() => totalCaves.value > 0 && visitedCount.value >= totalCaves.value)
const imageUrl = computed(() => props.collection.image_url || null)
</script>

<style scoped>
.collection-assistant-card {
  border-radius: 16px;
  text-decoration: none;
  width: 260px;
  flex-shrink: 0;
  overflow: hidden;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  transition: box-shadow 0.18s, transform 0.18s;
}
.collection-assistant-card:hover {
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
}

.col-media {
  position: relative;
  height: 120px;
  width: 100%;
  overflow: hidden;
  background: linear-gradient(135deg, #6A1B9A 0%, #9C27B0 100%);
}
.col-media :deep(.v-img) { height: 100% !important; }
.col-media-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.col-media-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(106,27,154,0.2) 0%, rgba(0,0,0,0.4) 100%);
}

.col-progress-badge {
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

.col-complete-badge {
  position: absolute;
  bottom: 8px;
  left: 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: rgba(46, 125, 50, 0.92);
  color: white;
  border-radius: 4px;
  padding: 3px 7px;
  display: inline-flex;
  align-items: center;
}

.col-body {
  padding: 10px 12px 12px;
}
.col-name {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.25;
  color: #111827;
  margin-bottom: 4px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.col-description {
  font-size: 11px;
  line-height: 1.5;
  color: #4b5563;
  margin: 0 0 8px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.col-meta {
  font-size: 11px;
  color: #6b7280;
  margin-top: 4px;
}
</style>
