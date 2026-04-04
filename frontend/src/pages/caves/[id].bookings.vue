<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <v-btn icon variant="text" @click="$router.back()">
        <v-icon :icon="mdiArrowLeft" />
      </v-btn>
      <div class="ml-2">
        <h2 class="text-h4 font-weight-bold">{{ permit?.name || 'Booking' }}</h2>
        <p v-if="permit?.description" class="text-subtitle-1 text-grey-darken-1 mt-1">{{ permit.description }}</p>
      </div>
    </div>

    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <template v-if="permit">
      <!-- Permit Info -->
      <v-alert v-if="permit.conditions" type="info" variant="tonal" class="mb-4">
        <div class="text-subtitle-2 font-weight-bold mb-1">Conditions</div>
        <div style="white-space: pre-line;">{{ permit.conditions }}</div>
      </v-alert>

      <v-row v-if="permit.has_max_groups_per_day" class="mb-4">
        <v-col>
          <v-chip color="info" variant="tonal">
            Max {{ permit.max_groups_per_day }} group{{ permit.max_groups_per_day !== 1 ? 's' : '' }} per day
          </v-chip>
          <v-chip v-if="permit.auto_approve" color="success" variant="tonal" class="ml-2">
            Auto-approved
          </v-chip>
        </v-col>
      </v-row>

      <!-- Linked Caves -->
      <v-card v-if="permit.caves?.length" class="mb-6" variant="outlined">
        <v-card-title class="text-subtitle-1 font-weight-bold">Caves covered by this permit</v-card-title>
        <v-list density="compact">
          <v-list-item
            v-for="cave in permit.caves"
            :key="cave.id"
            :to="`/caves/${cave.slug}`"
            :title="cave.name"
          />
        </v-list>
      </v-card>

      <!-- Calendar -->
      <v-card class="mb-6">
        <v-card-title class="d-flex align-center justify-center">
          <v-btn icon variant="text" @click="prevMonth">
            <v-icon :icon="mdiChevronLeft" />
          </v-btn>
          <span class="text-h6 mx-4">{{ calendarTitle }}</span>
          <v-btn icon variant="text" @click="nextMonth">
            <v-icon :icon="mdiChevronRight" />
          </v-btn>
        </v-card-title>
        <v-card-text>
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
                'calendar-day--past': cell.current && cell.past,
                'calendar-day--full': !cell.available && cell.current && !cell.past,
                'calendar-day--clickable': cell.available && cell.current && !cell.past,
              }"
              @click="cell.available && cell.current && !cell.past ? selectDate(cell) : null"
            >
              <div class="day-number">{{ cell.day }}</div>
              <div v-if="cell.current && !cell.past && cell.bookingCount > 0" class="text-caption text-grey-darken-1">
                {{ cell.bookingCount }} booked
              </div>
              <div v-if="cell.current && !cell.past && !cell.available" class="text-caption text-error">
                Full
              </div>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Application Form -->
      <v-dialog v-model="applyDialog" max-width="600" persistent>
        <v-card>
          <v-card-title>Apply for permit — {{ formatDate(selectedDate) }}</v-card-title>
          <v-card-text>
            <v-form ref="applyFormRef" @submit.prevent="submitApplication">
              <p class="mb-4 text-body-2 text-grey-darken-2">
                You are applying for <strong>{{ permit.name }}</strong> on <strong>{{ formatDate(selectedDate) }}</strong>.
              </p>

              <v-text-field
                v-model.number="application.participants"
                label="Number of participants"
                type="number"
                min="1"
                :max="permit.has_max_participants ? permit.max_participants : undefined"
                :hint="permit.has_max_participants ? `Maximum ${permit.max_participants} participant${permit.max_participants !== 1 ? 's' : ''} per booking` : undefined"
                :persistent-hint="permit.has_max_participants"
                :rules="[
                  v => v >= 1 || 'At least 1 participant required',
                  v => !permit.has_max_participants || v <= permit.max_participants || `Maximum ${permit.max_participants} participants allowed`,
                ]"
                class="mb-2"
              />

              <v-textarea
                v-model="application.notes"
                label="Notes (optional)"
                rows="3"
                hint="Any additional information for the access officer"
                persistent-hint
                class="mb-4"
              />

              <v-alert v-if="permit.conditions" type="warning" variant="tonal" class="mb-4">
                <div class="text-subtitle-2 font-weight-bold mb-1">You must agree to the following conditions:</div>
                <div style="white-space: pre-line;" class="mb-3">{{ permit.conditions }}</div>
                <v-checkbox
                  v-model="application.conditions_accepted"
                  label="I have read and accept these conditions"
                  :rules="[v => !!v || 'You must accept the conditions']"
                  color="primary"
                />
              </v-alert>

              <v-checkbox
                v-if="!permit.conditions"
                v-model="application.conditions_accepted"
                label="I confirm the above details are correct"
                :rules="[v => !!v || 'Required']"
                color="primary"
              />
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="applyDialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="submitting" @click="submitApplication">
              Submit Application
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>

    <v-alert v-if="!loading && !permit" type="info" variant="tonal">
      This cave does not have a permit scheme associated with it.
    </v-alert>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { mdiArrowLeft, mdiChevronLeft, mdiChevronRight } from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'

