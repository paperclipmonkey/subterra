<template>
  <v-container>
    <v-row v-if="loading" justify="center" class="pa-10">
      <v-progress-circular indeterminate color="primary" />
    </v-row>
    <v-row v-else justify="center">
      <v-col cols="12" md="8" lg="6">
        <!-- Rescue Active Banner -->
        <v-alert v-if="callout.incident" type="error" prominent class="mb-6 elevation-5"
                 :icon="mdiAlertOctagram">
          <div class="text-h6 font-weight-bold">RESCUE IN PROGRESS</div>
          <div>
            Incident #{{ callout.incident.id }} has been opened. Emergency services may have been contacted.
            If you are safe, please click "I AM SAFE" immediately to inform the rescue team.
          </div>
        </v-alert>

        <v-card class="mb-6 elevation-10" :color="cardColor" dark>
          <v-card-text class="text-center pa-6">
            <div class="text-h6 mb-2">{{ callout.incident ? 'RESCUE ACTIVATED' : 'RESCUE WILL BE ACTIVATED IN' }}</div>
            <div class="text-h2 font-weight-black mb-2 white--text">
              {{ callout.incident ? 'OVERDUE' : timeRemaining }}
            </div>
            <div class="subtitle-1">
              {{ formatTime(callout.callout_time) }} - {{ formatDate(callout.callout_time) }}
            </div>
          </v-card-text>
        </v-card>

        <v-card class="mb-6">
          <v-card-title class="headline">
            <v-icon left color="primary" :icon="mdiMapMarker" />
            <router-link v-if="callout.cave" :to="'/caves/' + callout.cave.slug"
                         class="text-decoration-none text-primary font-weight-bold">
              {{ callout.cave.name }}
            </router-link>
            <span v-else>{{ callout.description }}</span>
          </v-card-title>
          <v-card-text>
            <v-list dense>
              <v-list-item>
                <v-list-item-title>Start Time</v-list-item-title>
                <v-list-item-subtitle>{{ formatTime(callout.created_at) }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item v-if="callout.trip_plan">
                <v-list-item-title>Trip Plan</v-list-item-title>
                <div class="text-body-2 mt-1">{{ callout.trip_plan }}</div>
              </v-list-item>
              <v-list-item v-if="callout.car_details">
                <v-list-item-title>Vehicle</v-list-item-title>
                <v-list-item-subtitle>{{ callout.car_details }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item v-if="callout.team_details">
                <v-list-item-title>Additional Team Details</v-list-item-title>
                <div class="text-body-2 mt-1">{{ callout.team_details }}</div>
              </v-list-item>
              <v-list-item v-if="callout.participants && callout.participants.length > 0">
                <v-list-item-title>The Team</v-list-item-title>
                <div class="d-flex flex-wrap gap-2 mt-1">
                  <v-chip v-for="participant in callout.participants" :key="participant.id"
                          size="small" class="mr-1 mb-1">
                    {{ participant.name }}
                  </v-chip>
                </div>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>

        <v-btn block x-large color="success" size="x-large" class="py-6 font-weight-black text-h5 mb-4"
               @click="confirmSafe = true">
          <v-icon left size="large" :icon="mdiCheckCircle" />
          I AM SAFE
        </v-btn>
      </v-col>
    </v-row>

    <!-- Confirmation Dialog -->
    <v-dialog v-model="confirmSafe" max-width="400">
      <v-card>
        <v-card-title class="headline">Verify Safety</v-card-title>
        <v-card-text>
          Are you out of the cave and safe? This will cancel the callout for all participants.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="grey darken-1" text @click="confirmSafe = false">Cancel</v-btn>
          <v-btn color="green darken-1" text @click="cancelCallout">Yes, I'm Safe</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Convert to Trip Dialog -->
    <v-dialog v-model="convertToTrip" persistent max-width="500">
      <v-card>
        <v-card-title class="headline text-center pt-6">Glad you're safe!</v-card-title>
        <v-card-text class="text-center pb-6">
          <v-icon size="64" color="green" class="mb-4" :icon="mdiPartyPopper" />
          <p class="text-h6 mb-2">We've logged a private trip report for you.</p>
          <p class="text-body-2">Would you like to edit the details or publish it for others to see?</p>
        </v-card-text>
        <v-card-actions class="pb-6 px-6">
          <v-btn color="grey" variant="text" @click="finish">Close</v-btn>
          <v-spacer />
          <v-btn color="primary" elevation="2" @click="editTrip">Edit / Publish Trip Report</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import { mdiAlertOctagram, mdiCheckCircle, mdiMapMarker, mdiPartyPopper } from '@mdi/js'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRouter, useRoute } from 'vue-router'
import { api } from '@/plugins/api'
import moment from 'moment'
import { useNotificationStore } from '@/stores/notifications'

const appStore = useAppStore()
const router = useRouter()
const route = useRoute()
const notifications = useNotificationStore()

const confirmSafe = ref(false)
const convertToTrip = ref(false)
const newTripId = ref(null)
const now = ref(moment())
const callout = ref({})
const loading = ref(true)
let timer = null

const timeRemaining = computed(() => {
  if (!callout.value.callout_time) return '--:--:--'
  const end = moment(callout.value.callout_time)
  const diff = end.diff(now.value) // ms
  if (diff <= 0) return 'OVERDUE'

  const duration = moment.duration(diff)
  const hours = Math.floor(duration.asHours())
  const mins = duration.minutes()
  const secs = duration.seconds()

  return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
})

const cardColor = computed(() => {
  if (callout.value.incident) return 'red-darken-4'
  if (!callout.value.callout_time) return 'primary'

  const end = moment(callout.value.callout_time)
  const diffMins = end.diff(now.value, 'minutes')

  if (diffMins < 30) return 'red-darken-4'
  if (diffMins < 60) return 'orange-darken-4'
  return 'primary'
})

const formatTime = (t) => moment(t).format('HH:mm')
const formatDate = (t) => moment(t).format('ddd Do MMM')

const cancelCallout = async () => {
  confirmSafe.value = false

  // Attempt to get location for cancellation snapshot
  let locationData = null
  if (navigator.geolocation) {
    try {
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 })
      })
      locationData = `${position.coords.latitude},${position.coords.longitude} (acc: ${position.coords.accuracy}m)`
    } catch (e) {
      // Location unavailable for cancellation — non-critical
    }
  }

  try {
    const response = await api.post(`/api/callouts/${callout.value.id}/cancel`, {
      location: locationData
    })
    notifications.showSuccess("Callout Cancelled")

    // Store the returned trip_id for the edit action
    newTripId.value = response.data.trip_id

    // Update user state to remove open callout if logged in
    if (appStore.user.id) {
      await appStore.getUser()
    }

    // Show convert dialog
    convertToTrip.value = true
  } catch (e) {
    notifications.showError("Failed to cancel callout: " + (e.response?.data?.message || e.message))
  }
}

const finish = () => {
  convertToTrip.value = false
  router.push('/')
}

const editTrip = () => {
  if (newTripId.value) {
    router.push(`/trips/${newTripId.value}/edit`)
  } else {
    router.push('/')
  }
}

const fetchCallout = async (id) => {
  try {
    const res = await api.get(`/api/callouts/${id}`)
    callout.value = res.data.data
  } catch (e) {
    notifications.showError("Could not load callout details.")
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const idFromUrl = route.query.id

  if (idFromUrl) {
    await fetchCallout(idFromUrl)
  } else if (appStore.user.active_callout) {
    callout.value = appStore.user.active_callout
    loading.value = false
  } else {
    // Try to fetch user just in case
    await appStore.getUser()
    if (appStore.user.active_callout) {
      callout.value = appStore.user.active_callout
      loading.value = false
    } else {
      loading.value = false
      notifications.showInfo("No active callout found.")
      router.push('/callout')
    }
  }

  timer = setInterval(() => {
    now.value = moment()
  }, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>
