<template>
  <v-card
    variant="flat"
    class="trip-created-card"
  >
    <div class="tc-header">
      <v-icon :icon="mdiCheckCircleOutline" size="18" color="white" class="mr-2" />
      <span class="tc-header-text">Trip saved!</span>
    </div>

    <div class="tc-body">
      <div class="tc-name">{{ trip.name }}</div>
      <div v-if="trip.cave_system || trip.date" class="tc-meta">
        <span v-if="trip.cave_system">{{ trip.cave_system }}</span>
        <span v-if="trip.cave_system && trip.date" class="tc-sep">·</span>
        <span v-if="trip.date">{{ formatDate(trip.date) }}</span>
      </div>
    </div>

    <div class="tc-actions">
      <v-btn
        :href="trip.trip_url"
        variant="tonal"
        color="primary"
        size="small"
        :prepend-icon="mdiEye"
        class="tc-btn"
      >
        View trip
      </v-btn>
      <v-btn
        :href="trip.edit_url"
        variant="tonal"
        color="secondary"
        size="small"
        :prepend-icon="mdiImagePlus"
        class="tc-btn"
      >
        Add photos
      </v-btn>
    </div>
  </v-card>
</template>

<script setup>
import { mdiCheckCircleOutline, mdiEye, mdiImagePlus } from '@mdi/js'

defineProps({
  trip: { type: Object, required: true },
})

function formatDate(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('en-GB', {
      day: 'numeric', month: 'short', year: 'numeric',
    })
  } catch {
    return iso
  }
}
</script>

<style scoped>
.trip-created-card {
  border-radius: 16px;
  width: 300px;
  flex-shrink: 0;
  overflow: hidden;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.tc-header {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
  color: white;
}

.tc-header-text {
  font-size: 0.82rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.tc-body {
  padding: 10px 14px 6px;
}

.tc-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1a1a1a;
  line-height: 1.3;
  margin-bottom: 4px;
}

.tc-meta {
  font-size: 0.78rem;
  color: #666;
}

.tc-sep {
  margin: 0 5px;
}

.tc-actions {
  display: flex;
  gap: 8px;
  padding: 8px 14px 12px;
}

.tc-btn {
  font-size: 0.75rem !important;
}
</style>
