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
                @click="simulateLoading" 
                color="primary" 
                size="large" 
                block
                :loading="isLoading"
                :disabled="isLoading"
                class="mb-2"
              >
                <template v-if="!isLoading">
                  <v-icon left>mdi-content-save</v-icon>
                  Save Trip
                </template>
                <template v-else>
                  <v-icon left class="mdi-spin">mdi-loading</v-icon>
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
                @click="showSuccessToast" 
                color="success" 
                class="mb-2 mr-2"
                size="small"
              >
                <v-icon left>mdi-check-circle</v-icon>
                Success Toast
              </v-btn>
              <v-btn 
                @click="showErrorToast" 
                color="error" 
                size="small"
              >
                <v-icon left>mdi-alert-circle</v-icon>
                Error Toast
              </v-btn>
              <p class="text-caption mt-2">Test the notification system!</p>
            </v-card>
          </v-col>
        </v-row>

        <v-card class="pa-4">
          <v-card-title class="text-h6">Complete Flow Demo</v-card-title>
          <v-btn 
            @click="simulateSuccess" 
            color="success" 
            class="mb-2 mr-2"
            :loading="isSimulatingSuccess"
            :disabled="isSimulatingSuccess"
          >
            <template v-if="!isSimulatingSuccess">
              <v-icon left>mdi-check</v-icon>
              Simulate Successful Save
            </template>
            <template v-else>
              <v-icon left class="mdi-spin">mdi-loading</v-icon>
              Saving...
            </template>
          </v-btn>
          
          <v-btn 
            @click="simulateError" 
            color="error" 
            :loading="isSimulatingError"
            :disabled="isSimulatingError"
          >
            <template v-if="!isSimulatingError">
              <v-icon left>mdi-alert</v-icon>
              Simulate Failed Save
            </template>
            <template v-else>
              <v-icon left class="mdi-spin">mdi-loading</v-icon>
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
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>