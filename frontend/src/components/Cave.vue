<template>
  <v-container v-if="cave">
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.push({name: '/caves'})">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-btn v-if="appStore.user.is_admin" class="float-right" icon @click="$router.push({name: '/cave/[id].edit', params: {id: route.params.id}})">
          <v-icon>mdi-pencil</v-icon>
        </v-btn>
        <v-toolbar-title>{{ cave.name }}</v-toolbar-title>
        <v-divider>{{ cave.system.name }}</v-divider>
      </v-col>
    </v-row>

    <!-- Main Content Row -->
    <v-row>
      <!-- Left Column -->
      <v-col cols="12" md="8">
        <v-row>
          <v-col cols="12">
            <v-card>
              <v-img
                class="align-end text-white"
                height="200"
                :src="cave.hero_image ? cave.hero_image : cave.entrance_image"
                cover
              >
                <v-card-title style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                  {{ cave.name }}
                </v-card-title>
                <v-card-subtitle style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ cave.location_name }},  {{ cave.location_country }}</v-card-subtitle>
              </v-img>
              <v-card-text>
                <vue-markdown :source="cave.description" />
                <p> Tags:
                  <v-chip v-for="tag in cave.tags" :key="tag" class="ma-1">
                    {{ tag.tag }}
                  </v-chip>
                </p>
                <strong>Location:</strong>
                <v-btn :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`" target="_blank" icon>
                  <v-icon>mdi-google-maps</v-icon>
                </v-btn>
                <v-btn :href="`https://maps.apple.com/?q=${cave.location_lat},${cave.location_lng}`" target="_blank" icon>
                  <v-icon>mdi-apple</v-icon>
                </v-btn>
                <v-btn @click="copyLatLng" icon>
                  <v-icon>mdi-clipboard-multiple-outline</v-icon>
                </v-btn>
                <vue-markdown v-if="cave.access_info" :source="'Access Info: ' + cave.access_info" />
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12">
            <v-card>
              <v-card-title>
                Recent Trips
                <v-btn class="float-right" icon @click="$router.push({name: '/create-trip', query: {cave_id: cave.id}})">
                  <v-icon>mdi-plus</v-icon>
                </v-btn>
                <template v-if="!hasDone">
                    <v-btn class="float-right" icon @click="showConfirmModal = true">
                    <v-icon>mdi-check</v-icon>
                    </v-btn>
                    <v-dialog v-model="showConfirmModal" max-width="500">
                    <v-card>
                      <v-card-title>Confirm</v-card-title>
                      <v-card-text>Are you sure you want to mark this cave as done?</v-card-text>
                      <v-card-actions>
                      <v-spacer></v-spacer>
                      <v-btn text @click="showConfirmModal = false">Cancel</v-btn>
                      <v-btn text color="primary" @click="markAsDone">Confirm</v-btn>
                      </v-card-actions>
                    </v-card>
                    </v-dialog>
                </template>

              </v-card-title>

              <v-card-text>
                <v-list>
                  <template v-for="trip in cave.trips" :key="trip.datetime">
                    <CaveTripListItem :trip="trip" v-if="trip.end_time || trip.participants.some(participant => participant.id === appStore.user.id)" />
                  </template>
                </v-list>
                <template v-if="cave.trips.length === 0">
                  <v-alert>No trips have been recorded for this cave yet.</v-alert>
                </template>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12">
            <v-card>
              <v-card-title>
                Media
              </v-card-title>
              <v-card-text>
                <v-row>
                  <v-col
                    v-if="cave.hero_image"
                    class="d-flex child-flex"
                    cols="6" sm="4" md="3" lg="4"
                  >
                    <v-img
                      :src="cave.hero_image"
                      aspect-ratio="1"
                      class="bg-grey-lighten-2"
                      cover
                      @click="openImage(cave.hero_image)"
                      style="cursor: pointer;"
                    >
                    </v-img>
                  </v-col>
                  <v-col
                    v-if="cave.entrance_image"
                    class="d-flex child-flex"
                    cols="6" sm="4" md="3" lg="4"
                  >
                    <v-img
                      :src="cave.entrance_image"
                      aspect-ratio="1"
                      class="bg-grey-lighten-2"
                      cover
                      @click="openImage(cave.entrance_image)"
                      style="cursor: pointer;"
                    >
                    </v-img>
                  </v-col>
                  <v-col
                    v-for="media in media"
                    :key="media.url"
                    class="d-flex child-flex"
                    cols="6" sm="4" md="3" lg="4"
                  >
                    <v-img
                      :src="media.url"
                      aspect-ratio="1"
                      class="bg-grey-lighten-2"
                      cover
                      @click="openImage(media.url)"
                      style="cursor: pointer;"
                    >
                    </v-img>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-col>

      <!-- Right Column -->
      <v-col cols="12" md="4">
        <v-row>
          <v-col cols="12">
            <v-card class="mb-4">
              <mgl-map
                :map-style="style"
                :center="lnglat"
                :zoom="zoom"
                :max-zoom="15"
                height="350px"
              >
                <mgl-marker :coordinates="lnglat" color="#cc0000" />
                <mgl-navigation-control />
                <MglGeolocateControl :track-user-location="true" :showAccuracyCircle="true"/>
                <mgl-fullscreen-control />
              </mgl-map>
            </v-card>
          </v-col>
        </v-row>
        <v-row v-if="cave.system">
          <v-col cols="12">
            <v-card>
              <v-card-title>{{ cave.system.name }}</v-card-title>
              <v-card-subtitle>System</v-card-subtitle>
              <v-btn v-if="appStore.user.is_admin" class="float-right" icon @click="$router.push({name: '/cave_system/[id].edit', params: {id: cave.system.id}})">
                <v-icon>mdi-pencil</v-icon>
              </v-btn>
              <v-card-text>
                <vue-markdown :source="cave.system.description" />
                <p> Tags:
                  <v-chip v-for="tag in cave.system.tags" :key="tag" class="ma-1">
                    {{ tag.tag }}
                  </v-chip>
                </p>
                <p><strong>Length:</strong> {{ Math.round(cave.system.length) }} m</p>
                <p><strong>Vertical Range:</strong> {{ cave.system.vertical_range }} m</p>

                <div v-if="cave.system.files && cave.system.files.length > 0" class="mt-4">
                  <h3 class="text-subtitle-1 font-weight-bold mb-2">Files</h3>
                  <v-list lines="two" class="file-list pa-0">
                    <v-list-item
                      v-for="file in cave.system.files"
                      :key="file.id"
                      :href="file.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="file-item"
                      density="compact"
                    >
                      <template v-slot:prepend>
                        <v-avatar rounded="0" class="mr-3">
                          <v-img
                            v-if="isImage(file.mime_type)"
                            :src="file.url"
                            :alt="file.original_filename"
                            aspect-ratio="1"
                            cover
                            class="border"
                          ></v-img>
                          <v-icon v-else size="large">{{ getFileIcon(file.mime_type) }}</v-icon>
                        </v-avatar>
                      </template>

                      <v-list-item-title class="font-weight-medium">{{ file.original_filename }}</v-list-item-title>
                      <v-list-item-subtitle>
                        <span v-if="file.details">{{ file.details }}</span>
                        <span v-if="file.details && file.size"> - </span>
                        <span v-if="file.size">{{ formatBytes(file.size) }}</span>
                      </v-list-item-subtitle>

                    </v-list-item>
                  </v-list>
                </div>
                 <p v-else class="mt-4 text-grey">No files associated with this system.</p>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row v-if="cave.system.caves.length > 1">
          <v-col cols="12">
            <v-card>
              <v-card-title>System Entrances</v-card-title>
              <v-card-text>
                This system has multiple entrances:
                <v-list>
                  <v-list-item v-for="system_cave in cave.system.caves" :key="system_cave.id">
                    <div>
                      <v-list-item-title><RouterLink :to="{name: '/cave/[id]', params: {id: system_cave.slug}}">{{ system_cave.name }}</RouterLink></v-list-item-title>
                      <v-list-item-subtitle>{{ system_cave.description }}</v-list-item-subtitle>
                    </div>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row v-if="cave.system.references">
          <v-col cols="12">
            <v-card>
              <v-card-title>System References</v-card-title>
              <v-card-text>
                <vue-markdown :source="cave.system.references" />
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-col>
    </v-row>

  </v-container>
   <v-container v-else>
     <p>Loading cave data...</p>
   </v-container>