defineOptions({ name: 'CaveBookings' })

const route = useRoute()
const router = useRouter()
const notificationStore = useNotificationStore()

const loading = ref(true)
const permit = ref(null)
const calendarData = ref({})
const currentMonth = ref(new Date())
const applyDialog = ref(false)
const selectedDate = ref(null)
const submitting = ref(false)
const applyFormRef = ref(null)

const application = ref({
  participants: 1,
  notes: '',
  conditions_accepted: false,
})

const caveSlug = computed(() => route.params.id)

const calendarTitle = computed(() => {
  return currentMonth.value.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
})

const calendarCells = computed(() => {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  let startOffset = (firstDay.getDay() + 6) % 7
  const cells = []

  for (let i = startOffset - 1; i >= 0; i--) {
    const d = new Date(year, month, -i)
    cells.push({ day: d.getDate(), current: false, today: false, available: false, past: true, bookingCount: 0 })
  }

  for (let i = 1; i <= lastDay.getDate(); i++) {
    const d = new Date(year, month, i)
    const dateStr = [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-')
    const dayData = calendarData.value[dateStr]
    const isPast = d < today

    cells.push({
      day: i,
      current: true,
      today: d.toDateString() === today.toDateString(),
      date: dateStr,
      past: isPast,
      bookingCount: dayData?.booking_count || 0,
      available: isPast ? false : (dayData?.available !== false),
    })
  }

  const remaining = 42 - cells.length
  for (let i = 1; i <= remaining; i++) {
    cells.push({ day: i, current: false, today: false, available: false, past: false, bookingCount: 0 })
  }

  return cells
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

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

const selectDate = (cell) => {
  selectedDate.value = cell.date
  application.value = { participants: 1, notes: '', conditions_accepted: false }
  applyDialog.value = true
}

const fetchPermit = async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/api/caves/${caveSlug.value}/permit`)
    permit.value = data.data
    if (permit.value) {
      fetchCalendar()
    }
  } catch (e) {
    // handled by interceptor
  } finally {
    loading.value = false
  }
}

const fetchCalendar = async () => {
  if (!permit.value) return
  const year = currentMonth.value.getFullYear()
  const month = String(currentMonth.value.getMonth() + 1).padStart(2, '0')
  try {
    const { data } = await api.get(`/api/permits/${permit.value.slug}/calendar`, {
      params: { month: `${year}-${month}` },
    })
    calendarData.value = data.data || {}
  } catch (e) {
    // handled by interceptor
  }
}

const submitApplication = async () => {
  if (!application.value.conditions_accepted) {
    notificationStore.showError('Please accept the conditions.')
    return
  }

  submitting.value = true
  try {
    await api.post(`/api/permits/${permit.value.slug}/bookings`, {
      date: selectedDate.value,
      participants: application.value.participants,
      notes: application.value.notes,
      conditions_accepted: true,
    })

    applyDialog.value = false
    notificationStore.showSuccess(
      permit.value.auto_approve
        ? 'Booking confirmed! Check your email for access details.'
        : 'Application submitted. You will be notified once reviewed.'
    )
    fetchCalendar()
  } catch (e) {
    if (e.response?.status === 422) {
      const errors = e.response.data
      const msg = typeof errors === 'object' ? Object.values(errors).flat().join(', ') : 'Please check the form.'
      notificationStore.showError(msg)
    }
  } finally {
    submitting.value = false
  }
}

watch(currentMonth, () => {
  fetchCalendar()
})

onMounted(() => {
  fetchPermit()
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
  min-height: 80px;
  padding: 6px;
}

.calendar-day--other {
  background: #fafafa;
  opacity: 0.4;
}

.calendar-day--past {
  opacity: 0.35;
}

.calendar-day--today {
  background: #e3f2fd;
}

.calendar-day--full {
  background: #ffebee;
}

.calendar-day--clickable {
  cursor: pointer;
  transition: background 0.15s;
}

.calendar-day--clickable:hover {
  background: #e8f5e9;
}

.day-number {
  font-weight: 600;
  font-size: 0.85rem;
}
</style>
