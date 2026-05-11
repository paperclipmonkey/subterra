<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <div class="d-flex align-center mb-4">
          <h1 class="me-3">Pip Feedback</h1>
          <v-btn-toggle v-model="filter" mandatory color="primary" density="compact" variant="outlined">
            <v-btn value="down">Flagged (👎)</v-btn>
            <v-btn value="up">Positive (👍)</v-btn>
            <v-btn value="all">All</v-btn>
          </v-btn-toggle>
          <v-spacer />
          <v-btn variant="text" :prepend-icon="mdiRefresh" :loading="loading" @click="fetchItems">Refresh</v-btn>
        </div>

        <v-alert v-if="!loading && items.length === 0" type="info" variant="tonal">
          No feedback in this category yet.
        </v-alert>

        <v-card v-for="item in items" :key="item.id" class="mb-3" variant="outlined">
          <v-card-item>
            <template #prepend>
              <v-icon
                :icon="item.rating > 0 ? mdiThumbUp : mdiThumbDown"
                :color="item.rating > 0 ? 'success' : 'error'"
                size="22"
              />
            </template>
            <v-card-title class="d-flex align-center">
              <span>{{ item.user?.name || 'Anonymous' }}</span>
              <span v-if="item.user?.email" class="text-medium-emphasis text-body-2 ms-2">
                ({{ item.user.email }})
              </span>
              <v-spacer />
              <v-chip v-if="item.reviewed" color="grey" size="x-small" variant="tonal" class="me-2">
                Reviewed
              </v-chip>
              <span class="text-body-2 text-medium-emphasis">{{ formatDate(item.created_at) }}</span>
            </v-card-title>
            <v-card-subtitle>
              {{ item.message_count }} message{{ item.message_count === 1 ? '' : 's' }}
              <span v-if="item.comment"> — “{{ item.comment }}”</span>
            </v-card-subtitle>
          </v-card-item>
          <v-card-text v-if="item.rated_reply" class="pip-reply">
            {{ truncate(item.rated_reply, 400) }}
          </v-card-text>
          <v-card-actions>
            <v-btn variant="text" size="small" @click="openItem(item)">View transcript</v-btn>
            <v-btn
              variant="text"
              size="small"
              :color="item.reviewed ? 'warning' : 'success'"
              @click="toggleReviewed(item)"
            >
              {{ item.reviewed ? 'Mark unreviewed' : 'Mark reviewed' }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="dialog" max-width="700" scrollable>
      <v-card v-if="active">
        <v-card-title class="d-flex align-center pa-4 pb-2">
          <v-icon
            :icon="active.rating > 0 ? mdiThumbUp : mdiThumbDown"
            :color="active.rating > 0 ? 'success' : 'error'"
            class="me-2"
          />
          Feedback #{{ active.id }}
          <v-spacer />
          <v-btn variant="text" :icon="mdiClose" @click="dialog = false" />
        </v-card-title>
        <v-divider />
        <v-card-text style="max-height: 60vh">
          <p v-if="active.comment" class="mb-3"><strong>Comment:</strong> {{ active.comment }}</p>
          <div v-for="(m, i) in active.transcript || []" :key="i" class="mb-3">
            <div class="text-caption text-uppercase text-medium-emphasis">{{ m.role }}</div>
            <div class="pip-msg-box">{{ m.content }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { mdiClose, mdiRefresh, mdiThumbDown, mdiThumbUp } from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'
import moment from 'moment'

const notificationStore = useNotificationStore()
const items = ref([])
const loading = ref(false)
const filter = ref('down')
const dialog = ref(false)
const active = ref(null)

async function fetchItems() {
  loading.value = true
  try {
    const response = await api.get('/api/admin/pip-feedback', { params: { rating: filter.value } })
    items.value = response.data.data || []
  } catch {
    notificationStore.showError('Failed to load Pip feedback.')
  } finally {
    loading.value = false
  }
}

async function openItem(item) {
  try {
    const response = await api.get(`/api/admin/pip-feedback/${item.id}`)
    active.value = response.data
    dialog.value = true
  } catch {
    notificationStore.showError('Failed to load transcript.')
  }
}

async function toggleReviewed(item) {
  try {
    const response = await api.put(`/api/admin/pip-feedback/${item.id}/reviewed`)
    item.reviewed = response.data.reviewed
  } catch {
    notificationStore.showError('Failed to update.')
  }
}

function formatDate(iso) {
  return iso ? moment(iso).format('DD MMM YYYY HH:mm') : ''
}

function truncate(s, n) {
  if (!s) return ''
  return s.length > n ? s.slice(0, n - 1) + '…' : s
}

watch(filter, fetchItems)
onMounted(fetchItems)
</script>

<style scoped>
.pip-reply {
  background: #f7f8fb;
  border-radius: 8px;
  padding: 10px 12px;
  white-space: pre-wrap;
  font-size: 13px;
}
.pip-msg-box {
  background: #f3f4f6;
  border-radius: 8px;
  padding: 8px 12px;
  margin-top: 4px;
  white-space: pre-wrap;
  font-size: 13px;
  line-height: 1.5;
}
</style>
