<template>
  <v-card
    variant="flat"
    class="trip-report-card"
    :to="report.url || `/trips/${report.short_id}`"
  >
    <div class="tr-header">
      <div class="tr-meta">
        <v-icon :icon="mdiNotebookOutline" size="13" color="white" style="opacity:0.85" />
        <span v-if="report.date" class="tr-date">{{ formatDate(report.date) }}</span>
      </div>
      <div class="tr-title">{{ report.title }}</div>
    </div>

    <div v-if="report.description" class="tr-body">
      <p class="tr-excerpt">{{ report.description }}</p>
    </div>
  </v-card>
</template>

<script setup>
import { mdiNotebookOutline } from '@mdi/js'

defineProps({
  report: { type: Object, required: true },
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
.trip-report-card {
  border-radius: 16px;
  text-decoration: none;
  width: 280px;
  flex-shrink: 0;
  overflow: hidden;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.06);
  transition: box-shadow 0.18s, transform 0.18s;
}
.trip-report-card:hover {
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
}

.tr-header {
  padding: 12px 14px 10px;
  color: white;
  background: linear-gradient(135deg, #1565C0 0%, #1E88E5 100%);
}
.tr-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}
.tr-date {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: rgba(255,255,255,0.22);
  border-radius: 4px;
  padding: 2px 6px;
}
.tr-title {
  font-size: 13px;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.01em;
  text-shadow: 0 1px 2px rgba(0,0,0,0.15);
}

.tr-body {
  padding: 12px 14px 14px;
}
.tr-excerpt {
  font-size: 12px;
  line-height: 1.55;
  color: #4b5563;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 5;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
