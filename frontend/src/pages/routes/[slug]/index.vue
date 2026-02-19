<template>
  <v-container>
    <v-row>
      <v-col cols="12" class="d-flex justify-space-between align-center">
        <v-btn variant="text" size="small" :to="`/caves/${routeDetail?.entrance?.id || routeDetail?.exit?.id}`" class="mb-4">
          <v-icon start>mdi-arrow-left</v-icon>
          Back to Cave
        </v-btn>
        
        <v-btn
          v-if="appStore.user"
          color="primary"
          variant="text"
          prepend-icon="mdi-pencil"
          :to="`/routes/${route.params.slug}/edit`"
          class="mr-2"
        >
          {{ appStore.user?.is_admin ? 'Edit Route' : 'Suggest Edit' }}
        </v-btn>
        <v-btn
          v-else
          color="primary"
          variant="text"
          prepend-icon="mdi-pencil"
          to="/login"
          class="mr-2"
        >
          Log in to Suggest Edit
        </v-btn>
      </v-col>
    </v-row>

    <div v-if="loading" class="d-flex justify-center align-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-row v-else-if="routeDetail">
      <v-col cols="12">
        <v-card>
          <v-img 
            v-if="routeDetail.hero_image" 
            :src="routeDetail.hero_image" 
            height="300" 
            cover 
            class="align-end"
          >
            <div class="fill-height gradient-overlay d-flex align-end">
              <div class="pa-4 w-100">
                <div v-if="routeDetail.cave_system" class="text-subtitle-1 text-white font-weight-bold opacity-80 mb-1">
                  {{ routeDetail.cave_system.name }}
                </div>
                <v-card-title class="text-white text-h4 font-weight-bold pa-0 w-100">
                  {{ routeDetail.name }}
                </v-card-title>
              </div>
            </div>
          </v-img>
          <div v-else class="pa-4">
            <div v-if="routeDetail.cave_system" class="text-subtitle-1 text-medium-emphasis font-weight-bold mb-1">
              {{ routeDetail.cave_system.name }}
            </div>
            <v-card-title class="text-h4 font-weight-bold pa-0">
              {{ routeDetail.name }}
            </v-card-title>
          </div>
            
          <v-card-subtitle class="d-flex flex-wrap gap-4 py-2">
            <v-chip v-if="routeDetail.grade" color="warning" variant="flat">
              Grade {{ routeDetail.grade }}
              <v-tooltip activator="parent" location="top">
                {{ getGradeDescription(routeDetail.grade) }}
              </v-tooltip>
            </v-chip>
            <v-chip v-if="routeDetail.duration" prepend-icon="mdi-clock-outline">
              {{ routeDetail.duration }}
            </v-chip>
          </v-card-subtitle>

          <v-card-text>
            <v-row class="mt-2">
              <v-col cols="12" md="8">
                <h3 class="text-h6 mb-2">Details</h3>
                <v-table density="compact">
                  <tbody>
                    <tr v-if="routeDetail.entrance">
                      <th class="text-left font-weight-bold" style="width: 120px">Entrance:</th>
                      <td>
                        <router-link :to="`/caves/${routeDetail.entrance.id}`" class="text-decoration-none">
                          {{ routeDetail.entrance.name }}
                        </router-link>
                      </td>
                    </tr>
                    <tr v-if="routeDetail.exit">
                      <th class="text-left font-weight-bold">Exit:</th>
                      <td>
                        <router-link :to="`/caves/${routeDetail.exit.id}`" class="text-decoration-none">
                          {{ routeDetail.exit.name }}
                        </router-link>
                      </td>
                    </tr>
                  </tbody>
                </v-table>
                <div v-if="routeDetail.description" class="mt-4 text-body-1">
                  <MarkdownRenderer :source="routeDetail.description" />
                </div>
              </v-col>

              <v-col cols="12" md="4">
                <h3 class="text-h6 mb-2">Tackle Required</h3>
                <v-table v-if="routeDetail.tackle && routeDetail.tackle.length > 0" class="mb-4">
                  <thead>
                    <tr>
                      <th>Type</th>
                      <th>Description</th>
                      <th>Details</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in routeDetail.tackle" :key="item.id">
                      <td>
                        <v-icon v-if="['rope', 'srt_rope', 'handline', 'lifeline'].includes(item.type)" size="small">mdi-rope</v-icon>
                        <v-icon v-else-if="item.type === 'ladder'" size="small">mdi-stairs</v-icon>
                        <v-icon v-else-if="item.type === 'rope_protector'" size="small">mdi-shield-half-full</v-icon>
                        <span class="text-capitalize ml-1">{{ item.type ? item.type.replace('_', ' ') : '' }}</span>
                      </td>
                      <td>{{ item.description }}</td>
                      <td>
                        <span v-if="item.length">{{ item.length }}m</span>
                        <span v-if="item.quantity > 1"> (x{{ item.quantity }})</span>
                        <v-chip v-if="item.optional" size="x-small" color="info" class="ml-2">Optional</v-chip>
                      </td>
                    </tr>
                  </tbody>
                </v-table>
                <v-alert v-else type="info" variant="text" class="mb-4">
                  No specific tackle listed.
                </v-alert>

                <div v-if="routeDetail.media && routeDetail.media.length > 0">
                  <h3 class="text-h6 mb-2">Media</h3>
                  <v-row dense>
                    <v-col v-for="media in routeDetail.media" :key="media.id" cols="12" sm="6">
                      <v-card link @click="openMedia(media)">
                        <v-img 
                          v-if="media.type !== 'pdf'"
                          :src="media.path" 
                          height="150" 
                          cover
                        />
                        <div v-else class="d-flex align-center justify-center bg-grey-lighten-3" style="height: 150px;">
                          <v-icon size="x-large" color="error">mdi-file-pdf-box</v-icon>
                        </div>
                        <v-card-subtitle v-if="media.caption" class="py-2 text-caption">{{ media.caption }}</v-card-subtitle>
                        <v-card-subtitle v-else class="py-2 text-caption">{{ media.type === 'pdf' ? 'View PDF' : 'View Image' }}</v-card-subtitle>
                      </v-card>
                    </v-col>
                  </v-row>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-alert v-else type="error">
      Failed to load route details.
    </v-alert>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()
const route = useRoute()
const loading = ref(true)
const routeDetail = ref(null)

const load = async () => {
  loading.value = true
  try {
    const response = await fetch(`/api/routes/${route.params.slug}`)
    if (response.ok) {
      routeDetail.value = await response.json()
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

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

const openMedia = (media) => {
  window.open(media.path, '_blank')
}
</script>

<style scoped>
.gradient-overlay {
  background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 60%);
}
</style>
