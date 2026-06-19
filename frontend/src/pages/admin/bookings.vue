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

            <v-list-item v-if="detailBooking.participants_detail?.length">
              <template #prepend><v-icon :icon="mdiCardAccountDetails" class="mr-2" /></template>
              <v-list-item-title>BCA roster</v-list-item-title>
              <v-list-item-subtitle>
                <div v-for="(p, i) in detailBooking.participants_detail" :key="i" class="d-flex justify-space-between">
                  <span>{{ p.name }}</span>
                  <span class="font-weight-medium ml-4">{{ p.bca_number }}</span>
                </div>
              </v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="detailBooking.notes">
              <template #prepend><v-icon :icon="mdiNoteText" class="mr-2" /></template>
              <v-list-item-title>Notes from applicant</v-list-item-title>
              <v-list-item-subtitle style="white-space: pre-line;">{{ detailBooking.notes }}</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="detailBooking.approved_at">
              <template #prepend><v-icon :icon="mdiCheckCircle" color="success" class="mr-2" /></template>
              <v-list-item-title>Approved</v-list-item-title>
              <v-list-item-subtitle>{{ formatDateTime(detailBooking.approved_at) }}</v-list-item-subtitle>
            </v-list-item>

            <v-list-item v-if="detailBooking.rejection_reason">
              <template #prepend><v-icon :icon="mdiCloseCircle" color="error" class="mr-2" /></template>
              <v-list-item-title>Rejection reason</v-list-item-title>
              <v-list-item-subtitle style="white-space: pre-line;">{{ detailBooking.rejection_reason }}</v-list-item-subtitle>
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
          <v-btn color="primary" :loading="newBookingLoading" @click="saveNewBooking">Add Booking</v-btn>
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
import {
  mdiAccount, mdiAccountGroup, mdiArrowLeft, mdiCalendar, mdiCalendarBlank,
  mdiCheck, mdiCheckCircle, mdiChevronLeft, mdiChevronRight, mdiClipboardCheck,
  mdiCardAccountDetails, mdiClose, mdiCloseCircle, mdiClockOutline, mdiDomain, mdiEmail, mdiInformationOutline, mdiKey,
  mdiNoteText, mdiPlus, mdiViewList,
} from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

defineOptions({ name: 'AdminBookings' })

const notificationStore = useNotificationStore()

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
  status: null,
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
  const todayStr = new Date().toISOString().split('T')[0]

  for (let i = startOffset - 1; i >= 0; i--) {
    const d = new Date(year, month, -i)
    cells.push({ day: d.getDate(), current: false, today: false, date: d, bookings: [] })
  }

  for (let i = 1; i <= lastDay.getDate(); i++) {
    const d = new Date(year, month, i)
    const dateStr = d.toISOString().split('T')[0]
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

const approveBooking = async (booking) => {
  booking._approving = true
  try {
    const { data } = await api.put(`/api/admin/bookings/${booking.id}/approve`)
    const updated = data.data || data
    const idx = bookings.value.findIndex(b => b.id === booking.id)
    if (idx >= 0) bookings.value[idx] = updated
    if (detailBooking.value?.id === booking.id) detailBooking.value = updated
    notificationStore.showSuccess('Booking approved')
    detailDialog.value = false
  } catch (e) {
    // handled by interceptor
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
    rejectDialog.value = false
    detailDialog.value = false
  } catch (e) {
    // handled by interceptor
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

const saveNewBooking = async () => {
  const { permit_slug, date, participants } = newBookingForm.value
  if (!permit_slug || !date || !participants) return
  newBookingLoading.value = true
  try {
    const { data } = await api.post('/api/admin/bookings', newBookingForm.value)
    bookings.value.unshift(data.data || data)
    notificationStore.showSuccess('Booking added')
    newBookingDialog.value = false
  } catch (e) {
    if (e.response?.status === 422) {
      notificationStore.showError('Please check the form for errors.')
    }
  } finally {
    newBookingLoading.value = false
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
