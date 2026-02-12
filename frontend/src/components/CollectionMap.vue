<template>
  <div v-if="!userStore.canSuggest" class="d-flex justify-center align-center fill-height bg-grey-lighten-4 pa-12" style="height: 600px;">
    <v-card variant="outlined" class="text-center pa-8 border-dashed rounded-xl" max-width="400">
      <v-icon size="64" color="warning" class="mb-4">mdi-shield-lock</v-icon>
      <div class="text-h6 font-weight-bold mb-2">Maps Restricted</div>
      <div class="text-body-2 text-grey-darken-1">
        Cave locations are only visible to approved users. Please wait for an club to verify your membership.
      </div>
    </v-card>
  </div>
  <div v-else style="height: 600px;">
    <CaveMap :caves="collection.caves" v-if="collection.caves && collection.caves.length > 0" />
    <div v-else class="d-flex justify-center align-center fill-height bg-grey-lighten-4">
      <div class="text-center text-grey">
        <v-icon size="48" class="mb-2">mdi-map-off</v-icon>
        <div>No locations to display</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAppStore } from '@/stores/app'
import CaveMap from '@/components/CaveMap.vue'

defineProps({
    collection: {
        type: Object,
        required: true
    }
})

const userStore = useAppStore()
</script>
