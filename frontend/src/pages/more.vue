<template>
  <v-container>
    <v-row>
      <v-col cols="12" md="6">
        <v-card :to="{ path: '/huts' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="primary" class="mb-2">mdi-home-map-marker</v-icon>
            <div class="text-h5">Huts</div>
            <div class="text-body-2 text-medium-emphasis">Find club huts</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card :to="{ path: '/collections' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="secondary" class="mb-2">mdi-bookmark-box-multiple-outline</v-icon>
            <div class="text-h5">Collections</div>
            <div class="text-body-2 text-medium-emphasis">Curated lists of caves</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card :to="{ path: '/news' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="success" class="mb-2">mdi-newspaper</v-icon>
            <div class="text-h5">News</div>
            <div class="text-body-2 text-medium-emphasis">Latest platform updates and announcements</div>
          </div>
        </v-card>
      </v-col>
      <v-col v-if="userStore.user.is_admin" cols="12" md="6">
        <v-card :to="{ path: '/admin' }" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="blue-grey" class="mb-2">mdi-cogs</v-icon>
            <div class="text-h5">Admin</div>
            <div class="text-body-2 text-medium-emphasis">Users, Clubs & Data</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card href="https://status.subterra.world/" target="_blank" rel="noopener" link height="150" class="d-flex align-center justify-center">
          <div class="text-center">
            <v-icon size="48" color="info" class="mb-2">mdi-heart-pulse</v-icon>
            <div class="text-h5">System Status</div>
            <div class="text-body-2 text-medium-emphasis">Platform availability and monitoring</div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12">
        <v-divider class="my-4" />
      </v-col>
      <v-col cols="6">
        <v-btn block variant="text" to="/pages/terms-of-service" class="text-none">
          Terms of Service
        </v-btn>
      </v-col>
      <v-col cols="6">
        <v-btn block variant="text" to="/pages/privacy-policy" class="text-none">
          Privacy Policy
        </v-btn>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { useAppStore } from '@/stores/app'

const userStore = useAppStore()
</script>
