<template>
  <v-container>
    <v-row>
      <v-col v-if="canUsePip" cols="12" md="6">
        <v-card :to="{ path: '/pip' }" link height="150" class="d-flex align-center justify-center pip-tile">
          <div class="text-center">
            <img src="/pip.png" alt="Pip" class="pip-tile-avatar mb-2">
            <div class="text-h5">
              Pip
              <v-chip color="warning" variant="tonal" size="x-small" class="ml-1">Preview</v-chip>
            </div>
            <div class="text-body-2 text-medium-emphasis">Caving recommendations & trip planning</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card :to="{ path: '/trips/discover' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="deep-orange" class="mb-2" :icon="mdiCompass" />
            <div class="text-h5">Discover</div>
            <div class="text-body-2 text-medium-emphasis">Explore recent trip activity</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card :to="{ path: '/huts' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="primary" class="mb-2" :icon="mdiHomeMapMarker" />
            <div class="text-h5">Huts</div>
            <div class="text-body-2 text-medium-emphasis">Find club huts</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card :to="{ path: '/collections' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="secondary" class="mb-2" :icon="mdiBookmarkBoxMultipleOutline" />
            <div class="text-h5">Collections</div>
            <div class="text-body-2 text-medium-emphasis">Curated lists of caves</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card :to="{ path: '/news' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="success" class="mb-2" :icon="mdiNewspaper" />
            <div class="text-h5">News</div>
            <div class="text-body-2 text-medium-emphasis">Latest platform updates and announcements</div>
          </div>
        </v-card>
      </v-col>
      <v-col v-if="userStore.user" cols="12" md="6">
        <v-card :to="{ path: '/bookings' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="teal" class="mb-2" :icon="mdiClipboardCheck" />
            <div class="text-h5">Permits</div>
            <div class="text-body-2 text-medium-emphasis">Browse & apply for cave access permits</div>
          </div>
        </v-card>
      </v-col>
      <v-col v-if="offlineStore.isPwa" cols="12" md="6">
        <v-card :to="{ path: '/offline' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="grey-darken-1" class="mb-2" :icon="mdiCloudDownload" />
            <div class="text-h5">Offline Caves</div>
            <div class="text-body-2 text-medium-emphasis">
              {{ offlineCount > 0 ? `${offlineCount} cave(s) saved` : 'Download caves for underground use' }}
            </div>
          </div>
        </v-card>
      </v-col>
      <v-col v-if="userStore.user.is_admin" cols="12" md="6">
        <v-card :to="{ path: '/admin' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="blue-grey" class="mb-2" :icon="mdiCogs" />
            <div class="text-h5">Admin</div>
            <div class="text-body-2 text-medium-emphasis">Users, Clubs & Data</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card href="https://status.subterra.world/" target="_blank" rel="noopener" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="info" class="mb-2" :icon="mdiHeartPulse" />
            <div class="text-h5">System Status</div>
            <div class="text-body-2 text-medium-emphasis">Platform availability and monitoring</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12">
        <v-divider class="my-4" />
      </v-col>
      <v-col cols="4">
        <v-btn block variant="text" to="/pages/about" class="text-none">
          About
        </v-btn>
      </v-col>
      <v-col cols="4">
        <v-btn block variant="text" to="/pages/terms-of-service" class="text-none">
          Terms of Service
        </v-btn>
      </v-col>
      <v-col cols="4">
        <v-btn block variant="text" to="/pages/privacy-policy" class="text-none">
          Privacy Policy
        </v-btn>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { mdiBookmarkBoxMultipleOutline, mdiClipboardCheck, mdiCloudDownload, mdiCogs, mdiCompass, mdiHeartPulse, mdiHomeMapMarker, mdiNewspaper } from '@mdi/js'
import { useAppStore } from '@/stores/app'
import { useOfflineStore } from '@/stores/offline'
import { computed } from 'vue'

const userStore = useAppStore()
const offlineStore = useOfflineStore()
const offlineCount = computed(() => offlineStore.downloadedCaveCount)

// Mirror the gate on the /api/assistant/chat route — platform_admins always
// have access, and other users can be opted in via the `pip_access` role.
const canUsePip = computed(() => {
  const roles = userStore.user?.roles ?? []
  return roles.some(r => r.slug === 'platform_admin' || r.slug === 'pip_access')
})
</script>

<style scoped>
.pip-tile-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
  background: #fff;
}
</style>
