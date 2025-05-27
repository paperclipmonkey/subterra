<template>
  <v-container class="fill-height" fluid>
    <v-responsive class="align-center fill-height">
      <div class="text-center">
        <v-progress-circular 
          v-if="loading" 
          indeterminate 
          size="64" 
          color="primary"
          class="mb-4"
        />
        
        <v-icon 
          v-else-if="success" 
          size="64" 
          color="success" 
          class="mb-4"
        >
          mdi-check-circle
        </v-icon>
        
        <v-icon 
          v-else 
          size="64" 
          color="error" 
          class="mb-4"
        >
          mdi-alert-circle
        </v-icon>

        <h2 v-if="loading">Authenticating...</h2>
        <h2 v-else-if="success">Welcome to Subterra!</h2>
        <h2 v-else>Authentication Failed</h2>
        
        <p v-if="loading" class="text-grey">
          Please wait while we log you in...
        </p>
        <p v-else-if="success" class="text-grey">
          You have been successfully authenticated. Redirecting...
        </p>
        <p v-else class="text-grey">
          {{ errorMessage }}
        </p>

        <v-btn 
          v-if="!loading && !success" 
          color="primary" 
          @click="$router.push('/')"
          class="mt-4"
        >
          Return to Login
        </v-btn>
      </div>
    </v-responsive>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const route = useRoute()
const store = useAppStore()

const loading = ref(true)
const success = ref(false)
const errorMessage = ref('')

onMounted(async () => {
  try {
    // Get the magic link token from the URL parameters
    const token = route.query.token || route.params.token
    
    if (!token) {
      throw new Error('No authentication token found')
    }

    // Make a request to verify the magic link
    const response = await fetch(`/api/auth/magic-link-callback?${new URLSearchParams(route.query)}`)
    
    if (response.ok) {
      const data = await response.json()
      success.value = true
      
      // Refresh user data in store
      await store.getUser()
      
      // Check if user needs to complete their profile
      if (data.data.needs_profile) {
        setTimeout(() => {
          router.push({ name: '/profile/[id].edit', params: { id: store.user.id } })
        }, 2000)
      } else {
        setTimeout(() => {
          router.push({ name: '/trips' })
        }, 2000)
      }
    } else {
      const errorData = await response.json()
      throw new Error(errorData.message || 'Authentication failed')
    }
  } catch (error) {
    console.error('Magic link authentication error:', error)
    errorMessage.value = error.message || 'An unexpected error occurred'
  } finally {
    loading.value = false
  }
})
</script>

<route lang="yaml">
meta:
  layout: login
</route>
