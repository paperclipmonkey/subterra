<template>
  <div>
    <v-progress-linear v-if="loading" indeterminate />

    <template v-if="permit">
      <!-- Hero -->
      <v-img
        :src="permit.photo?.url || '/placeholder-cave.jpg'"
        :srcset="permit.photo?.srcset || undefined"
        height="320"
        cover
        class="align-end"
        gradient="to top, rgba(0,0,0,0.85), rgba(0,0,0,0.1) 60%"
      >
        <div class="position-absolute top-0 left-0 pa-4 d-flex w-100" style="z-index: 1;">
          <v-btn :icon="mdiArrowLeft" variant="tonal" color="white" class="backdrop-blur" @click="$router.back()" />
        </div>
        <div v-if="permit.photo?.photographer || permit.photo?.copyright" class="photo-credit text-caption">
          <v-icon size="x-small" :icon="mdiCamera" class="mr-1" />
          <span v-if="permit.photo.photographer">{{ permit.photo.photographer }}</span>
          <span v-if="permit.photo.photographer && permit.photo.copyright"> · </span>
          <span v-if="permit.photo.copyright">{{ permit.photo.copyright }}</span>
        </div>
        <v-container class="pb-6">
          <div class="d-flex flex-wrap ga-2 mb-2">
            <v-chip v-if="permit.auto_approve" color="success" size="small" variant="flat">
              <v-icon start :icon="mdiCheckDecagram" /> Auto-approved
            </v-chip>
            <v-chip v-else color="warning" size="small" variant="flat">
              <v-icon start :icon="mdiAccountClock" /> Reviewed by officer
            </v-chip>
            <v-chip v-if="permit.has_max_groups_per_day" size="small" variant="flat" color="white" class="text-grey-darken-3">
              Max {{ permit.max_groups_per_day }} group{{ permit.max_groups_per_day !== 1 ? 's' : '' }}/day
            </v-chip>
            <v-chip v-if="permit.has_max_participants" size="small" variant="flat" color="white" class="text-grey-darken-3">
              Max {{ permit.max_participants }} per group
            </v-chip>
          </div>
          <h1 class="text-h3 text-white font-weight-bold">{{ permit.name }}</h1>
          <div v-if="permit.caves?.length" class="text-subtitle-1 text-white mt-1 d-flex align-center">
            <v-icon :icon="mdiMapMarker" size="small" class="mr-1" />
            {{ permit.caves.map(c => c.name).join(', ') }}
          </div>
        </v-container>
      </v-img>

      <v-container class="py-6">
        <v-row>
          <!-- Calendar (main) -->
          <v-col cols="12" md="8" order="2" order-md="1">
            <v-card class="rounded-lg" elevation="2">
              <v-card-title class="d-flex align-center justify-space-between py-4">
                <v-btn icon variant="text" @click="prevMonth">
                  <v-icon :icon="mdiChevronLeft" />
                </v-btn>
                <span class="text-h6">{{ calendarTitle }}</span>
                <v-btn icon variant="text" @click="nextMonth">
                  <v-icon :icon="mdiChevronRight" />
                </v-btn>
              </v-card-title>
              <v-divider />

              <v-alert
                v-if="calendarPermitInfo.has_season"
                type="info"
                variant="tonal"
                density="compact"
                class="ma-4 mb-0"
              >
                This permit is only open from
                <strong>{{ formatSeasonDate(calendarPermitInfo.season_start) }}</strong>
                to
                <strong>{{ formatSeasonDate(calendarPermitInfo.season_end) }}</strong>.
                Dates outside this season cannot be booked.
              </v-alert>

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
                      'calendar-day--out-of-season': cell.current && !cell.past && cell.outOfSeason,
                      'calendar-day--full': !cell.available && cell.current && !cell.past && !cell.outOfSeason,
                      'calendar-day--at-risk': cell.atRisk && cell.current && !cell.past && !cell.outOfSeason,
                      'calendar-day--clickable': cell.available && cell.current && !cell.past && !cell.outOfSeason,
                    }"
                    @click="cell.available && cell.current && !cell.past && !cell.outOfSeason ? selectDate(cell) : null"
                  >
                    <div class="day-number">{{ cell.day }}</div>
                    <template v-if="cell.current && !cell.past">
                      <div v-if="cell.outOfSeason" class="text-caption day-label day-label--season">
                        Out of season
                      </div>
                      <template v-else>
                        <div v-if="cell.bookingCount > 0" class="text-caption text-grey-darken-1">
                          {{ cell.bookingCount }} booked
                        </div>
                        <div v-if="cell.available && cell.pendingCount > 0" class="text-caption" :class="cell.atRisk ? 'day-label--pending' : 'text-grey'">
                          {{ cell.pendingCount }} pending
                        </div>
                        <div v-if="!cell.available" class="text-caption text-error">
                          Full
                        </div>
                      </template>
                    </template>
                  </div>
                </div>

                <div class="d-flex flex-wrap ga-4 mt-4 text-caption text-grey-darken-1">
                  <span class="d-flex align-center"><span class="legend-swatch legend-swatch--available" /> Available — click to apply</span>
                  <span class="d-flex align-center"><span class="legend-swatch legend-swatch--at-risk" /> Pending — may fill once approved</span>
                  <span class="d-flex align-center"><span class="legend-swatch legend-swatch--full" /> Full</span>
                  <span class="d-flex align-center"><span class="legend-swatch legend-swatch--today" /> Today</span>
                </div>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Info (sidebar) -->
          <v-col cols="12" md="4" order="1" order-md="2">
            <v-card v-if="permit.description" class="rounded-lg mb-4" elevation="2">
              <v-card-title class="d-flex align-center py-4 text-subtitle-1 font-weight-bold">
                <v-icon :icon="mdiInformationOutline" class="mr-2 text-primary" />
                About this permit
              </v-card-title>
              <v-divider />
              <v-card-text>
                <MarkdownRenderer :source="permit.description" />
              </v-card-text>
            </v-card>

            <v-alert v-if="permit.conditions" type="info" variant="tonal" class="rounded-lg mb-4">
              <div class="text-subtitle-2 font-weight-bold mb-1">Conditions</div>
              <MarkdownRenderer :source="permit.conditions" />
            </v-alert>

            <v-card v-if="permit.caves?.length" class="rounded-lg" variant="outlined">
              <v-card-title class="d-flex align-center py-4 text-subtitle-1 font-weight-bold">
                <v-icon :icon="mdiMapMarker" class="mr-2 text-primary" />
                Caves covered
              </v-card-title>
              <v-divider />
              <v-list density="compact" nav>
                <v-list-item
                  v-for="cave in permit.caves"
                  :key="cave.id"
                  :to="`/caves/${cave.slug}`"
                  :title="cave.name"
                  :append-icon="mdiChevronRight"
                />
              </v-list>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- Application Form -->
      <v-dialog v-model="applyDialog" max-width="600" persistent>
        <v-card>
          <v-card-title>Apply for permit — {{ formatDate(selectedDate) }}</v-card-title>
          <v-card-text>
            <v-form ref="applyFormRef" @submit.prevent="submitApplication">
              <p class="mb-4 text-body-2 text-grey-darken-2">
                You are applying for <strong>{{ permit.name }}</strong> on <strong>{{ formatDate(selectedDate) }}</strong>.
              </p>

              <v-alert v-if="selectedDateAtRisk" type="warning" variant="tonal" density="compact" class="mb-4">
                There {{ selectedDatePending === 1 ? 'is' : 'are' }} already
                <strong>{{ selectedDatePending }}</strong> pending application{{ selectedDatePending === 1 ? '' : 's' }}
                for this date. If {{ selectedDatePending === 1 ? 'it is' : 'they are' }} approved this date may become fully
                booked, so your application can still be submitted but isn't guaranteed.
              </v-alert>

              <!-- Simple headcount (permits that don't require BCA numbers) -->
              <v-text-field
                v-if="!permit.requires_bca"
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

              <!-- Named roster with BCA numbers (permits that require BCA) -->
              <div v-else class="mb-4">
                <div class="d-flex align-center mb-1">
                  <div class="text-subtitle-2 font-weight-bold">Participants &amp; BCA numbers</div>
                  <v-chip size="x-small" class="ml-2" variant="tonal">{{ application.participants_detail.length }}</v-chip>
                </div>
                <p class="text-caption text-grey-darken-1 mb-3">
                  This permit requires every participant to be a BCA member. List everyone in your group with their BCA membership number.
                </p>

                <v-card v-for="(p, i) in application.participants_detail" :key="i" variant="outlined" class="mb-2">
                  <v-card-text class="py-3">
                    <div class="d-flex align-center mb-1">
                      <span class="text-body-2 font-weight-medium">
                        {{ i === 0 ? 'You (trip leader)' : `Participant ${i + 1}` }}
                      </span>
                      <v-chip v-if="p.user_id" size="x-small" color="primary" variant="tonal" class="ml-2">Member</v-chip>
                      <v-spacer />
                      <v-btn
                        v-if="i !== 0"
                        :icon="mdiClose"
                        size="x-small"
                        variant="text"
                        density="comfortable"
                        @click="removeParticipant(i)"
                      />
                    </div>
                    <v-text-field
                      v-model="p.name"
                      label="Full name"
                      density="compact"
                      hide-details="auto"
                      :readonly="i === 0 || !!p.user_id"
                      :rules="[v => !!(v && v.trim()) || 'Name required']"
                      class="mb-2"
                    />
                    <v-text-field
                      v-if="p.bca_on_file"
                      model-value="On file ✓"
                      label="BCA number"
                      density="compact"
                      hide-details
                      readonly
                      :prepend-inner-icon="mdiCheckDecagram"
                    />
                    <v-text-field
                      v-else
                      v-model="p.bca_number"
                      label="BCA membership number"
                      density="compact"
                      hide-details="auto"
                      :rules="[
                        v => !!(v && v.trim()) || 'BCA number required',
                        v => /^[A-Za-z0-9]{3,20}$/.test(v || '') || '3–20 letters or numbers',
                      ]"
                    />
                  </v-card-text>
                </v-card>

                <v-alert
                  v-if="permit.has_max_participants && application.participants_detail.length >= permit.max_participants"
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mb-2"
                >
                  This permit allows a maximum of {{ permit.max_participants }} participants.
                </v-alert>

                <template v-else>
                  <v-autocomplete
                    v-model="memberToAdd"
                    v-model:search="memberSearch"
                    label="Add a member by name"
                    :items="memberResults"
                    item-title="name"
                    item-value="id"
                    return-object
                    :loading="memberSearching"
                    no-filter
                    clearable
                    autocomplete="off"
                    name="random_unique_participant_search_field"
                    prepend-inner-icon=""
                    hint="Search existing Subterra members — their BCA number is filled in automatically"
                    persistent-hint
                    class="mb-2"
                    @update:search="onMemberSearch"
                    @update:model-value="addMember"
                  />
                  <v-btn
                    variant="tonal"
                    size="small"
                    :prepend-icon="mdiAccountPlus"
                    class="mb-2"
                    @click="addManualParticipant"
                  >
                    Add someone not on Subterra
                  </v-btn>
                </template>
              </div>

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
                <MarkdownRenderer :source="permit.conditions" class="mb-3" />
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

    <v-container v-if="!loading && !permit">
      <v-alert type="info" variant="tonal">
        This cave does not have a permit scheme associated with it.
      </v-alert>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { mdiArrowLeft, mdiChevronLeft, mdiChevronRight, mdiCheckDecagram, mdiAccountClock, mdiAccountPlus, mdiClose, mdiCamera, mdiMapMarker, mdiInformationOutline } from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'
