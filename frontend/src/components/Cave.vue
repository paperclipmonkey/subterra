<template>
  <v-container v-if="cave" class="pa-4" fluid>
    <!-- Top Navigation -->
    <v-row class="mb-2">
      <v-col cols="12" class="d-flex align-center">
        <v-btn icon="mdi-arrow-left" variant="text" class="mr-2" @click="$router.push('/caves')" />
        <v-spacer />
        <v-btn
          v-if="appStore.user && appStore.canSuggest"
          variant="text"
          color="primary"
          prepend-icon="mdi-pencil"
          class="text-none mr-2"
          size="small"
          @click="$router.push('/caves/' + route.params.id + '/edit')"
        >
          {{ appStore.user?.is_admin ? 'Edit Cave' : 'Suggest Edit' }}
        </v-btn>
        <v-btn
          v-else-if="appStore.user"
          variant="text"
          color="grey"
          disabled
          prepend-icon="mdi-pencil-off"
          class="text-none mr-2"
          size="small"
        >
          <v-tooltip activator="parent" location="top">
            {{ !appStore.canSuggest ? 'Your account must be approved' : 'You must join a club' }} to suggest edits
          </v-tooltip>
          Suggest Edit
        </v-btn>
        <v-btn
          v-else
          variant="text"
          color="primary"
          to="/login"
          prepend-icon="mdi-pencil"
          class="text-none mr-2"
          size="small"
        >
          Log in to Suggest Edit
        </v-btn>
      </v-col>
    </v-row>

    <!-- Hero Section -->
    <v-card class="mb-6 rounded-lg" elevation="2">
      <v-img :src="cave.hero_image?.url || cave.entrance_image?.url || '/placeholder-cave.jpg'" height="300" cover
             class="align-end cursor-pointer hero-img" @click="activeTab = 'media'">
        <template #placeholder>
          <div class="d-flex align-center justify-center fill-height bg-grey-lighten-2">
            <v-icon color="grey" size="64">mdi-image-off</v-icon>
          </div>
        </template>
        <div class="d-flex flex-column pa-6 text-white"
             style="background: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0));">
          <div class="d-flex justify-space-between align-end">
            <div>
              <div class="text-overline mb-1">{{ cave.system?.name || 'Unknown System' }}</div>
              <h1 class="text-h3 font-weight-bold mb-2">{{ cave.name }}</h1>
              <div class="d-flex align-center">
                <v-icon size="small" class="mr-1">mdi-map-marker</v-icon>
                <span class="text-subtitle-1">{{ cave.location_name }}, {{ cave.location_country }}</span>
              </div>
            </div>
            <div v-if="cave.hero_image?.photographer" class="text-caption text-right opacity-70">
              <v-icon size="x-small" class="mr-1">mdi-camera</v-icon>
              {{ cave.hero_image.photographer }}
            </div>
          </div>
        </div>
      </v-img>
    </v-card>

    <v-row>
      <!-- Main Column -->
      <v-col cols="12" md="8">
        <v-card class="fill-height rounded-lg" elevation="1">
          <v-tabs v-model="activeTab" color="primary">
            <v-tab value="overview">Overview</v-tab>
            <v-tab value="trips">Trips <v-badge v-if="visibleTripsCount > 0" :content="visibleTripsCount" inline
                                                color="grey-lighten-1" /></v-tab>
            <v-tab value="weather">Weather</v-tab>
            <v-tab value="system">System Info</v-tab>
            <v-tab v-if="smAndDown" value="map">Map</v-tab>
            <v-tab value="media">Media</v-tab>
            <v-tab value="routes">Routes <v-badge v-if="cave.system?.routes?.length > 0" :content="cave.system.routes.length" inline color="grey-lighten-1" /></v-tab>
            <v-tab v-if="appStore.user.is_admin || (linkedCollections && linkedCollections.length > 0)" value="collections">Collections</v-tab>
          </v-tabs>
          <v-divider />

          <v-window v-model="activeTab" class="pa-4">
            <!-- Overview Tab -->
            <v-window-item value="overview">
              <div class="text-h6 mb-3 font-weight-bold display-1">Description</div>
              <vue-markdown :source="cave.description || '_No description provided._'" class="mb-6 text-body-1" />

              <v-divider class="mb-6" />

              <div class="text-h6 mb-3 font-weight-bold">Access Information</div>
              <div v-if="!appStore.canSuggest">
                <v-alert icon="mdi-lock" border="start" border-color="grey" elevation="0" color="grey-lighten-3"
                         class="mb-4">
                  <v-icon color="grey-darken-2" size="40" class="mr-4">mdi-lock</v-icon>
                  <div>
                    <div class="text-body-2 text-grey-darken-2">
                      Access information is restricted to approved club members.
                      <router-link :to="`/profile/${appStore.user.id}`" class="text-decoration-none font-weight-bold">Join a club</router-link> to view details.
                    </div>
                  </div>
                </v-alert>
              </div>
              <div v-else-if="cave.access_info">
                <v-alert icon="mdi-lock-alert" border="start" border-color="warning" elevation="0" color="warning"
                         variant="tonal" class="mb-4">
                  <vue-markdown :source="cave.access_info" />
                </v-alert>
              </div>
              <p v-else class="text-grey text-body-2">No specific access information provided.</p>
            </v-window-item>

            <!-- Trips Tab -->
            <v-window-item value="trips">
              <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between mb-4">
                <h3 class="text-h6 mb-2 mb-sm-0">Recent Trips</h3>
                <div class="d-flex align-center flex-wrap" style="gap: 8px;">
                  <v-btn v-if="!hasDone" variant="text" color="primary" prepend-icon="mdi-check" @click="markAsDone">
                    Mark Visited
                  </v-btn>
                  <v-btn color="primary" prepend-icon="mdi-plus"
                         @click="$router.push({ name: '/create-trip', query: { cave_id: cave.id } })">
                    Log Trip
                  </v-btn>
                </div>
              </div>

              <div v-if="cave.trips && cave.trips.length > 0" class="mt-2">
                <template v-for="(trip, index) in cave.trips" :key="trip.datetime || index">
                  <CaveTripListItem v-if="trip.end_time || trip.participants.some(participant => participant.id === appStore.user.id)"
                                    :trip="trip" />
                </template>
              </div>
              <v-alert v-else type="info" variant="tonal" class="mt-2">
                No trips have been recorded for this cave yet. Be the first!
              </v-alert>

              <v-dialog v-model="showConfirmModal" max-width="400">
                <v-card>
                  <v-card-title class="text-h6 pa-4">Mark Cave as Done?</v-card-title>
                  <v-card-text class="pt-0 pb-4">
                    Are you sure you want to mark <strong>{{ cave.name }}</strong> as visited?
                  </v-card-text>
                  <v-card-actions class="pa-4 pt-0">
                    <v-spacer />
                    <v-btn variant="text" @click="showConfirmModal = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" @click="confirmMarkAsDone">Confirm</v-btn>
                  </v-card-actions>
                </v-card>
              </v-dialog>
            </v-window-item>

            <!-- Weather Tab -->
            <v-window-item value="weather">
              <CaveWeather :cave-id="cave.slug || cave.id" />
            </v-window-item>

            <!-- Map Tab (Mobile only) -->
            <v-window-item v-if="smAndDown" value="map">
              <v-card class="mb-4 rounded-lg" elevation="0" variant="flat">
                <template v-if="appStore.canSuggest">
                  <mgl-map :map-style="style" :center="lnglat" :zoom="zoom" :max-zoom="15" height="400px">
                    <mgl-marker :coordinates="lnglat" color="#cc0000" />
                    <mgl-navigation-control />
                    <mgl-fullscreen-control />
                  </mgl-map>
                </template>
                <div v-else class="d-flex align-center justify-center bg-grey-lighten-3 rounded-t-lg" style="height: 300px;">
                  <div class="text-center pa-4">
                    <v-icon size="48" color="grey" class="mb-2">mdi-map-lock</v-icon>
                    <div class="text-h6 text-grey-darken-1">Location Locked</div>
                    <div class="text-caption text-grey-darken-1">Join a club to view cave locations and maps</div>
                  </div>
                </div>
                <v-card-text>
                  <div class="d-flex justify-space-between align-center">
                    <div>
                      <div class="text-caption text-grey">Coordinates</div>
                      <div v-if="appStore.canSuggest && cave.location_lat" class="font-weight-medium text-body-2">{{ cave.location_lat.toFixed(5) }}, {{
                        cave.location_lng.toFixed(5) }}</div>
                      <div v-else class="font-weight-medium text-body-2 text-grey">Hidden</div>
                    </div>
                    <div v-if="appStore.canSuggest" class="d-flex">
                      <v-tooltip text="Copy Coordinates" location="top">
                        <template #activator="{ props }">
                          <v-btn icon="mdi-content-copy" size="small" variant="text" v-bind="props"
                                 @click="copyLatLng" />
                        </template>
                      </v-tooltip>
                      <v-tooltip text="Open in Google Maps" location="top">
                        <template #activator="{ props }">
                          <v-btn icon="mdi-google-maps" size="small" variant="text" v-bind="props"
                                 :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`"
                                 target="_blank" />
                        </template>
                      </v-tooltip>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-window-item>

            <!-- System Tab -->
            <v-window-item value="system">
              <template v-if="cave.system">
                <div class="d-flex align-center justify-space-between mb-2">
                  <h3 class="text-h6">{{ cave.system.name }}</h3>
                  <div v-if="appStore.user.is_admin || (appStore.user.roles && appStore.user.roles.some(r => r.slug === 'data_admin'))">
                    <v-btn
                      size="small"
                      variant="text"
                      color="primary"
                      prepend-icon="mdi-plus"
                      class="mr-2"
                      :to="`/caves/create?system_id=${cave.system.id}`"
                    >
                      Add Cave
                    </v-btn>
                    <v-btn size="small" variant="text" icon="mdi-pencil"
                           @click="$router.push('/cave-systems/' + cave.system.id + '/edit')" />
                  </div>
                </div>

                <div v-if="cave.system.catchment_name" class="mb-4">
                  <div class="text-subtitle-2 text-grey mb-1">Catchment</div>
                  <v-btn
                    variant="tonal"
                    color="primary"
                    size="small"
                    :to="{ path: '/caves', query: { catchment: cave.system.catchment_id } }"
                    prepend-icon="mdi-water"
                  >
                    {{ cave.system.catchment_name }}
                  </v-btn>
                </div>

                <vue-markdown v-if="cave.system.description" :source="cave.system.description"
                              class="text-body-1 mb-4" />

                <v-chip-group class="mb-4">
                  <v-chip v-for="tag in cave.system.tags" :key="tag.tag" size="small" variant="outlined" disabled>{{ tag.tag
                  }}</v-chip>
                </v-chip-group>

                <v-divider class="mb-4" />

                <!-- References -->
                <div v-if="appStore.canSuggest && cave.system.references" class="mb-6">
                  <div class="text-subtitle-1 font-weight-bold mb-2">References</div>
                  <v-card variant="tonal" class="pa-4 bg-grey-lighten-5">
                    <vue-markdown :source="cave.system.references" class="text-body-2" />
                  </v-card>
                </div>

                <!-- Files -->
                <div v-if="appStore.canSuggest && cave.system.files && cave.system.files.length > 0" class="mb-6">
                  <div class="text-subtitle-1 font-weight-bold mb-2">Surveys & Documents</div>
                  <v-row dense>
                    <v-col v-for="file in cave.system.files" :key="file.id" cols="12" sm="6">
                      <v-card variant="outlined" class="h-100 hover-card" :href="file.url" target="_blank">
                        <div class="d-flex align-center pa-3">
                          <v-avatar v-if="file.thumbnail_url" class="mr-3" rounded size="48">
                            <v-img :src="file.thumbnail_url" cover />
                          </v-avatar>
                          <v-avatar v-else color="primary-lighten-5" class="mr-3" rounded>
                            <v-icon color="primary">mdi-file-document-outline</v-icon>
                          </v-avatar>
                          <div class="flex-grow-1 overflow-hidden">
                            <div class="text-body-2 font-weight-bold text-truncate">{{ file.original_filename }}</div>
                            <div class="text-caption text-medium-emphasis">{{ file.details || 'No description' }}</div>
                          </div>
                          <v-icon size="small" color="grey">mdi-download</v-icon>
                        </div>
                      </v-card>
                    </v-col>
                  </v-row>
                </div>

                <!-- Unapproved User Placeholder -->
                <div v-if="!appStore.canSuggest" class="text-center pa-8 bg-grey-lighten-5 rounded-lg border border-dashed">
                  <v-icon size="48" color="grey-lighten-1" class="mb-3">mdi-shield-lock-outline</v-icon>
                  <div class="text-body-1 font-weight-medium text-grey-darken-2">Detailed System Data Restricted</div>
                  <div class="text-caption text-grey-darken-1 mb-4">
                    References, surveys, and technical documents are available to approved club members.
                  </div>
                </div>

                <!-- Statistics (Mobile only) -->
                <div v-if="smAndDown" class="mt-6">
                  <v-divider class="mb-6" />
                  <h3 class="text-h6 mb-4">Statistics</h3>
                  <v-row dense>
                    <v-col cols="6">
                      <div class="d-flex flex-column">
                        <span class="text-caption text-grey">Length</span>
                        <span class="text-h6">{{ cave.system?.length ? Math.round(cave.system.length) + ' m' : '-' }}</span>
                      </div>
                    </v-col>
                    <v-col cols="6">
                      <div class="d-flex flex-column">
                        <span class="text-caption text-grey">Vertical</span>
                        <span class="text-h6">{{ cave.system?.vertical_range ? cave.system.vertical_range + ' m' : '-' }}</span>
                      </div>
                    </v-col>
                  </v-row>

                  <v-divider class="my-4" />

                  <v-chip-group>
                    <v-chip
                      v-for="tag in cave.tags"
                      :key="tag.tag"
                      size="small"
                      color="secondary"
                      variant="tonal"
                      style="cursor: pointer;"
                      @click="$router.push({ path: '/caves', query: { tags: tag.tag, view: 'list' } })"
                    >
                      {{ tag.tag }}
                    </v-chip>
                  </v-chip-group>

                  <template v-if="cave.system?.caves?.length > 1">
                    <v-divider class="my-4" />
                    <h3 class="text-h6 mb-2">System Entrances</h3>
                    <v-list density="compact" class="bg-transparent pa-0">
                      <v-list-item v-for="ent in cave.system.caves" :key="ent.id" :to="'/caves/' + ent.slug"
                                   :active="ent.id === cave.id" class="px-0">
                        <template #prepend>
                          <v-icon icon="mdi-cave" size="small" />
                        </template>
                        <v-list-item-title>{{ ent.name }}</v-list-item-title>
                      </v-list-item>
                    </v-list>
                  </template>
                </div>
              </template>
              <v-alert v-else type="warning" variant="tonal">No system information available.</v-alert>
            </v-window-item>

            <!-- Media Tab -->
            <v-window-item value="media">
              <v-row v-if="allMedia.length > 0">
                <v-col v-for="item in allMedia" :key="item.url || item.filename" cols="6" sm="4" md="3">
                  <v-img :src="item.url" aspect-ratio="1" cover class="rounded cursor-pointer"
                         @click="openImage(item)" />
                </v-col>
              </v-row>
              <v-alert v-else type="info" variant="text">No photos available.</v-alert>
              
              <MediaViewModal v-model="showMediaModal" :media="selectedMedia" />
            </v-window-item>

            <!-- Collections Tab -->
            <v-window-item value="collections">
              <div v-if="linkedCollections && linkedCollections.length > 0">
                <v-row>
                  <v-col v-for="collection in linkedCollections" :key="collection.id" cols="12" md="6">
                    <v-card :to="`/collections/${collection.slug}`" link class="d-flex flex-row align-center rounded-lg"
                            elevation="1">
                      <v-avatar rounded="0" size="80">
                        <v-img
                          :src="collection.photo_path || 'https://images.unsplash.com/photo-1504386106331-3e4e71712b38?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'"
                          cover />
                      </v-avatar>
                      <div class="pa-4 flex-grow-1">
                        <div class="text-subtitle-1 font-weight-bold">{{ collection.name }}</div>
                        <div class="text-caption text-grey">{{ collection.caves_count }} Caves</div>
                        <div v-if="collection.curr_cave_index !== undefined" class="text-caption text-primary">
                          Cave #{{ collection.curr_cave_index + 1 }} in list
                        </div>
                      </div>
                      <div class="pr-4">
                        <v-icon color="grey-lighten-1">mdi-chevron-right</v-icon>
                      </div>
                    </v-card>
                  </v-col>
                </v-row>
              </div>
              <v-alert v-else type="info" variant="tonal">
                This cave does not appear in any collections yet.
              </v-alert>
            </v-window-item>


            
            <!-- Routes Tab -->
            <v-window-item value="routes">
              <template v-if="cave.system && cave.system.routes && cave.system.routes.length > 0">
                <RouteList :routes="cave.system.routes" :cave-system-id="cave.system.id" />
              </template>
              <v-alert v-else-if="cave.system" type="info" variant="tonal">
                <div class="d-flex justify-space-between align-center">
                  <span>No specific routes defined for this system yet.</span>
                  <v-btn
                    v-if="appStore.user.is_admin"
                    color="primary"
                    size="small"
                    variant="text"
                    prepend-icon="mdi-plus"
                    :to="`/cave-systems/${cave.system.id}/routes/new`"
                  >
                    Add Route
                  </v-btn>
                </div>
              </v-alert>
              <v-alert v-else type="warning" variant="tonal">System information not available, cannot show routes.</v-alert>
            </v-window-item>

          </v-window>
        </v-card>
      </v-col>

      <!-- Sidebar Column -->
      <v-col v-if="!smAndDown" cols="12" md="4">
        <!-- Location Card -->
        <v-card class="mb-4 rounded-lg" elevation="1">
          <template v-if="appStore.canSuggest">
            <mgl-map :map-style="style" :center="lnglat" :zoom="zoom" :max-zoom="15" height="300px">
              <mgl-marker :coordinates="lnglat" color="#cc0000" />
              <mgl-navigation-control />
              <mgl-fullscreen-control />
            </mgl-map>
          </template>
          <div v-else class="d-flex align-center justify-center bg-grey-lighten-3 rounded-t-lg" style="height: 300px;">
            <div class="text-center pa-4">
              <v-icon size="48" color="grey" class="mb-2">mdi-map-lock</v-icon>
              <div class="text-h6 text-grey-darken-1">Location Locked</div>
              <div class="text-caption text-grey-darken-1">Join a club to view cave locations and maps</div>
            </div>
          </div>
          <v-card-text>
            <div class="d-flex justify-space-between align-center">
              <div>
                <div class="text-caption text-grey">Coordinates</div>
                <div v-if="appStore.canSuggest && cave.location_lat" class="font-weight-medium text-body-2">{{ cave.location_lat.toFixed(5) }}, {{
                  cave.location_lng.toFixed(5) }}</div>
                <div v-else class="font-weight-medium text-body-2 text-grey">Hidden</div>
              </div>
              <div v-if="appStore.canSuggest" class="d-flex">
                <v-tooltip text="Copy Coordinates" location="top">
                  <template #activator="{ props }">
                    <v-btn icon="mdi-content-copy" size="small" variant="text" v-bind="props"
                           @click="copyLatLng" />
                  </template>
                </v-tooltip>
                <v-tooltip text="Open in Google Maps" location="top">
                  <template #activator="{ props }">
                    <v-btn icon="mdi-google-maps" size="small" variant="text" v-bind="props"
                           :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`"
                           target="_blank" />
                  </template>
                </v-tooltip>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Stats Card -->
        <v-card v-if="!smAndDown" class="mb-4 rounded-lg pa-4" elevation="1">
          <h3 class="text-h6 mb-4">Statistics</h3>
          <v-row dense>
            <v-col cols="6">
              <div class="d-flex flex-column">
                <span class="text-caption text-grey">Length</span>
                <span class="text-h6">{{ cave.system?.length ? Math.round(cave.system.length) + ' m' : '-' }}</span>
              </div>
            </v-col>
            <v-col cols="6">
              <div class="d-flex flex-column">
                <span class="text-caption text-grey">Vertical</span>
                <span class="text-h6">{{ cave.system?.vertical_range ? cave.system.vertical_range + ' m' : '-' }}</span>
              </div>
            </v-col>
          </v-row>

          <v-divider class="my-4" />

          <div class="text-caption text-grey mb-2">Tags</div>
          <v-chip-group>
            <v-chip
              v-for="tag in cave.tags"
              :key="tag.tag"
              size="small"
              color="secondary"
              variant="tonal"
              style="cursor: pointer;"
              @click="$router.push({ path: '/caves', query: { tags: tag.tag, view: 'list' } })"
            >
              {{ tag.tag }}
            </v-chip>
          </v-chip-group>
        </v-card>

        <!-- Entrances Card (if multiple) -->
        <v-card v-if="cave.system?.caves?.length > 1" class="mb-4 rounded-lg" elevation="1">
          <v-card-title class="text-subtitle-1">System Entrances</v-card-title>
          <v-list density="compact">
            <v-list-item v-for="ent in cave.system.caves" :key="ent.id" :to="'/caves/' + ent.slug"
                         :active="ent.id === cave.id">
              <template #prepend>
                <v-icon icon="mdi-cave" size="small" />
              </template>
              <v-list-item-title>{{ ent.name }}</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-card>

      </v-col>
    </v-row>
  </v-container>
  <v-container v-else-if="loading" class="fill-height d-flex justify-center align-center">
    <v-progress-circular indeterminate color="primary" />
  </v-container>
  
  <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center">
    <v-icon icon="mdi-alert-circle-outline" size="64" color="grey" class="mb-4" />
    <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
    <p class="text-body-1 text-grey mb-6">{{ error }}</p>
    <v-btn color="primary" variant="flat" to="/caves" prepend-icon="mdi-arrow-left">
      Back to Caves
    </v-btn>
  </v-container>
