<template>
  <v-app>
    <v-main>
      <router-view />
    </v-main>
    
    <!-- Global notification snackbar -->
    <v-snackbar
      v-model="notificationStore.show"
      :color="notificationColor"
      :timeout="notificationStore.timeout"
      location="top"
      @update:modelValue="(value) => !value && notificationStore.hideNotification()"
    >
      <v-icon
        :icon="notificationIcon"
        class="mr-2"
      ></v-icon>
      {{ notificationStore.message }}
      
      <template v-slot:actions>
        <v-btn
          icon="mdi-close"
          variant="text"
          @click="notificationStore.hideNotification()"
        ></v-btn>
      </template>
    </v-snackbar>
  </v-app>
</template>

<script setup>
import { computed } from 'vue'
import { useNotificationStore } from '@/stores/notifications'

const notificationStore = useNotificationStore()

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
