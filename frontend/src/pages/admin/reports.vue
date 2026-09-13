<!--
  The moderation queue.

  Reports are worked, not browsed, so the page opens on the open queue with the
  urgent ones already at the top and shows the state of each at a glance.
-->
<template>
  <v-container>
    <div class="d-flex align-center mb-2" style="gap: 12px;">
      <v-icon color="error" size="32" :icon="mdiFlagOutline" />
      <h2 class="text-h4 font-weight-bold">Reports</h2>
      <v-chip v-if="counts.urgent_open" color="error" variant="flat" size="small" class="font-weight-bold">
        {{ counts.urgent_open }} urgent
      </v-chip>
    </div>
    <p class="text-subtitle-1 text-grey-darken-1 mb-6">
      Complaints raised by members, and data-protection objections from people added by someone else.
    </p>

    <v-card variant="outlined" class="mb-6">
      <v-card-text class="d-flex flex-wrap align-center pa-4" style="gap: 16px;">
        <v-btn-toggle v-model="statusFilter" mandatory density="comfortable" color="primary" variant="outlined">
          <v-btn value="open" class="text-none">Open ({{ counts.open }})</v-btn>
          <v-btn value="actioned" class="text-none">Actioned</v-btn>
          <v-btn value="dismissed" class="text-none">Dismissed</v-btn>
          <v-btn value="all" class="text-none">All</v-btn>
        </v-btn-toggle>
        <v-select
          v-model="categoryFilter"
          :items="categoryItems"
          label="Category"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          style="max-width: 260px;"
        />
      </v-card-text>
    </v-card>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />

    <v-alert v-if="!loading && !reports.length" type="info" variant="tonal" density="comfortable">
      Nothing here. No reports match this filter.
    </v-alert>

    <v-card
      v-for="report in reports"
      :key="report.id"
      variant="outlined"
      class="mb-4 report-card"
      :class="{ 'report-card--urgent': report.is_urgent && report.status === 'open' }"
    >
      <v-card-text class="pa-4">
        <div class="d-flex flex-wrap align-center mb-3" style="gap: 8px;">
          <v-chip
            :color="categoryColour(report.category)"
            variant="flat"
            size="small"
            class="font-weight-bold"
          >
            {{ categoryLabel(report.category) }}
          </v-chip>
          <v-chip :color="statusColour(report.status)" variant="tonal" size="small">
            {{ report.status }}
          </v-chip>
          <v-spacer />
          <span class="text-caption text-medium-emphasis">
            #{{ report.id }} · {{ formatDate(report.created_at) }}
          </span>
        </div>

        <div class="mb-3">
          <div class="text-caption text-medium-emphasis">Reported</div>
          <div class="text-body-1 font-weight-medium">
            <router-link v-if="report.target.url" :to="report.target.url" class="text-decoration-none">
              {{ report.target.label }}
            </router-link>
            <span v-else>{{ report.target.label || 'Unknown' }}</span>
            <span class="text-caption text-medium-emphasis ml-2">({{ report.target.type }})</span>
          </div>
        </div>

        <div class="mb-3">
          <div class="text-caption text-medium-emphasis">Raised by</div>
          <div class="text-body-2">
            <template v-if="report.reporter && report.reporter.name">
              {{ report.reporter.name }} &lt;{{ report.reporter.email }}&gt;
            </template>
            <template v-else>
              <em>The person themselves, from the notice email</em>
            </template>
          </div>
        </div>

        <div v-if="report.details" class="details-block mb-3">
          {{ report.details }}
        </div>

        <div v-if="report.status !== 'open'" class="resolution-block">
          <div class="text-caption text-medium-emphasis">
            {{ report.status === 'actioned' ? 'Actioned' : 'Dismissed' }}
            by {{ report.handled_by || 'an admin' }} on {{ formatDate(report.handled_at) }}
          </div>
          <div v-if="report.resolution_note" class="text-body-2 mt-1">{{ report.resolution_note }}</div>
        </div>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-btn
          v-if="report.status !== 'open'"
          variant="text"
          class="text-none"
          :loading="saving === report.id"
          @click="resolve(report, 'open')"
        >
          Reopen
        </v-btn>
        <v-spacer />
        <template v-if="report.status === 'open'">
          <v-btn
            variant="text"
            class="text-none"
            :loading="saving === report.id"
            @click="openResolve(report, 'dismissed')"
          >
            Dismiss
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            class="text-none px-5"
            :loading="saving === report.id"
            @click="openResolve(report, 'actioned')"
          >
            Mark actioned
          </v-btn>
        </template>
      </v-card-actions>
    </v-card>

    <!-- Resolving asks for a note, so the queue records what was decided and why. -->
    <v-dialog v-model="resolveDialog" max-width="480">
      <v-card>
        <v-card-title class="pa-4">
          {{ pendingStatus === 'actioned' ? 'Mark as actioned' : 'Dismiss report' }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <v-textarea
            id="resolution-note"
            v-model="resolutionNote"
            label="What did you do? (optional, kept on the record)"
            variant="outlined"
            rows="3"
            hide-details
          />
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="resolveDialog = false">Cancel</v-btn>
          <v-btn
            :color="pendingStatus === 'actioned' ? 'error' : 'primary'"
            variant="flat"
            class="text-none px-5"
            @click="confirmResolve"
          >
            Confirm
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { mdiFlagOutline } from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'

const notifications = useNotificationStore()

const reports = ref([])
const counts = ref({ open: 0, urgent_open: 0 })
const loading = ref(true)
const saving = ref(null)
const statusFilter = ref('open')
const categoryFilter = ref(null)

const resolveDialog = ref(false)
const resolutionNote = ref('')
const pendingReport = ref(null)
const pendingStatus = ref(null)

const categoryLabels = {
  child_safety: 'Under 18',
  harassment: 'Harassment',
  privacy: 'Privacy',
  illegal: 'Illegal',
  inaccurate: 'Inaccurate',
  spam: 'Spam',
  other: 'Other',
  data_objection: 'Data objection',
}

const categoryItems = computed(() =>
  Object.entries(categoryLabels).map(([value, title]) => ({ value, title }))
)

const categoryLabel = value => categoryLabels[value] || value

const categoryColour = value => {
  if (value === 'child_safety' || value === 'illegal') return 'error'
  if (value === 'data_objection') return 'deep-purple'
  if (value === 'harassment' || value === 'privacy') return 'warning'
  return 'grey-darken-1'
}

const statusColour = value => {
  if (value === 'open') return 'primary'
  if (value === 'actioned') return 'success'
  return 'grey'
}

const formatDate = value => {
  if (!value) return ''
  return new Date(value).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

const load = async () => {
  loading.value = true
  try {
    const [list, countResponse] = await Promise.all([
      api.get('/api/admin/reports', {
        params: { status: statusFilter.value, category: categoryFilter.value || undefined },
      }),
      api.get('/api/admin/reports/counts'),
    ])
    reports.value = list.data.data
    counts.value = countResponse.data
  } catch (error) {
    notifications.showError('Could not load reports: ' + (error.response?.data?.message || error.message))
  } finally {
    loading.value = false
  }
}

const openResolve = (report, status) => {
  pendingReport.value = report
  pendingStatus.value = status
  resolutionNote.value = ''
  resolveDialog.value = true
}

const confirmResolve = () => {
  resolveDialog.value = false
  resolve(pendingReport.value, pendingStatus.value, resolutionNote.value)
}

const resolve = async (report, status, note = null) => {
  saving.value = report.id
  try {
    await api.put(`/api/admin/reports/${report.id}`, {
      status,
      resolution_note: note,
    })
    notifications.showSuccess(status === 'open' ? 'Report reopened.' : `Report marked ${status}.`)
    await load()
  } catch (error) {
    notifications.showError('Could not update report: ' + (error.response?.data?.message || error.message))
  } finally {
    saving.value = null
  }
}

watch([statusFilter, categoryFilter], load)
onMounted(load)
</script>

<style scoped>
.report-card--urgent {
  border-left: 4px solid rgb(var(--v-theme-error));
}

.details-block {
  background: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 4px;
  padding: 12px;
  white-space: pre-wrap;
  font-size: 0.925rem;
  line-height: 1.6;
}

.resolution-block {
  border-left: 3px solid rgba(var(--v-theme-on-surface), 0.2);
  padding-left: 12px;
}
</style>