</template>


<script setup>
import { useAppStore } from '@/stores/app'
import { useDisplay } from 'vuetify'
import VueMarkdown from 'vue-markdown-render'
import { useRoute, useRouter } from 'vue-router'
import { markCaveAsDone } from '@/stores/markAsDone'
import { useCollectionStore } from '@/stores/collections'
import CaveWeather from '@/components/CaveWeather.vue'
import MediaViewModal from '@/components/MediaViewModal.vue'
import { usePageTitle } from '@/composables/usePageTitle'
import {
  MglMap,
  MglNavigationControl,
  MglMarker,
  MglFullscreenControl,
} from '@indoorequal/vue-maplibre-gl'

const style = 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge'
const zoom = 14

const appStore = useAppStore()
const { smAndDown } = useDisplay()
const collectionStore = useCollectionStore()

const route = useRoute()
const router = useRouter()
const cave = ref(null)
const loading = ref(true)
const error = ref(null)
const activeTab = ref(route.query.tab || 'overview')

const pageTitle = computed(() => cave.value?.name)
usePageTitle(pageTitle)

// Sync tab changes to URL without adding history
watch(activeTab, (newTab) => {
  router.replace({ query: { ...route.query, tab: newTab } })
})

// Sync URL changes to tab (e.g. back/forward navigation or direct link)
watch(() => route.query.tab, (newTab) => {
  if (newTab && newTab !== activeTab.value) {
    activeTab.value = newTab
  }
})
const showConfirmModal = ref(false)

