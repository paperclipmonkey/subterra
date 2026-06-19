<template>
  <v-container>
    <div class="mb-4">
      <h2 class="text-h4 font-weight-bold">Permits</h2>
    </div>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="mine">My Bookings</v-tab>
      <v-tab value="browse">Browse Permits</v-tab>
    </v-tabs>

    <v-tabs-window v-model="tab">
      <!-- My Bookings -->
      <v-tabs-window-item value="mine">
        <v-data-table
          :headers="headers"
          :items="bookings"
          :loading="loadingBookings"
          item-value="id"
          hover
          show-expand
        >
          <template #item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>

          <template #item.permit="{ item }">
            {{ item.permit?.name }}
          </template>

          <template #item.status="{ item }">
            <v-chip :color="statusColor(item.status)" size="small">
              {{ item.status }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex ga-2">
              <v-btn
                v-if="item.status === 'pending' || item.status === 'approved'"
                size="small"
                variant="text"
                color="error"
                @click="openCancelDialog(item)"
              >
                Cancel
              </v-btn>
              <span v-else class="text-medium-emphasis text-caption align-self-center">No actions</span>
            </div>
          </template>

          <template #expanded-row="{ item, columns }">
            <tr>
              <td :colspan="columns.length">
                <div class="pa-4">
                  <div v-if="item.status === 'approved' && item.booking_info" class="mb-3">
                    <strong>Access Information:</strong>
                    <v-alert type="success" variant="tonal" class="mt-2">
                      <MarkdownRenderer :source="item.booking_info" />
                    </v-alert>
                  </div>
                  <div v-if="item.status === 'rejected' && item.rejection_reason" class="mb-3">
                    <strong>Rejection reason:</strong>
                    <v-alert type="error" variant="tonal" class="mt-2">{{ item.rejection_reason }}</v-alert>
                  </div>
                  <div v-if="item.participants_detail?.length" class="mb-3">
                    <strong>Participants (BCA):</strong>
                    <v-table density="compact" class="mt-1">
                      <tbody>
                        <tr v-for="(p, i) in item.participants_detail" :key="i">
                          <td>{{ p.name }}</td>
                          <td class="text-medium-emphasis">{{ p.bca_number }}</td>
                        </tr>
                      </tbody>
                    </v-table>
                  </div>
                  <div v-if="item.notes">
                    <strong>Your notes:</strong> {{ item.notes }}
                  </div>
                </div>
              </td>
            </tr>
          </template>

          <template #no-data>
            <div class="text-center py-8">
              <p class="text-grey-darken-1">You have no bookings yet.</p>
              <v-btn variant="tonal" class="mt-2" @click="tab = 'browse'">Browse Permits</v-btn>
            </div>
          </template>
        </v-data-table>
      </v-tabs-window-item>

      <!-- Browse Permits -->
      <v-tabs-window-item value="browse">
        <v-text-field
          v-model="permitSearch"
          placeholder="Search permits..."
          :prepend-inner-icon="mdiMagnify"
          variant="outlined"
          density="compact"
          class="mb-4"
          clearable
          hide-details
        />
        <v-progress-linear v-if="loadingPermits" indeterminate class="mb-4" />
        <v-row v-if="!loadingPermits">
          <v-col v-if="filteredPermits.length === 0" cols="12">
            <p class="text-grey-darken-1 text-center py-8">No permits are currently available.</p>
          </v-col>
          <v-col v-for="permit in filteredPermits" :key="permit.id" cols="12" md="6">
            <v-card class="rounded-lg overflow-hidden fill-height d-flex flex-column" hover>
              <router-link :to="`/permits/${permit.slug}`" class="text-decoration-none">
                <v-img
                  :src="permit.photo?.url || '/placeholder-cave.jpg'"
                  :srcset="permit.photo?.srcset || undefined"
                  height="170"
                  cover
                  class="align-end"
                  gradient="to top, rgba(0,0,0,0.85), rgba(0,0,0,0.05) 55%"
                >
                  <div class="pa-3">
                    <div class="text-h6 font-weight-bold text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                      {{ permit.name }}
                    </div>
                    <div v-if="permit.caves?.length" class="text-caption text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                      <v-icon size="x-small" :icon="mdiMapMarker" /> {{ permit.caves.map(c => c.name).join(', ') }}
                    </div>
                  </div>
                </v-img>
              </router-link>
              <v-card-text class="flex-grow-1">
                <div class="d-flex ga-2 flex-wrap mb-3">
                  <v-chip v-if="permit.auto_approve" color="success" size="small" variant="tonal">Auto-approved</v-chip>
                  <v-chip v-else color="warning" size="small" variant="tonal">Reviewed by officer</v-chip>
                  <v-chip v-if="permit.has_max_groups_per_day" size="small" variant="tonal">
                    Max {{ permit.max_groups_per_day }} group{{ permit.max_groups_per_day !== 1 ? 's' : '' }}/day
                  </v-chip>
                </div>
                <div v-if="permit.description" class="text-body-2 text-grey-darken-1 permit-desc-clamp">
                  <MarkdownRenderer :source="permit.description" />
                </div>
              </v-card-text>
              <v-divider />
              <v-card-actions>
                <v-btn color="primary" variant="tonal" :prepend-icon="mdiCalendarCheck" :to="`/caves/${permit.caves?.[0]?.slug}/bookings`">
                  Apply
                </v-btn>
                <v-btn variant="text" :to="`/permits/${permit.slug}`">Details</v-btn>
                <v-spacer />
                <v-btn
                  v-if="canEditPermit(permit)"
                  color="secondary"
                  variant="text"
                  size="small"
                  :to="`/admin/permits?edit=${permit.slug}`"
                >
                  Edit
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </v-tabs-window-item>
    </v-tabs-window>

    <!-- Cancel Confirmation Dialog -->
    <v-dialog v-model="cancelDialog" max-width="480" persistent>
      <v-card>
        <v-card-title class="text-h6">Cancel booking?</v-card-title>
        <v-card-text>
          <p class="mb-3">
            You are about to cancel your booking for
            <strong>{{ cancellingBooking?.permit?.name }}</strong>
            on <strong>{{ formatDate(cancellingBooking?.date) }}</strong>.
          </p>
          <v-alert type="warning" variant="tonal" class="mb-0">
            Without this permit you will <strong>not be permitted to access the cave</strong>.
            This action cannot be undone — you will need to submit a new application if you change your mind.
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="cancelDialog = false">Keep booking</v-btn>
          <v-btn color="error" variant="tonal" :loading="cancelling" @click="confirmCancel">Yes, cancel it</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { mdiCalendarCheck, mdiMagnify, mdiMapMarker } from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'
import { useAppStore } from '@/stores/app'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

defineOptions({ name: 'MyBookings' })

const notificationStore = useNotificationStore()
const appStore = useAppStore()
const isPlatformAdmin = computed(() => {
  return appStore.user?.roles?.some(r => r.slug === 'platform_admin')
})
const tab = ref('mine')
const loadingBookings = ref(true)
const loadingPermits = ref(true)
const bookings = ref([])
const permits = ref([])
const permitSearch = ref('')

const filteredPermits = computed(() => {
  const q = permitSearch.value?.trim().toLowerCase()
  if (!q) return permits.value
  return permits.value.filter(permit => {
    if (permit.name?.toLowerCase().includes(q)) return true
    if (permit.description?.toLowerCase().includes(q)) return true
    if (permit.caves?.some(c => c.name?.toLowerCase().includes(q))) return true
    if (permit.caves?.some(c => c.system?.name?.toLowerCase().includes(q))) return true
    return false
  })
})

const canEditPermit = (permit) => {
  if (isPlatformAdmin.value) return true
  const userId = appStore.user?.id
  return permit.officers?.some(o => o.id === userId)
}
const cancelDialog = ref(false)
const cancellingBooking = ref(null)
const cancelling = ref(false)

const headers = [
  { title: 'Reference', key: 'id' },
  { title: 'Permit', key: 'permit', sortable: false },
  { title: 'Date', key: 'date', sortable: true },
  { title: 'Participants', key: 'participants', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: '', key: 'actions', sortable: false },
  { title: '', key: 'data-table-expand' },
]

const statusColor = (status) => {
  const map = { pending: 'warning', approved: 'success', rejected: 'error', cancelled: 'grey' }
  return map[status] || 'grey'
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

const fetchBookings = async () => {
  loadingBookings.value = true
  try {
    const { data } = await api.get('/api/bookings/mine')
    bookings.value = data.data
  } catch (e) {
    // handled by interceptor
  } finally {
    loadingBookings.value = false
  }
}

const fetchPermits = async () => {
  loadingPermits.value = true
  try {
    const { data } = await api.get('/api/permits')
    permits.value = data.data
  } catch (e) {
    // handled by interceptor
  } finally {
    loadingPermits.value = false
  }
}

const openCancelDialog = (booking) => {
  cancellingBooking.value = booking
  cancelDialog.value = true
}

const confirmCancel = async () => {
  const booking = cancellingBooking.value
  cancelling.value = true
  try {
    const { data } = await api.put(`/api/bookings/${booking.id}/cancel`)
    const idx = bookings.value.findIndex(b => b.id === booking.id)
    if (idx >= 0) bookings.value[idx] = data.data || data
    notificationStore.showSuccess('Booking cancelled')
    cancelDialog.value = false
  } catch (e) {
    // handled by interceptor
  } finally {
    cancelling.value = false
  }
}

onMounted(() => {
  fetchBookings()
  fetchPermits()
})
</script>

<style scoped>
/* Keep browse-permit cards uniform by clamping the description preview. */
.permit-desc-clamp {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
