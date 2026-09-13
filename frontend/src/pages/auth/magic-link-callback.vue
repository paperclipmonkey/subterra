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
          :icon="mdiCheckCircle" />
        
        <v-icon 
          v-else 
          size="64" 
          color="error" 
          class="mb-4"
          :icon="mdiAlertCircle" />

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
          class="mt-4"
          @click="$router.push('/')"
        >
          Return to Login
        </v-btn>
      </div>
    </v-responsive>
  </v-container>
</template>

<script setup>
import { mdiAlertCircle, mdiCheckCircle } from '@mdi/js'
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { api } from '@/plugins/api'

const router = useRouter()
const route = useRoute()
const store = useAppStore()

const loading = ref(true)
const success = ref(false)
const errorMessage = ref('')

// This page is a spinner with no way out, so a router call that throws or rejects
// would strand an already-signed-in user on it. Fall back to a hard page load.
const navigate = (target) => {
  try {
    router.push(target).catch(() => window.location.assign('/trips'))
  } catch {
    window.location.assign('/trips')
  }
}

onMounted(async () => {
  try {
    // Get the magic link token from the URL parameters
    const token = route.query.token || route.params.token
    
    if (!token) {
      throw new Error('No authentication token found')
    }

    // Make a request to verify the magic link
    const response = await api.get(`/api/auth/magic-link-callback?${new URLSearchParams(route.query)}`)
    const data = response.data
    success.value = true
    
    // Refresh user data in store
    await store.getUser(true)
    
    // Check if user needs to complete their profile
    if (data.data.needs_profile) {
      setTimeout(() => {
        navigate({ name: '/profile/[id].edit', params: { id: store.user.id } })
      }, 2000)
    } else {
      const redirect = sessionStorage.getItem('redirectAfterLogin')
      sessionStorage.removeItem('redirectAfterLogin')
      setTimeout(() => {
        navigate(redirect || '/trips')
      }, 2000)
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