// Media Modal State
const showMediaModal = ref(false)
const selectedMedia = ref({})

const hasDone = computed(() => {
  return cave.value?.trips?.some(trip => trip.participants.some(participant => participant.id === appStore.user.id))
})

// Count only visible trips (those with end_time or where current user is a participant)
const visibleTripsCount = computed(() => {
  if (!cave.value?.trips) return 0
  return cave.value.trips.filter(trip =>
    trip.end_time || trip.participants.some(participant => participant.id === appStore.user.id)
  ).length
})

const allMedia = computed(() => {
  const mediaList = []

  // Add Cave-specific media (Hero, Entrance, etc.)
  if (cave.value?.media) {
    mediaList.push(...cave.value.media)
  }

  // Add Trip-related media
  if (cave.value?.trips) {
    const tripMedia = cave.value.trips.reduce((acc, trip) => {
      if (trip.media && trip.media.length > 0) {
        const enrichedMedia = trip.media.map(m => ({
          ...m,
          trip_id: trip.id,
          trip_name: trip.name,
          photographer: m.photographer || (m.user_id ? trip.participants.find(p => p.id === m.user_id)?.name : null)
        }))
        acc.push(...enrichedMedia)
      }
      return acc
    }, [])
    mediaList.push(...tripMedia)
  }

  return mediaList
})

