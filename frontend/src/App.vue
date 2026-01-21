<template>
  <v-app>
    <!-- Content moves down if banner is present? v-system-bar app fixes to top -->
    <v-system-bar v-if="appStore.user && appStore.user.active_callout" color="red darken-2"
      class="text-white cursor-pointer px-4" height="40" @click="router.push('/callout/active')"
      style="cursor: pointer; z-index: 9999;" window>
      <v-icon color="white" class="mr-2">mdi-alert-circle</v-icon>
      <span class="font-weight-bold">OPEN CALLOUT IN PROGRESS</span>
      <v-spacer></v-spacer>
      <span class="d-none d-sm-flex">
        EXPECTED: {{ formatTime(appStore.user.active_callout.callout_time) }}
      </span>
      <v-icon class="ml-2">mdi-chevron-right</v-icon>
    </v-system-bar>

    <v-main>
      <router-view />
    </v-main>

    <!-- Global notification snackbar -->
    <v-snackbar v-model="notificationStore.show" :color="notificationColor" :timeout="notificationStore.timeout"
      location="top" @update:modelValue="(value) => !value && notificationStore.hideNotification()">
      <v-icon :icon="notificationIcon" class="mr-2"></v-icon>
      {{ notificationStore.message }}

      <template v-slot:actions>
        <v-btn icon="mdi-close" variant="text" @click="notificationStore.hideNotification()"></v-btn>
      </template>
    </v-snackbar>
  </v-app>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '@/stores/notifications'
import { useAppStore } from '@/stores/app'
import moment from 'moment'

const notificationStore = useNotificationStore()
const appStore = useAppStore()
const router = useRouter()

const formatTime = (t) => moment(t).format('HH:mm')

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
      return 'mdi-check-circle'
    case 'error':
      return 'mdi-alert-circle'
    case 'warning':
      return 'mdi-alert'
    case 'info':
    default:
      return 'mdi-information'
  }
})
</script>
