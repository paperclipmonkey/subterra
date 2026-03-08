<template>
  <v-container class="pa-4">
    <v-card class="pa-6">
      <v-card-title>
        <h2>Trip Saving Demo</h2>
      </v-card-title>
      <v-card-text>
        <p class="mb-4">This demo showcases the new trip saving improvements:</p>
        
        <v-row>
          <v-col cols="12" md="6">
            <v-card class="pa-4 mb-4">
              <v-card-title class="text-h6">Loading Animation</v-card-title>
              <v-btn 
                color="primary" 
                size="large" 
                block 
                :loading="isLoading"
                :disabled="isLoading"
                class="mb-2"
                @click="simulateLoading"
              >
                <template v-if="!isLoading">
                  <v-icon left :icon="mdiContentSave" />
                  Save Trip
                </template>
                <template v-else>
                  <v-icon left class="mdi-spin" :icon="mdiLoading" />
                  Saving Trip...
                </template>
              </v-btn>
              <p class="text-caption">Click to see the fun loading animation!</p>
            </v-card>
          </v-col>
          
          <v-col cols="12" md="6">
            <v-card class="pa-4 mb-4">
              <v-card-title class="text-h6">Toast Notifications</v-card-title>
              <v-btn 
                color="success" 
                class="mb-2 mr-2" 
                size="small"
                @click="showSuccessToast"
              >
                <v-icon left :icon="mdiCheckCircle" />
                Success Toast
              </v-btn>
              <v-btn 
                color="error" 
                size="small" 
                @click="showErrorToast"
              >
                <v-icon left :icon="mdiAlertCircle" />
                Error Toast
              </v-btn>
              <p class="text-caption mt-2">Test the notification system!</p>
            </v-card>
          </v-col>
        </v-row>

        <v-card class="pa-4">
          <v-card-title class="text-h6">Complete Flow Demo</v-card-title>
          <v-btn 
            color="success" 
            class="mb-2 mr-2" 
            :loading="isSimulatingSuccess"
            :disabled="isSimulatingSuccess"
            @click="simulateSuccess"
          >
            <template v-if="!isSimulatingSuccess">
              <v-icon left :icon="mdiCheck" />
              Simulate Successful Save
            </template>
            <template v-else>
              <v-icon left class="mdi-spin" :icon="mdiLoading" />
              Saving...
            </template>
          </v-btn>
          
          <v-btn 
            color="error" 
            :loading="isSimulatingError" 
            :disabled="isSimulatingError"
            @click="simulateError"
          >
            <template v-if="!isSimulatingError">
              <v-icon left :icon="mdiAlert" />
              Simulate Failed Save
            </template>
            <template v-else>
              <v-icon left class="mdi-spin" :icon="mdiLoading" />
              Saving...
            </template>
          </v-btn>
          <p class="text-caption mt-2">Experience the complete loading + notification flow!</p>
        </v-card>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script setup>
import { mdiAlert, mdiAlertCircle, mdiCheck, mdiCheckCircle, mdiContentSave, mdiLoading } from '@mdi/js'
import { ref } from 'vue'
import { useNotificationStore } from '@/stores/notifications'

const notificationStore = useNotificationStore()

const isLoading = ref(false)
const isSimulatingSuccess = ref(false)
const isSimulatingError = ref(false)

const simulateLoading = async () => {
  isLoading.value = true
  await new Promise(resolve => setTimeout(resolve, 3000))
  isLoading.value = false
}

const showSuccessToast = () => {
  notificationStore.showSuccess('Trip saved successfully! 🚀')
}

const showErrorToast = () => {
  notificationStore.showError('Failed to save trip. Please try again.')
}

const simulateSuccess = async () => {
  isSimulatingSuccess.value = true
  await new Promise(resolve => setTimeout(resolve, 2000))
  isSimulatingSuccess.value = false
  notificationStore.showSuccess('Trip saved successfully! 🎉')
}

const simulateError = async () => {
  isSimulatingError.value = true
  await new Promise(resolve => setTimeout(resolve, 2000))
  isSimulatingError.value = false
  notificationStore.showError('Server error occurred. Please try again later.')
}
</script>

<style scoped>
.mdi-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}
</style>