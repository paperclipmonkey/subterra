<template>
  <v-card variant="flat" class="import-card">
    <div class="ic-header" :class="{ 'ic-header--done': imported }">
      <v-icon :icon="imported ? mdiCheckCircleOutline : mdiNotebookEditOutline" size="18" color="white" class="mr-2" />
      <span class="ic-header-text">{{ title }}</span>
    </div>

    <div class="ic-body">
      <div v-if="status?.filename" class="ic-file">{{ status.filename }}</div>

      <div v-if="status && status.total" class="ic-bar" role="img" :aria-label="barLabel">
        <span
          v-for="seg in segments"
          :key="seg.key"
          class="ic-seg"
          :class="`ic-seg--${seg.key}`"
          :style="{ width: `${(seg.count / status.total) * 100}%` }"
        />
      </div>

      <div v-if="status" class="ic-counts">
        <span v-for="seg in segments" :key="seg.key" class="ic-count">
          <span class="ic-dot" :class="`ic-seg--${seg.key}`" />
          {{ seg.count }} {{ seg.label }}
        </span>
      </div>

      <ul v-if="imported?.trips?.length" class="ic-trips">
        <li v-for="trip in imported.trips" :key="trip.trip_url">
          <router-link :to="trip.trip_url">{{ trip.name }}</router-link>
        </li>
      </ul>
    </div>

    <div v-if="imported" class="ic-actions">
      <v-btn
        :to="imported.trips_url || '/trips'"
        variant="tonal"
        color="primary"
        size="small"
        :prepend-icon="mdiEye"
      >
        View your trips
      </v-btn>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiCheckCircleOutline, mdiEye, mdiNotebookEditOutline } from '@mdi/js'

const props = defineProps({
  /** Counts by row status, from the `import_status` event */
  status: { type: Object, default: null },
  /** Result of a bulk import, from the `trips_imported` event */
  imported: { type: Object, default: null },
})

const SEGMENTS = [
  { key: 'imported', label: 'imported' },
  { key: 'ready', label: 'ready' },
  { key: 'needs_review', label: 'to check' },
  { key: 'duplicate', label: 'possible duplicates' },
  { key: 'skipped', label: 'skipped' },
]

const segments = computed(() =>
  SEGMENTS
    .map(s => ({ ...s, count: props.status?.[s.key] ?? 0 }))
    .filter(s => s.count > 0),
)

const title = computed(() => {
  if (props.imported) {
    const n = props.imported.imported
    return n === 1 ? '1 trip imported' : `${n} trips imported`
  }
  return 'Import progress'
})

const barLabel = computed(() => segments.value.map(s => `${s.count} ${s.label}`).join(', '))
</script>

<style scoped>
.import-card {
  border-radius: 16px;
  width: 320px;
  max-width: 100%;
  overflow: hidden;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.ic-header {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  background: linear-gradient(135deg, #1867c0 0%, #3b82d6 100%);
  color: white;
}

.ic-header--done {
  background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
}

.ic-header-text {
  font-weight: 600;
  font-size: 14px;
}

.ic-body {
  padding: 12px 14px 4px;
}

.ic-file {
  font-size: 12px;
  color: rgba(0, 0, 0, 0.6);
  margin-bottom: 8px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ic-bar {
  display: flex;
  height: 8px;
  border-radius: 4px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.06);
}

.ic-seg {
  display: block;
  height: 100%;
}

.ic-seg--imported { background: #43a047; }
.ic-seg--ready { background: #1e88e5; }
.ic-seg--needs_review { background: #fb8c00; }
.ic-seg--duplicate { background: #8e24aa; }
.ic-seg--skipped { background: #9e9e9e; }

.ic-counts {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  margin-top: 8px;
  font-size: 12px;
  color: rgba(0, 0, 0, 0.75);
}

.ic-count {
  display: inline-flex;
  align-items: center;
}

.ic-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 4px;
}

.ic-trips {
  margin: 8px 0 0;
  padding-left: 18px;
  font-size: 13px;
}

.ic-actions {
  padding: 8px 14px 12px;
}
</style>
