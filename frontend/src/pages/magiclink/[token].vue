<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="6">
        <v-card>
          <v-card-title>Logging in...</v-card-title>
          <v-card-text>
            <v-progress-circular indeterminate color="primary" />
            <div v-if="error" class="mt-4 text-error">{{ error }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { mande } from 'mande'
const authApi = mande('/api/auth')
import { useAppStore } from '@/stores/app'

const route = useRoute()
const router = useRouter()
const error = ref('')

onMounted(async () => {
  const token = route.params.token
  try {
    // Call backend API to handle magic link using mande
    const response = await authApi.get('magic-link-callback', { query: { token } })
    // Response contains user data and sets a cookie
    if (response && response.user) {
      await useAppStore().getUser(true)
      if (response.needs_profile) {
      router.replace({ name: '/profile' })
      } else {
      router.replace({ name: '/trips' })
      }
    } else {
      error.value = 'Invalid magic link or login failed.'
    }
  } catch (e) {
    error.value = e?.body?.message || 'Login failed.'
  }
})
</script>

<style scoped>
.fill-height {
  min-height: 100vh;
}
</style>
