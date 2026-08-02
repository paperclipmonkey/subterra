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
            <PermitCalendar
              v-model:current-month="currentMonth"
              :calendar-data="calendarData"
              :permit-info="calendarPermitInfo"
              @select="selectDate"
            />
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
import { mdiArrowLeft, mdiChevronRight, mdiCheckDecagram, mdiAccountClock, mdiCamera, mdiMapMarker, mdiInformationOutline } from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import PermitCalendar from '@/components/PermitCalendar.vue'

defineOptions({ name: 'CaveBookings' })

const route = useRoute()
const router = useRouter()
const notificationStore = useNotificationStore()

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
  notes: '',
  conditions_accepted: false,
})

const caveSlug = computed(() => route.params.id)

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
</style>
