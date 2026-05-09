<template>
  <v-card
    variant="flat"
    class="trip-report-card"
    :to="report.url || `/trips/${report.short_id}`"
    style="border-radius: 14px; text-decoration: none; min-width: 240px; max-width: 280px; flex-shrink: 0; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);"
  >
    <div class="report-header">
      <div class="report-meta">
        <v-icon :icon="mdiNotebookOutline" size="13" color="white" style="opacity:0.85" />
        <span v-if="report.date" class="report-date">{{ formatDate(report.date) }}</span>
      </div>
      <div class="report-title">{{ report.title }}</div>
    </div>

    <div v-if="report.description" class="pa-3">
      <p class="report-excerpt mb-0">
        {{ report.description }}
      </p>
    </div>
  </v-card>
</template>

<script setup>
import { mdiNotebookOutline } from '@mdi/js'

defineProps({
  report: {
    type: Object,
    required: true,
  },
})

function formatDate(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
  } catch {
    return iso
  }
}
</script>

<style scoped>
.trip-report-card {
  transition: box-shadow 0.15s, transform 0.15s;
  cursor: pointer;
  background: #fff;
}

.trip-report-card:hover {
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
  transform: translateY(-3px);
}

.report-header {
  padding: 12px 12px 10px;
  color: white;
  background: linear-gradient(135deg, #1565C0 0%, #1E88E5 100%);
}

.report-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}

.report-date {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.95;
  background: rgba(255,255,255,0.22);
  border-radius: 4px;
  padding: 1px 5px;
}

.report-title {
  font-size: 13px;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.01em;
  text-shadow: 0 1px 2px rgba(0,0,0,0.15);
}

.report-excerpt {
  font-size: 12px;
  line-height: 1.45;
  color: #4b5563;
  display: -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
