<template>
  <v-container class="pa-0 pb-8">
    <template v-if="caveStore.loading">
      <div class="d-flex justify-center my-8">
        <v-progress-circular indeterminate color="primary" size="48" />
      </div>
    </template>

    <template v-else>
      <div v-if="caveStore.caves.length === 0" class="text-center py-8">
        <v-icon size="64" color="grey lighten-10" :icon="mdiMapMarkerOff" class="mb-4" />
        <template v-if="hasFilters">
          <h3 class="text-h6 font-weight-medium text-grey-darken-1">No caves found with these filters</h3>
          <p class="text-body-2 text-grey-darken-1 mb-4">The cave you're looking for may be in another region.</p>
          <v-btn color="primary" variant="tonal" @click="emit('clear-filters')">
            Remove All Filters
          </v-btn>
        </template>
        <template v-else>
          <h3 class="text-h6 font-weight-medium text-grey-darken-1">No caves found</h3>
          <p class="text-body-2 text-grey-darken-1">Try adjusting your search.</p>
        </template>
      </div>

      <v-row v-else class="px-2">
        <v-col v-for="cave in displayedCaves" :key="cave.id" cols="12" sm="6" md="4" lg="3">
          <v-hover v-slot="{ isHovering, props }">
            <v-card v-bind="props" elevation="2" class="fill-height d-flex flex-column cave-card"
                    :to="'/caves/' + cave.slug">
              <div class="position-relative bg-grey-lighten-2" style="height: 160px; overflow: hidden;">
                <!-- Video Preview -->
                <video
                  v-if="cave.hero_video"
                  :ref="el => { if(el) { videoRefs[cave.id] = el; (!mobile && isHovering) ? el.play() : el.pause() } }"
                  :src="cave.hero_video.preview_url || cave.hero_video.url"
                  muted loop playsinline
                  class="position-absolute w-100 h-100"
                  style="object-fit: cover; top: 0; left: 0; z-index: 1; transition: opacity 0.3s ease;"
                  :style="{ opacity: (!mobile && isHovering) ? 1 : 0 }"
                />
                <!-- Image Fallback/Poster -->
                <v-img :src="cave.hero_video?.poster_url || cave.hero_image?.url || cave.entrance_image?.url || '/placeholder-cave.jpg'" height="160" cover
                       class="position-absolute w-100 h-100" style="top: 0; left: 0; z-index: 0;">
                  <template #placeholder>
                    <div class="d-flex align-center justify-center fill-height">
                      <v-icon color="grey-lighten-1" size="large" :icon="mdiImageOffOutline" />
                    </div>
                  </template>
                  <div v-if="cave.previously_done" class="d-flex justify-end pa-2 position-relative" style="z-index: 2;">
                    <v-chip color="success" size="small" variant="elevated" :prepend-icon="mdiCheck">Done</v-chip>
                  </div>
                  <div v-if="offlineStore.isPwa && offlineStore.isCaveDownloaded(cave.id)" class="position-absolute pa-2" style="z-index: 2; top: 0; left: 0;">
                    <v-chip color="grey-darken-3" size="x-small" variant="elevated" :prepend-icon="mdiCloudDownload">Offline</v-chip>
                  </div>
                </v-img>
              </div>

              <div class="pa-4 d-flex flex-column flex-grow-1">
                <div class="mb-2">
                  <h3 class="text-h6 font-weight-bold lh-tight mb-1 text-truncate">{{ cave.name }}</h3>
                  <div class="d-flex align-center text-caption text-grey-darken-1">
                    <v-icon size="small" :icon="mdiMapMarker" class="mr-1" />
                    <span class="text-truncate">{{ cave.location_name }}, {{ cave.location_country }}</span>
                  </div>
                </div>

                <div class="d-flex align-center ga-4 mb-3">
                  <div class="d-flex flex-column">
                    <span class="text-caption text-grey">Length</span>
                    <span class="font-weight-medium">
                      {{ cave.system?.length ? Math.round((cave.system.length / 1000) * 10) / 10 + ' km' : '-' }}
                    </span>
                  </div>
                  <!-- Add vertical divider if needed -->
                  <div class="d-flex flex-column">
                    <span class="text-caption text-grey">Vertical</span>
                    <span class="font-weight-medium">
                      {{ cave.system?.vertical_range ? cave.system.vertical_range + ' m' : '-' }}
                    </span>
                  </div>
                </div>

                <div class="mt-auto">
                  <v-chip-group class="mb-0">
                    <v-chip
                      v-for="tag in (cave.tags || []).slice(0, 3)"
                      :key="tag.tag"
                      size="x-small"
                      variant="tonal"
                      style="cursor: pointer;"
                      @click.stop.prevent="emit('tag-click', tag.tag)"
                    >
                      {{ tag.tag }}
                    </v-chip>
                    <v-chip v-if="(cave.tags || []).length > 3" size="x-small" variant="text" class="px-1 text-grey">
                      +{{ cave.tags.length - 3 }}
                    </v-chip>
                  </v-chip-group>
                </div>
              </div>

              <v-divider />

              <div class="pa-2 d-flex justify-end">
                <v-btn v-if="!cave.previously_done" variant="text" color="primary" size="small"
                       @click.stop.prevent="showConfirmModal = true; caveToMark = cave">
                  Mark as Done
                </v-btn>
                <v-btn variant="text" size="small" color="grey-darken-1" :to="'/caves/' + cave.slug">
                  Details
                </v-btn>
              </div>
            </v-card>
          </v-hover>
        </v-col>
      </v-row>

      <!-- Infinite scroll sentinel -->
      <div ref="sentinel" class="py-6 d-flex justify-center">
        <v-progress-circular v-if="hasMore" indeterminate color="grey-lighten-2" size="28" />
        <p v-else-if="caveStore.caves.length > PAGE_SIZE" class="text-caption text-grey">
          All {{ caveStore.caves.length }} caves shown
        </p>
      </div>
    </template>

    <v-dialog v-model="showConfirmModal" max-width="400">
      <v-card class="rounded-lg">
        <v-card-title class="text-h6 pa-4">Mark Cave as Done?</v-card-title>
        <v-card-text class="pt-0 pb-4 text-body-1">
          Are you sure you want to mark <strong>{{ caveToMark?.name }}</strong> as visited?
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="showConfirmModal = false; caveToMark = null">Cancel</v-btn>
          <v-btn color="primary" variant="flat" @click="markAsDone(caveToMark)">Confirm</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
