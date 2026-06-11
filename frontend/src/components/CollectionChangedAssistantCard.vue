<template>
  <v-card
    variant="flat"
    class="collection-changed-card"
  >
    <div class="cc-header" :class="`cc-header--${action}`">
      <v-icon :icon="headerIcon" size="18" color="white" class="mr-2" />
      <span class="cc-header-text">{{ headerText }}</span>
    </div>

    <div class="cc-body">
      <div class="cc-name">{{ collection.name }}</div>
      <div v-if="metaText" class="cc-meta">{{ metaText }}</div>
    </div>

    <div v-if="collection.url" class="cc-actions">
      <v-btn
        :href="collection.url"
        variant="tonal"
        color="primary"
        size="small"
        :prepend-icon="mdiEye"
        class="cc-btn"
      >
        View collection
      </v-btn>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiCheckCircleOutline, mdiPencilOutline, mdiDeleteOutline, mdiEye } from '@mdi/js'

const props = defineProps({
  collection: { type: Object, required: true },
})

const action = computed(() => props.collection.action || 'updated')

const headerText = computed(() => ({
  created: 'Collection created',
  updated: 'Collection updated',
  deleted: 'Collection deleted',
}[action.value] || 'Collection updated'))

const headerIcon = computed(() => ({
  created: mdiCheckCircleOutline,
  updated: mdiPencilOutline,
  deleted: mdiDeleteOutline,
}[action.value] || mdiPencilOutline))

const metaText = computed(() => {
  if (action.value === 'deleted') return 'The caves it contained are unaffected.'
  const count = props.collection.cave_count
  if (count === null || count === undefined) return ''
  return count === 1 ? '1 cave' : `${count} caves`
})
</script>

<style scoped>
.collection-changed-card {
  border-radius: 16px;
  width: 300px;
  flex-shrink: 0;
  overflow: hidden;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.cc-header {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  color: white;
}

.cc-header--created {
  background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
}

.cc-header--updated {
  background: linear-gradient(135deg, #6a1b9a 0%, #8e24aa 100%);
}

.cc-header--deleted {
  background: linear-gradient(135deg, #b71c1c 0%, #e53935 100%);
}

.cc-header-text {
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.cc-body {
  padding: 10px 14px 6px;
}

.cc-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1a1a1a;
  line-height: 1.3;
  margin-bottom: 4px;
}

.cc-meta {
  font-size: 0.78rem;
  color: #666;
}

.cc-actions {
  display: flex;
  gap: 8px;
  padding: 8px 14px 12px;
}

.cc-btn {
  font-size: 0.75rem !important;
}
</style>
