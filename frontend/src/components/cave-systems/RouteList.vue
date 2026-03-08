<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-4">
      <h3 class="text-h6">Routes</h3>
      <div class="d-flex gap-2">
        <v-btn
          v-if="appStore.user && appStore.canSuggest"
          color="primary"
          size="small"
          :variant="appStore.user?.is_admin ? 'flat' : 'text'"
          :prepend-icon="mdiPlus"
          :to="`/cave-systems/${caveSystemId}/routes/new`"
        >
          {{ appStore.user?.is_admin ? 'Add Route' : 'Suggest New Route' }}
        </v-btn>
        <v-btn
          v-else-if="appStore.user"
          color="grey"
          size="small"
          variant="text"
          disabled
          :prepend-icon="mdiPlus"
        >
          <v-tooltip activator="parent" location="top">
            {{ !appStore.canSuggest ? 'Your account must be approved' : 'You must join a club' }} to contribute
          </v-tooltip>
          Suggest New Route
        </v-btn>
        <v-btn
          v-else
          color="primary"
          size="small"
          variant="text"
          :prepend-icon="mdiPlus"
          to="/login"
        >
          Log in to Suggest Route
        </v-btn>
      </div>
    </div>

    <div v-if="routes && routes.length > 0">
      <v-card
        v-for="route in routes"
        :key="route.id"
        class="mb-4 rounded-lg overflow-hidden"
        elevation="1"
        :to="`/routes/${route.slug}`"
        link
      >
        <v-row no-gutters>
          <v-col cols="12" sm="4">
            <v-img 
              :src="route.hero_image || '/placeholder-cave.jpg'" 
              height="100%" 
              min-height="200"
              cover
              class="bg-grey-lighten-2"
            >
              <template #placeholder>
                <div class="d-flex align-center justify-center fill-height">
                  <v-icon color="grey-lighten-1" size="48" :icon="mdiMapMarkerPath" />
                </div>
              </template>
            </v-img>
          </v-col>
          <v-col cols="12" sm="8" class="d-flex flex-column">
            <v-card-item>
              <div class="d-flex justify-space-between align-start mb-1">
                <v-card-title class="text-h5 font-weight-bold pt-0 text-truncate">{{ route.name }}</v-card-title>
                <v-chip color="warning" variant="flat" size="small" class="font-weight-bold flex-shrink-0 ml-2">
                  Grade {{ route.grade || '?' }}
                  <v-tooltip v-if="route.grade" activator="parent" location="top">
                    {{ getGradeDescription(route.grade) }}
                  </v-tooltip>
                </v-chip>
              </div>
                    
              <div class="d-flex flex-wrap gap-4 text-body-2 text-medium-emphasis mb-3">
                <div v-if="route.duration" class="d-flex align-center mr-4">
                  <v-icon start size="small" color="primary" :icon="mdiClockOutline" />
                  {{ route.duration }}
                </div>
                <div v-if="route.entrance" class="d-flex align-center mr-4">
                  <v-icon start size="small" color="primary" :icon="mdiDoorOpen" />
                  In: {{ route.entrance.name }}
                </div>
                <div v-if="route.exit" class="d-flex align-center">
                  <v-icon start size="small" color="primary" :icon="mdiExitToApp" />
                  Out: {{ route.exit.name }}
                </div>
              </div>

              <div class="text-body-1 text-truncate-3-lines mb-4">
                {{ truncateDescription(route.description) }}
              </div>
            </v-card-item>

            <v-spacer />

            <v-card-actions class="px-4 pb-4 pt-0">
              <v-btn variant="text" color="primary" class="px-0" :append-icon="mdiArrowRight">
                View Details
              </v-btn>
            </v-card-actions>
          </v-col>
        </v-row>
      </v-card>
    </div>
    <v-alert v-else type="info" variant="tonal" density="compact">
      No routes defined for this system yet.
    </v-alert>
  </div>
</template>

<script setup>
import { mdiArrowRight, mdiClockOutline, mdiDoorOpen, mdiExitToApp, mdiMapMarkerPath, mdiPlus } from '@mdi/js'
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()

defineProps({
  routes: {
    type: Array,
    default: () => []
  },
  caveSystemId: {
    type: [String, Number],
    required: false,
    default: null
  }
})
const truncateDescription = (text) => {
  if (!text) return 'No description available.'
  // Strip markdown chars broadly if needed, but simple text substring is usually fine for preview
  // Simplistic markdown strip:
  const plain = text.replace(/[#*`_]/g, '')
  return plain.length > 200 ? plain.substring(0, 200) + '...' : plain
}

const getGradeDescription = (grade) => {
  const grades = {
    1: 'Easy walking, no tackle',
    2: 'Easy caving, some crawling',
    3: 'Moderate, vertical/water possible',
    4: 'Difficult, significant vertical/water',
    5: 'Severe, expert only'
  }
  return grades[grade] || 'Unknown grade'
}
</script>

<style scoped>
.text-truncate-3-lines {
  display: -webkit-box;
  line-clamp: 3;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  color: rgba(0, 0, 0, 0.7);
}
</style>
