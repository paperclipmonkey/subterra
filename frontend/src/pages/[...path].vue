<template>
  <v-container class="fill-height">
    <v-row justify="center" align="center">
      <v-col cols="12" sm="8" md="6" class="text-center">
        <v-icon :icon="mdiMapMarkerQuestion" size="64" color="primary" class="mb-4" />
        <h1 class="text-h4 font-weight-bold mb-2">Page not found</h1>
        <p class="text-body-1 text-medium-emphasis mb-6">
          We couldn't find that page. The link may be mistyped, or the page may have moved.
        </p>
        <v-btn color="primary" to="/" class="text-none">Go to home page</v-btn>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
// Catch-all route: without it an unknown URL rendered an empty page.
import { mdiMapMarkerQuestion } from '@mdi/js'
import { usePageTitle } from '@/composables/usePageTitle'

usePageTitle('Page not found')
</script>
