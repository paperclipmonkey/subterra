<template>
  <v-app>
    <!-- Content moves down if banner is present? v-system-bar app fixes to top -->
    <v-system-bar v-if="showActiveCalloutBanner" :color="calloutBannerColor"
                  class="text-white cursor-pointer px-4" height="40" style="cursor: pointer; z-index: 9999;"
                  window @click="router.push('/callout/active')">
      <v-icon color="white" class="mr-2">{{ calloutBannerIcon }}</v-icon>
      <span class="font-weight-bold">OPEN CALLOUT IN PROGRESS</span>
      <v-spacer />
      <span class="d-none d-sm-flex">
        EXPECTED: {{ formatTime(appStore.user.active_callout.callout_time) }}
      </span>
      <v-icon class="ml-2" :icon="mdiChevronRight" />
    </v-system-bar>

    <v-system-bar v-else-if="appStore.user.on_call" color="deep-purple-darken-2" class="text-white cursor-pointer px-4"
                  height="40" style="cursor: pointer; z-index: 9999;" window @click="router.push('/admin/callout')">
      <v-icon color="white" class="mr-2" :icon="mdiShieldCheck" />
      <span class="font-weight-bold">ON-CALL DUTY OFFICER</span>
      <v-spacer />
      <span v-if="appStore.user.on_call_until" class="d-none d-sm-flex mr-4">
        REMAINING: {{ formatRemainingTime(appStore.user.on_call_until) }}
      </span>
      <span class="font-weight-bold">
        {{ appStore.user.open_callouts_count }} OPEN CALLOUTS
      </span>
      <v-icon class="ml-2" :icon="mdiChevronRight" />
    </v-system-bar>
 
    <v-system-bar v-else-if="appStore.user.id && !appStore.canSuggest && appStore.user.onboarding_completed_at" color="warning"
                  class="text-white px-4" height="40" style="z-index: 9999;" window>
      <v-icon color="white" class="mr-2">{{ hasPendingApprovals ? mdiAccountClock : mdiAccountPlus }}</v-icon>
      <span class="font-weight-bold">{{ hasPendingApprovals ? 'MEMBERSHIP PENDING' : 'MEMBERSHIP REQUIRED' }}</span>
      <v-spacer />
      <span>{{ hasPendingApprovals ? 'Your club application is being reviewed' : 'Join a club to unlock full access' }}</span>
      <v-btn variant="text" size="small" class="ml-4" to="/waitlist" color="white" border>
        {{ hasPendingApprovals ? 'Check Status' : 'Join Club' }}
      </v-btn>
    </v-system-bar>

    <v-main>
      <router-view />
    </v-main>

    <OnboardingWizard />

    <!-- Global notification snackbar -->
    <v-snackbar v-model="notificationStore.show" :color="notificationColor" :timeout="notificationStore.timeout"
                location="top" @update:model-value="(value) => !value && notificationStore.hideNotification()">
      <v-icon :icon="notificationIcon" class="mr-2" />
      {{ notificationStore.message }}

      <template #actions>
        <v-btn :icon="mdiClose" variant="text" @click="notificationStore.hideNotification()" />
      </template>
    </v-snackbar>

    <!-- GDPR Privacy & Cookie Notice Banner -->
    <PrivacyNotice />
  </v-app>
</template>

<script setup>
import { mdiAccountClock, mdiAccountPlus, mdiAlert, mdiAlertCircle, mdiAlertOutline, mdiCheckCircle, mdiChevronRight, mdiClose, mdiInformation, mdiShieldCheck } from '@mdi/js'
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useNotificationStore } from '@/stores/notifications'
import { useAppStore } from '@/stores/app'
import PrivacyNotice from '@/components/PrivacyNotice.vue'
import OnboardingWizard from '@/components/OnboardingWizard.vue'
import moment from 'moment'

const notificationStore = useNotificationStore()
const appStore = useAppStore()
const router = useRouter()
const route = useRoute()

const showActiveCalloutBanner = computed(() => {
  return appStore.user && appStore.user.active_callout && route.path !== '/callout/active'
})

const calloutBannerColor = computed(() => {
  if (!appStore.user?.active_callout) return 'red darken-2'
  const diff = moment(appStore.user.active_callout.callout_time).diff(moment(), 'minutes')
  return diff < 60 ? 'red darken-2' : 'warning'
})

const calloutBannerIcon = computed(() => {
  if (!appStore.user?.active_callout) return mdiAlertCircle
  const diff = moment(appStore.user.active_callout.callout_time).diff(moment(), 'minutes')
  return diff < 60 ? mdiAlertCircle : mdiAlertOutline
})

const hasPendingApprovals = computed(() => {
  return appStore.user.clubs && appStore.user.clubs.some(club => club.status === 'pending')
})

const formatTime = (t) => moment(t).format('HH:mm')

const formatRemainingTime = (t) => {
  const diff = moment(t).diff(moment())
  const duration = moment.duration(diff)
  const hours = Math.floor(duration.asHours())
  const minutes = duration.minutes()
  return `${hours}h ${minutes}m`
}

const notificationColor = computed(() => {
  switch (notificationStore.type) {
    case 'success':
      return 'success'
    case 'error':
      return 'error'
    case 'warning':
      return 'warning'
    case 'info':
    default:
      return 'primary'
  }
})

const notificationIcon = computed(() => {
  switch (notificationStore.type) {
    case 'success':
      return mdiCheckCircle
    case 'error':
      return mdiAlertCircle
    case 'warning':
      return mdiAlert
    case 'info':
    default:
      return mdiInformation
  }
})
</script>