<script setup>
import { mdiCheck, mdiCloudDownload, mdiImageOffOutline, mdiMapMarker, mdiMapMarkerOff } from '@mdi/js'
import { ref, computed, watch, onUnmounted } from 'vue'

const emit = defineEmits(['tag-click', 'clear-filters'])

const props = defineProps({
  hasFilters: { type: Boolean, default: false },
})

import { useCaveStore } from '@/stores/caves'
import { useAppStore } from '@/stores/app'
import { useOfflineStore } from '@/stores/offline'
import { markCaveAsDone } from '@/stores/markAsDone'
import { useDisplay } from 'vuetify'

const offlineStore = useOfflineStore()

const caveStore = useCaveStore()
const appStore = useAppStore()
const { mobile } = useDisplay()
const showConfirmModal = ref(false)
const caveToMark = ref(null)
const videoRefs = ref({})

const PAGE_SIZE = 24
const displayCount = ref(PAGE_SIZE)
const sentinel = ref(null)

const displayedCaves = computed(() => caveStore.caves.slice(0, displayCount.value))
const hasMore = computed(() => displayCount.value < caveStore.caves.length)

// Reset pagination whenever the filtered cave list changes
watch(() => caveStore.caves, () => {
  displayCount.value = PAGE_SIZE
})

let observer = null

const attachObserver = (el) => {
  observer?.disconnect()
  if (!el) return
  observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && hasMore.value) {
      displayCount.value += PAGE_SIZE
    }
  }, { rootMargin: '200px' })
  observer.observe(el)
}

// Watch the sentinel ref — it only appears after caves load (v-else block)
watch(sentinel, (el) => {
  attachObserver(el)
})

onUnmounted(() => {
  observer?.disconnect()
})

const markAsDone = async (cave) => {
  if (!cave) return
  const ok = await markCaveAsDone({ cave, userId: appStore.user.id })
  if (ok) {
    await caveStore.getList()
    showConfirmModal.value = false
    caveToMark.value = null
  } else {
    console.error('failed to save trip')
  }
}
</script>