</template>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.file-list {
  background-color: transparent;
}
.file-item {
  border-bottom: 1px solid rgba(0,0,0,0.08);
  padding-left: 0 !important;
  padding-right: 0 !important;
}
.file-item:last-child {
  border-bottom: none;
}
</style>

<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import VueMarkdown from 'vue-markdown-render'
import { useRoute } from "vue-router"
import { useAppStore } from '@/stores/app'

import {
  MglMap,
  MglNavigationControl,
  MglMarker,
  MglGeolocateControl,
  MglFullscreenControl,
} from '@indoorequal/vue-maplibre-gl';

const style = 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge';
const zoom = 11;

const appStore = useAppStore()

const route = useRoute()
const cave = ref(null)

const hasDone = computed(() => {
  return cave.value.trips.some(trip => trip.participants.some(participant => participant.id === appStore.user.id))
})

const media = computed(()=> {
  return cave.value.trips.reduce((acc, item) => {
    if(item.media) {
      acc.push(...item.media)
      return acc
    }
  }, [])
})

const fetchCave = async () => {
  try {
    const response = await fetch(`/api/caves/${route.params.id}`)
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    const responseData = await response.json();
    cave.value = responseData.data;

    if (cave.value && cave.value.system) {
        cave.value.system.files = cave.value.system.files || [];
        cave.value.system.caves = cave.value.system.caves || [];
    } else if (cave.value) {
        cave.value.system = { files: [], caves: [] };
    }
     if (!cave.value.trips) {
        cave.value.trips = [];
    }

  } catch (error) {
      console.error("Failed to fetch cave data:", error);
  }
}