// Compute collections that this cave belongs to
const linkedCollections = computed(() => {
  return cave.value?.collections || []
})

const fetchCave = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await fetch(`/api/caves/${route.params.id}`, { headers: { 'Accept': 'application/json' } })
    if (response.status === 404) {
      error.value = "Cave not found. It may have been deleted or you may have the wrong link."
    } else if (!response.ok) {
      error.value = "Failed to load cave. Please try again later."
    } else {
      const responseData = await response.json()
      cave.value = responseData.data

      if (cave.value && cave.value.system) {
        cave.value.system.files = cave.value.system.files || []
        cave.value.system.caves = cave.value.system.caves || []
      } else if (cave.value) {
        cave.value.system = { files: [], caves: [] }
      }
      if (!cave.value.trips) {
        cave.value.trips = []
      }
    }

  } catch (e) {
    console.error("Failed to fetch cave data:", e)
    error.value = "An unexpected error occurred."
  } finally {
    loading.value = false
  }
}

const lnglat = computed(() => {
  return [cave.value.location_lng, cave.value.location_lat]
})

const copyLatLng = async () => {
  if (cave.value && navigator.clipboard) {
    const textToCopy = `${cave.value.location_lat}, ${cave.value.location_lng}`
    try {
      await navigator.clipboard.writeText(textToCopy)
      // TODO: Add user feedback like a snackbar message
      console.log('Coordinates copied to clipboard:', textToCopy)
    } catch (err) {
      console.error('Failed to copy coordinates: ', err)
    }
  }
}

const markAsDone = () => {
  showConfirmModal.value = true
}

const confirmMarkAsDone = async () => {
  const ok = await markCaveAsDone({ cave: cave.value, userId: appStore.user.id })
  if (ok) {
    await fetchCave()
    showConfirmModal.value = false
  } else {
    console.error('failed to save trip')
  }
}

const openImage = (item) => {
  selectedMedia.value = item
  showMediaModal.value = true
}

onMounted(() => {
  fetchCave()
})

watch(
  () => route.params.id,
  (newId, oldId) => {
    if (newId !== oldId) {
      fetchCave()
    }
  }
)
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.cursor-pointer {
  cursor: pointer;
}

.hero-img {
  transition: filter 0.2s ease;

  &:hover {
    filter: brightness(0.9);
  }
}

.file-list {
  background-color: transparent;
}

.file-item {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  padding-left: 0 !important;
  padding-right: 0 !important;
}

.file-item:last-child {
  border-bottom: none;
}
</style>