import { useAppStore } from '@/stores/app'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

defineOptions({ name: 'CaveBookings' })

const route = useRoute()
const router = useRouter()
const notificationStore = useNotificationStore()
const appStore = useAppStore()

const loading = ref(true)
const permit = ref(null)
const calendarData = ref({})
const calendarPermitInfo = ref({})
const currentMonth = ref(new Date())
const applyDialog = ref(false)
const selectedDate = ref(null)
const submitting = ref(false)
const applyFormRef = ref(null)

const application = ref({
  participants: 1,
  participants_detail: [],
  notes: '',
  conditions_accepted: false,
})

// Member search for the BCA participant roster
const memberToAdd = ref(null)
const memberSearch = ref('')
const memberResults = ref([])
const memberSearching = ref(false)
let memberSearchTimer = null

const caveSlug = computed(() => route.params.id)

const calendarTitle = computed(() => {
  return currentMonth.value.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
})

const isInSeason = (dateStr) => {
  const info = calendarPermitInfo.value
  if (!info.has_season || !info.season_start || !info.season_end) return true
  const md = dateStr.slice(5) // 'MM-DD'
  const start = info.season_start
  const end = info.season_end
  if (start <= end) {
    return md >= start && md <= end
  }
  // Wrap-around season (e.g. Oct–Mar)
  return md >= start || md <= end
}

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
    const inSeason = isInSeason(dateStr)
    const bookingCount = dayData?.booking_count || 0
    const pendingCount = dayData?.pending_count || 0
    const available = isPast || !inSeason ? false : (dayData?.available !== false)
    const info = calendarPermitInfo.value
    // Available now, but approved + pending applications would fill the day if
    // all pending ones are approved — likely to book out.
    const atRisk = available && info.has_max_groups_per_day && (bookingCount + pendingCount) >= info.max_groups_per_day

    cells.push({
      day: i,
      current: true,
      today: d.toDateString() === today.toDateString(),
      date: dateStr,
      past: isPast,
      outOfSeason: !inSeason,
      bookingCount,
      pendingCount,
      available,
      atRisk,
    })
  }

  const remaining = 42 - cells.length
  for (let i = 1; i <= remaining; i++) {
    cells.push({ day: i, current: false, today: false, available: false, past: false, bookingCount: 0 })
  }

  return cells
})

