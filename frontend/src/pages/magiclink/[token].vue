<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="6">
        <v-card>
          <v-card-title>{{ error ? 'Sign-in problem' : 'Logging in...' }}</v-card-title>
          <v-card-text>
            <v-progress-circular v-if="!error" indeterminate color="primary" />
            <template v-else>
              <div class="text-error mb-4">{{ error }}</div>
              <v-btn color="primary" to="/">Back to login</v-btn>
            </template>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/plugins/api'
import { useAppStore } from '@/stores/app'

const route = useRoute()
const router = useRouter()
const error = ref('')

const store = useAppStore()

// Send the freshly-authenticated user somewhere useful. A real account that
// still has no name needs to complete its profile; everyone else goes to their
// intended destination (or the trips feed). Guard on a real id so an
// unauthenticated placeholder user (e.g. /api/users/me erroring) can never send
// us to /profile/undefined/edit.
const redirectAfterLogin = (user) => {
  if (user?.id && !(user.name || '').trim()) {
    router.replace({ name: '/profile/[id].edit', params: { id: user.id } })
    return
  }
  const redirect = sessionStorage.getItem('redirectAfterLogin')
  sessionStorage.removeItem('redirectAfterLogin')
  router.replace(redirect || { name: '/trips' })
}

onMounted(async () => {
  const token = route.params.token
  try {
    const { data: response } = await api.get('/api/auth/magic-link-callback', { params: { token } })
    if (response && response.user) {
      const user = await store.getUser(true)
      redirectAfterLogin(user)
      return
    }
    throw new Error('Invalid magic link or login failed.')
  } catch (e) {
    // The callback can fail even when the user is already signed in — e.g. an
    // email client prefetched the link and consumed the single-use token, or the
    // page was refreshed after a successful login. Before showing an error, check
    // whether we actually have a valid session and, if so, just continue.
    const user = await store.getUser(true)
    if (user && user.id) {
      redirectAfterLogin(user)
      return
    }
    error.value = e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Login failed.'
  }
})
</script>

<style scoped>
.fill-height {
  min-height: 100vh;
}
</style>