const lnglat = computed(() => {
  return [cave.value.location_lng, cave.value.location_lat]
})

const copyLatLng = async () => {
  if (cave.value && navigator.clipboard) {
    const textToCopy = `${cave.value.location_lat}, ${cave.value.location_lng}`;
    try {
      await navigator.clipboard.writeText(textToCopy);
      // Optional: Add user feedback like a snackbar message
      console.log('Coordinates copied to clipboard:', textToCopy);
    } catch (err) {
      console.error('Failed to copy coordinates: ', err);
      // Optional: Add error feedback
    }
  }
};

const showConfirmModal = ref(false)

const markAsDone = async () => {
  showConfirmModal.value = true
  const trip = {
    name: 'Marked as Done',
    entrance_cave_id: cave.value.id,
    exit_cave_id: cave.value.id,
    participants: [appStore.user.id],
    cave_system_id: cave.value.system.id,
    visibility: 'private',
  }

  const response = await fetch('/api/trips', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(trip)
  })
  if (response.ok) {
    fetchCave()
    showConfirmModal.value = false
  } else {
    console.error('failed to save trip')
  }
}

const formatBytes = (bytes, decimals = 2) => {
  if (!+bytes) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
};

const isImage = (mimeType) => {
  return mimeType && mimeType.startsWith('image/');
};

const getFileIcon = (mimeType) => {
  if (!mimeType) return 'mdi-file-outline';
  if (mimeType.includes('pdf')) return 'mdi-file-pdf-box';
  if (mimeType.includes('word')) return 'mdi-file-word-box';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'mdi-file-excel-box';
  if (mimeType.includes('zip') || mimeType.includes('archive')) return 'mdi-archive-arrow-down-outline';
  if (mimeType.startsWith('text/')) return 'mdi-file-document-outline';
  return 'mdi-file-outline';
};

const openImage = (url) => {
  if (url) {
    window.open(url, '_blank');
  }
};

onMounted(fetchCave)

watch(
  () => route.params.id,
  (newId, oldId) => {
    if (newId !== oldId) {
        fetchCave();
    }
  }
)
</script>