// Pending applications already lodged for the date being applied for.
const selectedDatePending = computed(() => {
  if (!selectedDate.value) return 0
  return calendarData.value[selectedDate.value]?.pending_count || 0
})

// True when outstanding pending applications could fill the date if approved.
const selectedDateAtRisk = computed(() => {
  const info = calendarPermitInfo.value
  const d = selectedDate.value ? calendarData.value[selectedDate.value] : null
  if (!d || !info.has_max_groups_per_day || d.available === false) return false
  return (d.booking_count || 0) + (d.pending_count || 0) >= info.max_groups_per_day
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

// Format MM-DD season boundary as "1 April"
const formatSeasonDate = (mmdd) => {
  if (!mmdd) return ''
  const [month, day] = mmdd.split('-')
  return new Date(2000, Number(month) - 1, Number(day)).toLocaleDateString('en-GB', { day: 'numeric', month: 'long' })
}

const selectDate = (cell) => {
  selectedDate.value = cell.date
  const detail = []
  if (permit.value?.requires_bca) {
    // Seed the roster with the current user as the trip leader, pre-filled from
    // their profile. If they have no BCA number on file they must type one.
    const me = appStore.user || {}
    detail.push({
      user_id: me.id || null,
      name: me.name || '',
      bca_number: me.bca_number || '',
      bca_on_file: !!me.bca_number,
    })
  }
  application.value = { participants: 1, participants_detail: detail, notes: '', conditions_accepted: false }
  memberToAdd.value = null
  memberSearch.value = ''
  memberResults.value = []
  applyDialog.value = true
}

const onMemberSearch = (query) => {
  if (memberSearchTimer) clearTimeout(memberSearchTimer)
  if (!query) return
  memberSearching.value = true
  memberSearchTimer = setTimeout(async () => {
    try {
      const { data } = await api.get(`/api/users?search=${encodeURIComponent(query)}`)
      // Exclude members already on the roster.
      const existing = application.value.participants_detail.map(p => p.user_id).filter(Boolean)
      memberResults.value = (data.data || []).filter(u => !existing.includes(u.id))
    } catch (e) {
      // handled by interceptor
    } finally {
      memberSearching.value = false
    }
  }, 300)
}

const addMember = (member) => {
  if (!member) return
  // The public search response intentionally omits other members' BCA numbers
  // (PII). The server resolves it from their profile on submit, so we only flag
  // whether one is expected to be on file.
  application.value.participants_detail.push({
    user_id: member.id,
    name: member.name,
    bca_number: '',
    // The number itself is PII so the search never returns it; has_bca tells us
    // whether the server will resolve one from their profile. If not, the leader
    // supplies it here.
    bca_on_file: !!member.has_bca,
  })
  memberToAdd.value = null
  memberSearch.value = ''
  memberResults.value = []
}

const addManualParticipant = () => {
  application.value.participants_detail.push({
    user_id: null,
    name: '',
    bca_number: '',
    bca_on_file: false,
  })
}

const removeParticipant = (index) => {
  application.value.participants_detail.splice(index, 1)
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
    calendarPermitInfo.value = data.permit || {}
  } catch (e) {
    // handled by interceptor
  }
}

const submitApplication = async () => {
  if (!application.value.conditions_accepted) {
    notificationStore.showError('Please accept the conditions.')
    return
  }

  const payload = {
    date: selectedDate.value,
    notes: application.value.notes,
    conditions_accepted: application.value.conditions_accepted,
  }

  if (permit.value.requires_bca) {
    const rows = application.value.participants_detail
    if (!rows.length) {
      notificationStore.showError('Please add at least one participant.')
      return
    }
    for (const p of rows) {
      if (!p.name || !p.name.trim()) {
        notificationStore.showError('Every participant needs a name.')
        return
      }
      if (!p.bca_on_file && !/^[A-Za-z0-9]{3,20}$/.test((p.bca_number || '').trim())) {
        notificationStore.showError(`A valid BCA number is required for ${p.name.trim()}.`)
        return
      }
    }
    payload.participants_detail = rows.map(p => ({
      user_id: p.user_id || null,
      name: p.name.trim(),
      // Leave blank for members with a number on file — the server resolves it.
      bca_number: p.bca_on_file ? '' : (p.bca_number || '').trim(),
    }))
  } else {
    payload.participants = application.value.participants
  }

  submitting.value = true
  try {
    await api.post(`/api/permits/${permit.value.slug}/bookings`, payload)

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
  // Ensure the current user is available so the BCA roster can pre-fill the leader.
  appStore.getUser()
  fetchPermit()
})

// The router reuses this component when navigating between caves' booking
// pages, so onMounted won't re-fire — refetch when the cave id changes.
watch(() => route.params.id, (id, prev) => {
  if (id && id !== prev) fetchPermit()
})
</script>

<style scoped>
.backdrop-blur {
  backdrop-filter: blur(4px);
}

.photo-credit {
  position: absolute;
  right: 8px;
  bottom: 4px;
  z-index: 1;
  color: rgba(255, 255, 255, 0.75);
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
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

.calendar-day--at-risk {
  background: #fff8e1;
}

.calendar-day--at-risk.calendar-day--clickable:hover {
  background: #ffecb3;
}

.day-label--pending {
  color: #f57f17;
  font-weight: 600;
}

.calendar-day--out-of-season {
  background: repeating-linear-gradient(
    135deg,
    #f5f5f5,
    #f5f5f5 4px,
    #eeeeee 4px,
    #eeeeee 8px
  );
  cursor: not-allowed;
}

.day-label {
  line-height: 1.2;
  margin-top: 2px;
}

.day-label--season {
  color: #9e9e9e;
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

.legend-swatch {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 3px;
  margin-right: 6px;
  border: 1px solid #e0e0e0;
}

.legend-swatch--available {
  background: #e8f5e9;
}

.legend-swatch--at-risk {
  background: #fff8e1;
}

.legend-swatch--full {
  background: #ffebee;
}

.legend-swatch--today {
  background: #e3f2fd;
}
</style>
