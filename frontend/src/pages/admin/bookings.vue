<template>
  <v-container>
    <div class="d-flex align-center mb-6">
      <v-btn icon variant="text" @click="$router.back()">
        <v-icon :icon="mdiArrowLeft" />
      </v-btn>
      <h2 class="text-h4 font-weight-bold ml-2">Bookings</h2>
      <v-spacer />
      <v-btn-toggle v-model="viewMode" mandatory density="compact" class="mr-4">
        <v-btn value="list"><v-icon :icon="mdiViewList" /></v-btn>
        <v-btn value="calendar"><v-icon :icon="mdiCalendar" /></v-btn>
      </v-btn-toggle>
      <v-btn color="primary" @click="openNewBookingDialog">
        <v-icon :icon="mdiPlus" class="mr-1" />
        New Booking
      </v-btn>
    </div>

    <!-- Filters -->
    <v-row class="mb-2" align="center">
      <v-col cols="12" sm="4">
        <v-select
          v-model="filters.permit_id"
          :items="permitOptions"
          item-title="name"
          item-value="id"
          label="Permit"
          clearable
          density="compact"
          variant="outlined"
        />
      </v-col>
      <v-col cols="12" sm="4">
        <v-select
          v-model="filters.status"
          :items="statusOptions"
          label="Status"
          clearable
          density="compact"
          variant="outlined"
        />
      </v-col>
      <v-col cols="12" sm="4">
        <v-text-field
          v-model="filters.search"
          label="Search applicant..."
          density="compact"
          variant="outlined"
          clearable
        />
      </v-col>
      <v-col cols="12" class="pt-0 pb-2">
        <v-switch
          v-model="showPast"
          label="Show past bookings"
          density="compact"
          color="primary"
          hide-details
        />
      </v-col>
    </v-row>

    <!-- List View -->
    <v-data-table
      v-if="viewMode === 'list'"
      :headers="headers"
      :items="filteredBookings"
      :loading="loading"
      item-value="id"
      hover
      @click:row="(_, { item }) => openDetail(item)"
    >
      <template #item.date="{ item }">
        {{ formatDate(item.date) }}
      </template>

      <template #item.status="{ item }">
        <v-chip :color="statusColor(item.status)" size="small">
          {{ item.status }}
        </v-chip>
      </template>

      <template #item.permit="{ item }">
        <span class="text-primary text-decoration-underline" style="cursor:pointer" @click.stop="openDetail(item)">
          {{ item.permit?.name }}
        </span>
      </template>

      <template #item.applicant="{ item }">
        <span class="text-primary text-decoration-underline" style="cursor:pointer" @click.stop="openDetail(item)">
          {{ item.applicant?.name }}
        </span>
      </template>

      <template #item.actions="{ item }">
        <template v-if="item.status === 'pending'">
          <v-btn size="small" color="success" variant="text" :loading="item._approving" @click.stop="approveBooking(item)">
            <v-icon :icon="mdiCheck" />
          </v-btn>
          <v-btn size="small" color="error" variant="text" @click.stop="openRejectDialog(item)">
            <v-icon :icon="mdiClose" />
          </v-btn>
        </template>
        <v-btn size="small" variant="text" @click.stop="openDetail(item)">
          <v-icon :icon="mdiInformationOutline" />
        </v-btn>
      </template>
    </v-data-table>

    <!-- Calendar View -->
    <v-card v-if="viewMode === 'calendar'" class="pa-4">
      <div class="d-flex align-center justify-center mb-4">
        <v-btn icon variant="text" @click="prevMonth">
          <v-icon :icon="mdiChevronLeft" />
        </v-btn>
        <h3 class="text-h5 mx-4">{{ calendarTitle }}</h3>
        <v-btn icon variant="text" @click="nextMonth">
          <v-icon :icon="mdiChevronRight" />
        </v-btn>
      </div>

      <div class="calendar-grid">
        <div v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="day" class="calendar-header">
          {{ day }}
        </div>
        <div
          v-for="(cell, i) in calendarCells"
          :key="i"
          class="calendar-day"
          :class="{
            'calendar-day--other': !cell.current,
            'calendar-day--today': cell.today,
          }"
        >
          <div class="day-number">{{ cell.day }}</div>
          <div class="day-events">
            <div
              v-for="booking in cell.bookings"
              :key="booking.id"
              class="booking-event"
              :class="'booking-event--' + booking.status"
              :title="`${booking.applicant?.name} - ${booking.permit?.name}`"
              style="cursor:pointer"
              @click="openDetail(booking)"
            >
              <span class="text-caption">{{ booking.applicant?.name }} · {{ booking.permit?.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </v-card>

    <!-- Booking Detail Modal -->
    <v-dialog v-model="detailDialog" max-width="600">
      <v-card v-if="detailBooking">
        <v-card-title class="d-flex align-center justify-space-between">
          <span>Booking #{{ detailBooking.id }}</span>
          <v-chip :color="statusColor(detailBooking.status)" size="small">{{ detailBooking.status }}</v-chip>
        </v-card-title>

        <v-card-text>
          <v-list density="compact" lines="two">
            <v-list-item>
              <template #prepend><v-icon :icon="mdiCalendarBlank" class="mr-2" /></template>
              <v-list-item-title>Date</v-list-item-title>
              <v-list-item-subtitle>{{ formatDate(detailBooking.date) }}</v-list-item-subtitle>
            </v-list-item>

            <v-list-item :to="`/caves/${detailBooking.permit?.caves?.[0]?.slug}/bookings`" @click="detailDialog = false">
              <template #prepend><v-icon :icon="mdiClipboardCheck" class="mr-2" /></template>
              <v-list-item-title>Permit</v-list-item-title>
              <v-list-item-subtitle class="text-primary">{{ detailBooking.permit?.name }}</v-list-item-subtitle>
            </v-list-item>

            <v-list-item :to="`/profile/${detailBooking.applicant?.id}`" @click="detailDialog = false">
              <template #prepend><v-icon :icon="mdiAccount" class="mr-2" /></template>
              <v-list-item-title>Applicant</v-list-item-title>
              <v-list-item-subtitle class="text-primary">{{ detailBooking.applicant?.name }}</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="detailBooking.applicant_email" :href="`mailto:${detailBooking.applicant_email}`">
              <template #prepend><v-icon :icon="mdiEmailOutline" class="mr-2" /></template>
              <v-list-item-title>Email</v-list-item-title>
              <v-list-item-subtitle class="text-primary">{{ detailBooking.applicant_email }}</v-list-item-subtitle>
              <template #append>
                <v-btn :icon="mdiContentCopy" size="small" variant="text" title="Copy email" @click.prevent.stop="copyEmail(detailBooking.applicant_email)" />
              </template>
            </v-list-item>

            <v-list-item v-if="detailBooking.applicant?.clubs?.length">
              <template #prepend><v-icon :icon="mdiDomain" class="mr-2" /></template>
              <v-list-item-title>Club</v-list-item-title>
              <v-list-item-subtitle>
                <v-chip
                  v-for="club in detailBooking.applicant.clubs"
                  :key="club.slug"
                  size="x-small"
                  class="mr-1"
                >
                  {{ club.name }}
                </v-chip>
              </v-list-item-subtitle>
            </v-list-item>

            <v-list-item>
              <template #prepend><v-icon :icon="mdiAccountGroup" class="mr-2" /></template>
              <v-list-item-title>Participants</v-list-item-title>
              <v-list-item-subtitle>{{ detailBooking.participants }}</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="detailBooking.notes">
              <template #prepend><v-icon :icon="mdiNoteText" class="mr-2" /></template>
              <v-list-item-title>Notes from applicant</v-list-item-title>
              <div class="detail-long-text">{{ detailBooking.notes }}</div>
            </v-list-item>

            <v-list-item v-if="detailBooking.approved_at">
              <template #prepend><v-icon :icon="mdiCheckCircle" color="success" class="mr-2" /></template>
              <v-list-item-title>Approved</v-list-item-title>
              <v-list-item-subtitle>{{ formatDateTime(detailBooking.approved_at) }}</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="detailBooking.rejection_reason">
              <template #prepend><v-icon :icon="mdiCloseCircle" color="error" class="mr-2" /></template>
              <v-list-item-title>Rejection reason</v-list-item-title>
              <div class="detail-long-text">{{ detailBooking.rejection_reason }}</div>
            </v-list-item>

            <v-list-item v-if="detailBooking.status === 'approved' && detailBooking.booking_info">
              <template #prepend><v-icon :icon="mdiKey" color="success" class="mr-2" /></template>
              <v-list-item-title>Access Information</v-list-item-title>
              <v-list-item-subtitle>
                <MarkdownRenderer :source="detailBooking.booking_info" />
              </v-list-item-subtitle>
            </v-list-item>

            <v-list-item>
              <template #prepend><v-icon :icon="mdiClockOutline" class="mr-2" /></template>
              <v-list-item-title>Submitted</v-list-item-title>
              <v-list-item-subtitle>{{ formatDateTime(detailBooking.created_at) }}</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card-text>

        <v-divider />
        <template v-if="detailBooking.status === 'pending'">
          <v-card-actions class="flex-wrap gap-1 pa-2">
            <v-btn color="success" variant="tonal" :loading="detailBooking._approving" @click="approveBooking(detailBooking)">
              Approve
            </v-btn>
            <v-btn color="error" variant="tonal" @click="detailDialog = false; openRejectDialog(detailBooking)">
              Reject
            </v-btn>
            <v-btn color="error" variant="text" @click="openCancelConfirm(detailBooking)">Cancel booking</v-btn>
            <v-spacer />
            <v-btn v-if="detailBooking.applicant" color="primary" variant="text" @click="openMessageDialog(detailBooking)">
              <template #prepend><v-icon :icon="mdiEmail" /></template>
              Message applicant
            </v-btn>
          </v-card-actions>
        </template>
        <template v-else-if="detailBooking.status === 'approved'">
          <v-card-actions class="flex-wrap gap-1 pa-2">
            <v-btn color="error" variant="text" @click="openCancelConfirm(detailBooking)">Cancel booking</v-btn>
            <v-spacer />
            <v-btn v-if="detailBooking.applicant" color="primary" variant="text" @click="openMessageDialog(detailBooking)">
              <template #prepend><v-icon :icon="mdiEmail" /></template>
              Message applicant
            </v-btn>
            <v-btn variant="text" @click="detailDialog = false">Close</v-btn>
          </v-card-actions>
        </template>
        <template v-else>
          <v-card-actions class="flex-wrap gap-1 pa-2">
            <v-btn v-if="detailBooking.applicant" color="primary" variant="text" @click="openMessageDialog(detailBooking)">
              <template #prepend><v-icon :icon="mdiEmail" /></template>
              Message applicant
            </v-btn>
            <v-spacer />
            <v-btn variant="text" @click="detailDialog = false">Close</v-btn>
          </v-card-actions>
        </template>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="rejectDialog" max-width="500">
      <v-card>
        <v-card-title>Reject Booking</v-card-title>
        <v-card-text>
          <p class="mb-4">Rejecting booking from <strong>{{ rejectingBooking?.applicant?.name }}</strong> for {{ formatDate(rejectingBooking?.date) }}.</p>
          <v-textarea v-model="rejectionReason" label="Reason for rejection" :rules="[v => !!v || 'Required']" rows="3" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="rejectDialog = false">Cancel</v-btn>
          <v-btn color="error" :loading="rejecting" @click="rejectBooking">Reject</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- New Booking Dialog -->
    <v-dialog v-model="newBookingDialog" max-width="560" persistent>
      <v-card>
        <v-card-title>Add Manual Booking</v-card-title>
        <v-card-text>
          <v-select
            v-model="newBookingForm.permit_slug"
            :items="permits"
            item-title="name"
            item-value="slug"
            label="Permit"
            :rules="[v => !!v || 'Required']"
            class="mb-2"
          />
          <v-autocomplete
            v-model="newBookingForm.user_id"
            v-model:search="userSearch"
            :items="userSearchResults"
            :loading="userSearchLoading"
            item-title="name"
            item-value="id"
            label="Applicant (optional — leave blank if not a Subterra user)"
            clearable
            no-filter
            hide-no-data
            autocomplete="off"
            class="mb-2"
          >
            <template #item="{ item, props }">
              <v-list-item v-bind="props" :title="undefined">
                <template #prepend>
                  <v-avatar size="32" class="mr-2">
                    <v-img v-if="item.raw.photo" :src="item.raw.photo" :alt="item.raw.name" />
                    <v-icon v-else :icon="mdiAccount" />
                  </v-avatar>
                </template>
                <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                <v-list-item-subtitle v-if="item.raw.clubs?.length">
                  {{ item.raw.clubs.map(c => c.name).join(', ') }}
                </v-list-item-subtitle>
              </v-list-item>
            </template>
            <template #selection="{ item }">
              <div class="d-flex align-center ga-2">
                <v-avatar size="24">
                  <v-img v-if="item.raw.photo" :src="item.raw.photo" :alt="item.raw.name" />
                  <v-icon v-else :icon="mdiAccount" size="16" />
                </v-avatar>
                <span>{{ item.raw.name }}</span>
              </div>
            </template>
            <template #no-data>
              <v-list-item>
                <v-list-item-title class="text-medium-emphasis">
                  {{ userSearch?.length >= 2 ? 'No users found' : 'Type to search users…' }}
                </v-list-item-title>
              </v-list-item>
            </template>
          </v-autocomplete>
          <v-text-field
            v-model="newBookingForm.date"
            label="Date"
            type="date"
            :rules="[v => !!v || 'Required']"
            class="mb-2"
          />
          <v-text-field
            v-model.number="newBookingForm.participants"
            label="Participants"
            type="number"
            min="1"
            :rules="[v => v >= 1 || 'Min 1']"
            class="mb-2"
          />
          <v-textarea
            v-model="newBookingForm.notes"
            label="Notes (optional)"
            rows="2"
            class="mb-2"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="newBookingDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="newBookingLoading" @click="saveNewBooking()">Add Booking</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Message Dialog -->
    <v-dialog v-model="messageDialog" max-width="500">
      <v-card>
        <v-card-title>Message Applicant</v-card-title>
        <v-card-text>
          <p class="mb-3 text-body-2 text-medium-emphasis">
            This will send an email to <strong>{{ messageBooking?.applicant?.name }}</strong> regarding their booking on {{ formatDate(messageBooking?.date) }}.
          </p>
          <v-textarea
            v-model="messageText"
            label="Message"
            rows="5"
            :rules="[v => !!v || 'Required']"
            autofocus
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="messageDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="messageSending" @click="sendMessage">Send</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Daily Limit Warning -->
    <v-dialog v-model="overLimitDialog" max-width="420">
      <v-card>
        <v-card-title class="d-flex align-center">
          <v-icon :icon="mdiAlert" color="warning" class="mr-2" />
          Over the daily limit
        </v-card-title>
        <v-card-text>
          {{ overLimitMessage }} Go over the limit for this booking?
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="overLimitDialog = false">Cancel</v-btn>
          <v-btn color="warning" @click="proceedOverLimit">Go over limit</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Cancel Booking Confirmation -->
    <v-dialog v-model="cancelConfirmDialog" max-width="400">
      <v-card>
        <v-card-title>Cancel Booking</v-card-title>
        <v-card-text>
          Are you sure you want to cancel the booking from <strong>{{ cancellingBooking?.applicant?.name }}</strong> on {{ formatDate(cancellingBooking?.date) }}?
          The applicant will lose their cave access.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="cancelConfirmDialog = false">No, keep it</v-btn>
          <v-btn color="error" :loading="cancelling" @click="cancelBooking">Yes, cancel it</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  mdiAccount, mdiAccountGroup, mdiAlert, mdiArrowLeft, mdiCalendar, mdiCalendarBlank,
  mdiCheck, mdiCheckCircle, mdiChevronLeft, mdiChevronRight, mdiClipboardCheck,
  mdiClose, mdiCloseCircle, mdiClockOutline, mdiContentCopy, mdiDomain, mdiEmail, mdiEmailOutline, mdiInformationOutline, mdiKey,
  mdiNoteText, mdiPlus, mdiViewList,
} from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'
import { useAppStore } from '@/stores/app'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

defineOptions({ name: 'AdminBookings' })

const notificationStore = useNotificationStore()
const appStore = useAppStore()
const route = useRoute()
const router = useRouter()

const loading = ref(true)
const viewMode = ref('list')
const bookings = ref([])
const permits = ref([])
const showPast = ref(false)
const rejectDialog = ref(false)
const rejectingBooking = ref(null)
const rejectionReason = ref('')
const rejecting = ref(false)
const detailDialog = ref(false)
const detailBooking = ref(null)

// New booking
const newBookingDialog = ref(false)
const newBookingLoading = ref(false)
const newBookingForm = ref({ permit_slug: null, user_id: null, date: '', participants: 1, notes: '' })

// User search (server-side, debounced — handles 1k+ users)
const userSearch = ref('')
const userSearchResults = ref([])
const userSearchLoading = ref(false)
let userSearchTimer = null

// Message applicant
const messageDialog = ref(false)
const messageBooking = ref(null)
const messageText = ref('')
const messageSending = ref(false)

// Admin cancel
const cancelConfirmDialog = ref(false)
const cancellingBooking = ref(null)
const cancelling = ref(false)

const currentMonth = ref(new Date())

const filters = ref({
  permit_id: null,
  status: ['pending', 'approved', 'rejected', 'cancelled'].includes(route.query.status) ? route.query.status : null,
  search: '',
})

const statusOptions = ['pending', 'approved', 'rejected', 'cancelled']
const permitOptions = computed(() => permits.value)

const headers = [
  { title: 'Date', key: 'date', sortable: true },
  { title: 'Permit', key: 'permit', sortable: false },
  { title: 'Applicant', key: 'applicant', sortable: false },
  { title: 'Participants', key: 'participants', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: '', key: 'actions', sortable: false, width: 120 },
]

const today = new Date()
today.setHours(0, 0, 0, 0)

const filteredBookings = computed(() => {
  let result = bookings.value
  if (!showPast.value) {
    result = result.filter(b => new Date(b.date) >= today)
  }
  if (filters.value.permit_id) {
    result = result.filter(b => b.permit?.id === filters.value.permit_id)
  }
  if (filters.value.status) {
    result = result.filter(b => b.status === filters.value.status)
  }
  if (filters.value.search) {
    const q = filters.value.search.toLowerCase()
    result = result.filter(b => b.applicant?.name?.toLowerCase().includes(q))
  }
  return result
})

const statusColor = (status) => {
  const map = { pending: 'warning', approved: 'success', rejected: 'error', cancelled: 'grey' }
  return map[status] || 'grey'
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

const formatDateTime = (isoStr) => {
  if (!isoStr) return ''
  return new Date(isoStr).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const openDetail = (booking) => {
  detailBooking.value = booking
  detailDialog.value = true
}

// Calendar
const calendarTitle = computed(() => {
  return currentMonth.value.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
})

const prevMonth = () => {
  const d = new Date(currentMonth.value)
  d.setMonth(d.getMonth() - 1)
  currentMonth.value = d
}

const nextMonth = () => {
  const d = new Date(currentMonth.value)
  d.setMonth(d.getMonth() + 1)
  currentMonth.value = d
}

const calendarCells = computed(() => {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  let startOffset = (firstDay.getDay() + 6) % 7
  const cells = []
  // Build keys from local date parts — toISOString() shifts to UTC and lands
  // on the previous day during BST.
  const toDateStr = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-')
  const todayStr = toDateStr(new Date())

  for (let i = startOffset - 1; i >= 0; i--) {
    const d = new Date(year, month, -i)
    cells.push({ day: d.getDate(), current: false, today: false, date: d, bookings: [] })
  }

  for (let i = 1; i <= lastDay.getDate(); i++) {
    const d = new Date(year, month, i)
    const dateStr = toDateStr(d)
    const dayBookings = filteredBookings.value.filter(b => b.date === dateStr)
    cells.push({
      day: i,
      current: true,
      today: dateStr === todayStr,
      date: d,
      bookings: dayBookings,
    })
  }

  const remaining = 42 - cells.length
  for (let i = 1; i <= remaining; i++) {
    const d = new Date(year, month + 1, i)
    cells.push({ day: d.getDate(), current: false, today: false, date: d, bookings: [] })
  }

  return cells
})

const fetchBookings = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/admin/bookings')
    bookings.value = data.data
  } catch (e) {
    // handled by interceptor
  } finally {
    loading.value = false
  }
}

const fetchPermits = async () => {
  // Only platform admins see every permit's bookings; everyone else is scoped
  // to the permits they're an officer of.
  if (!appStore.user.roles?.some(r => r.slug === 'platform_admin')) {
    permits.value = appStore.user.managed_permits || []
    return
  }
  try {
    const { data } = await api.get('/api/admin/permits')
    permits.value = (data.data || []).map(p => ({ id: p.id, name: p.name, slug: p.slug }))
  } catch (e) {
    // handled by interceptor
  }
}

const searchUsers = async (query) => {
  if (!query || query.length < 2) {
    userSearchResults.value = []
    return
  }
  userSearchLoading.value = true
  try {
    const { data } = await api.get('/api/admin/users/officer-list', { params: { search: query } })
    userSearchResults.value = data || []
  } catch (e) {
    // handled by interceptor
  } finally {
    userSearchLoading.value = false
  }
}

watch(userSearch, (val) => {
  clearTimeout(userSearchTimer)
  userSearchTimer = setTimeout(() => searchUsers(val), 300)
})

// The api interceptor leaves 422s to the caller; these actions have no form to
// show them on, e.g. a full day or a booking another officer already handled.
const showActionError = (e) => {
  if (e.response?.status === 422 && e.response.data?.error) {
    notificationStore.showError(e.response.data.error)
  }
}

// A just-reviewed booking shouldn't vanish from a pending-only list; the
// officer often wants to message the applicant next.
const keepVisibleAfterReview = () => {
  if (filters.value.status === 'pending') filters.value.status = null
}

// The daily group limit is soft for officers: warn, then let them go over it.
const overLimitDialog = ref(false)
const overLimitMessage = ref('')
let overLimitProceed = null

const isOverLimit = (e) => e.response?.status === 422 && e.response.data?.over_limit

const confirmOverLimit = (e, proceed) => {
  overLimitMessage.value = e.response.data.error
  overLimitProceed = proceed
  overLimitDialog.value = true
}

const proceedOverLimit = () => {
  overLimitDialog.value = false
  overLimitProceed?.()
}

const approveBooking = async (booking, overrideLimit = false) => {
  booking._approving = true
  try {
    const { data } = await api.put(`/api/admin/bookings/${booking.id}/approve`, { override_limit: overrideLimit })
    const updated = data.data || data
    const idx = bookings.value.findIndex(b => b.id === booking.id)
    if (idx >= 0) bookings.value[idx] = updated
    if (detailBooking.value?.id === booking.id) detailBooking.value = updated
    notificationStore.showSuccess('Booking approved')
    appStore.getUser(true).catch(() => {})
    keepVisibleAfterReview()
  } catch (e) {
    if (isOverLimit(e)) confirmOverLimit(e, () => approveBooking(booking, true))
    else showActionError(e)
  } finally {
    booking._approving = false
  }
}

const openRejectDialog = (booking) => {
  rejectingBooking.value = booking
  rejectionReason.value = ''
  rejectDialog.value = true
}

const rejectBooking = async () => {
  if (!rejectionReason.value) return
  rejecting.value = true
  try {
    const { data } = await api.put(`/api/admin/bookings/${rejectingBooking.value.id}/reject`, {
      rejection_reason: rejectionReason.value,
    })
    const updated = data.data || data
    const idx = bookings.value.findIndex(b => b.id === rejectingBooking.value.id)
    if (idx >= 0) bookings.value[idx] = updated
    if (detailBooking.value?.id === rejectingBooking.value.id) detailBooking.value = updated
    notificationStore.showSuccess('Booking rejected')
    appStore.getUser(true).catch(() => {})
    keepVisibleAfterReview()
    rejectDialog.value = false
    detailDialog.value = false
  } catch (e) {
    showActionError(e)
  } finally {
    rejecting.value = false
  }
}

const openNewBookingDialog = () => {
  newBookingForm.value = { permit_slug: null, user_id: null, date: '', participants: 1, notes: '' }
  userSearch.value = ''
  userSearchResults.value = []
  newBookingDialog.value = true
}

const saveNewBooking = async (overrideLimit = false) => {
  const { permit_slug, date, participants } = newBookingForm.value
  if (!permit_slug || !date || !participants) return
  newBookingLoading.value = true
  try {
    const { data } = await api.post('/api/admin/bookings', { ...newBookingForm.value, override_limit: overrideLimit })
    bookings.value.unshift(data.data || data)
    notificationStore.showSuccess('Booking added')
    newBookingDialog.value = false
  } catch (e) {
    if (isOverLimit(e)) {
      confirmOverLimit(e, () => saveNewBooking(true))
    } else if (e.response?.status === 422) {
      notificationStore.showError(e.response.data?.error || 'Please check the form for errors.')
    }
  } finally {
    newBookingLoading.value = false
  }
}

const copyEmail = async (email) => {
  try {
    await navigator.clipboard.writeText(email)
    notificationStore.showSuccess('Email copied')
  } catch {
    notificationStore.showError('Could not copy — select the address instead.')
  }
}

const openMessageDialog = (booking) => {
  messageBooking.value = booking
  messageText.value = ''
  messageDialog.value = true
}

const sendMessage = async () => {
  if (!messageText.value) return
  messageSending.value = true
  try {
    await api.post(`/api/admin/bookings/${messageBooking.value.id}/message`, { message: messageText.value })
    notificationStore.showSuccess('Message sent')
    messageDialog.value = false
  } catch (e) {
    // handled by interceptor
  } finally {
    messageSending.value = false
  }
}

const openCancelConfirm = (booking) => {
  cancellingBooking.value = booking
  cancelConfirmDialog.value = true
}

const cancelBooking = async () => {
  cancelling.value = true
  try {
    const { data } = await api.put(`/api/admin/bookings/${cancellingBooking.value.id}/cancel`)
    const updated = data.data || data
    const idx = bookings.value.findIndex(b => b.id === cancellingBooking.value.id)
    if (idx >= 0) bookings.value[idx] = updated
    if (detailBooking.value?.id === cancellingBooking.value.id) detailBooking.value = updated
    notificationStore.showSuccess('Booking cancelled')
    cancelConfirmDialog.value = false
  } catch (e) {
    showActionError(e)
  } finally {
    cancelling.value = false
  }
}

// Emails and the pending-bookings bar link to ?booking=<id>[&action=approve|reject|message].
// Approval still takes a click in the modal: mail scanners prefetch links.
const openBookingFromQuery = () => {
  const { booking: bookingId, action } = route.query
  if (!bookingId) return

  const booking = bookings.value.find(b => b.id === bookingId)
  router.replace({ query: { ...route.query, booking: undefined, action: undefined } })

  if (!booking) {
    notificationStore.showError('That booking could not be found. It may be for a permit you no longer manage.')
    return
  }
  if (new Date(booking.date) < today) showPast.value = true

  if (action === 'reject' && booking.status === 'pending') {
    openRejectDialog(booking)
  } else if (action === 'message' && booking.applicant) {
    openMessageDialog(booking)
  } else {
    openDetail(booking)
  }
}

onMounted(async () => {
  fetchPermits()
  await fetchBookings()
  openBookingFromQuery()
})
</script>

<style scoped>
.detail-long-text {
  font-size: 0.875rem;
  opacity: var(--v-medium-emphasis-opacity);
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: #e0e0e0;
}

.calendar-header {
  background: #f5f5f5;
  padding: 8px;
  text-align: center;
  font-weight: 600;
  font-size: 0.85rem;
}

.calendar-day {
  background: white;
  min-height: 100px;
  padding: 4px;
}

.calendar-day--other {
  background: #fafafa;
  opacity: 0.5;
}

.calendar-day--today {
  background: #e3f2fd;
}

.day-number {
  font-weight: 600;
  font-size: 0.85rem;
  margin-bottom: 4px;
}

.booking-event {
  padding: 2px 6px;
  margin-bottom: 2px;
  border-radius: 4px;
  font-size: 0.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.booking-event--pending {
  background: #fff3e0;
  border-left: 3px solid #ff9800;
}

.booking-event--approved {
  background: #e8f5e9;
  border-left: 3px solid #4caf50;
}

.booking-event--rejected {
  background: #ffebee;
  border-left: 3px solid #f44336;
}

.booking-event--cancelled {
  background: #f5f5f5;
  border-left: 3px solid #9e9e9e;
}
</style>
