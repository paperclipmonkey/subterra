<template>
  <v-container class="pa-0 pb-16">
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
          <v-hover v-slot="{ isHovering, props: hoverProps }">
            <v-card v-bind="hoverProps" elevation="2"
                    class="fill-height d-flex flex-column cave-card"
                    :class="{ 'cave-card--leaving': leavingIds.has(cave.id) }"
                    :to="'/caves/' + cave.slug">
              <div class="position-relative bg-grey-darken-3 cave-card__media" style="height: 210px; overflow: hidden;">
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
                <v-img :src="cardImage(cave).src" :srcset="cardImage(cave).srcset"
                       sizes="(max-width: 600px) 100vw, (max-width: 960px) 50vw, (max-width: 1280px) 33vw, 320px"
                       height="210" cover
                       class="position-absolute w-100 h-100"
                       :class="{ 'cave-card__img--placeholder': cardImage(cave).isPlaceholder }"
                       style="top: 0; left: 0; z-index: 0;">
                  <template #placeholder>
                    <div class="d-flex align-center justify-center fill-height">
                      <v-icon color="grey-lighten-1" size="large" :icon="mdiImageOffOutline" />
                    </div>
                  </template>
                </v-img>

                <!-- Gradient + title overlay -->
                <div class="cave-card__scrim" />
                <div class="cave-card__overlay pa-3">
                  <h3 class="cave-card__name">{{ cave.name }}</h3>
                  <div class="cave-card__loc d-flex align-center">
                    <v-icon size="14" :icon="mdiMapMarker" class="mr-1 flex-shrink-0" />
                    <span class="text-truncate">{{ cave.location_name }}, {{ cave.location_country }}</span>
                  </div>
                </div>

                <div v-if="cave.previously_done" class="position-absolute pa-2" style="z-index: 3; top: 0; right: 0;">
                  <v-chip color="success" size="small" variant="elevated" :prepend-icon="mdiCheck">Done</v-chip>
                </div>
                <div v-if="offlineStore.isPwa && offlineStore.isCaveDownloaded(cave.id)" class="position-absolute pa-2" style="z-index: 3; top: 0; left: 0;">
                  <v-chip color="grey-darken-3" size="x-small" variant="elevated" :prepend-icon="mdiCloudDownload">Offline</v-chip>
                </div>
              </div>

              <div class="px-3 py-2 d-flex flex-column flex-grow-1">
                <div class="d-flex align-center">
                  <div class="d-flex align-center cave-card__stat">
                    <v-icon size="15" :icon="mdiArrowExpandHorizontal" class="mr-1" />
                    <span>{{ cave.system?.length ? Math.round((cave.system.length / 1000) * 10) / 10 + ' km' : '–' }}</span>
                  </div>
                  <div class="d-flex align-center cave-card__stat ml-4">
                    <v-icon size="15" :icon="mdiArrowExpandVertical" class="mr-1" />
                    <span>{{ cave.system?.vertical_range ? cave.system.vertical_range + ' m' : '–' }}</span>
                  </div>
                  <v-spacer />
                  <v-tooltip v-if="!cave.previously_done" text="Mark as done" location="top">
                    <template #activator="{ props: tipProps }">
                      <v-btn v-bind="tipProps" :icon="mdiCheckCircleOutline" size="small" variant="text"
                             color="grey-darken-1" density="comfortable" aria-label="Mark as done"
                             @click.stop.prevent="showConfirmModal = true; caveToMark = cave" />
                    </template>
                  </v-tooltip>
                </div>

                <div class="d-flex align-center flex-wrap ga-1 mt-1 mb-1">
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
                  <span v-if="(cave.tags || []).length > 3" class="text-caption text-grey ml-1">
                    +{{ cave.tags.length - 3 }}
                  </span>
                </div>
              </div>
            </v-card>
          </v-hover>
        </v-col>
      </v-row>

      <!-- Infinite scroll sentinel -->
      <div ref="sentinel" class="py-6 d-flex flex-wrap align-center justify-center ga-1">
        <v-progress-circular v-if="hasMore" indeterminate color="grey-lighten-2" size="28" />
        <template v-else>
          <p v-if="caveStore.caves.length > PAGE_SIZE" class="text-caption text-grey mb-0">
            All {{ caveStore.caves.length }} caves shown
          </p>
          <v-btn
            v-if="caveStore.caves.length > 0 && appStore.canSuggest"
            variant="text"
            size="small"
            color="primary"
            class="text-none"
            :prepend-icon="mdiEarth"
            @click="exportKml"
          >
            Export to Google Earth (KML)
          </v-btn>
        </template>
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
import { mdiArrowExpandHorizontal, mdiArrowExpandVertical, mdiCheck, mdiCheckCircleOutline, mdiCloudDownload, mdiEarth, mdiImageOffOutline, mdiMapMarker, mdiMapMarkerOff } from '@mdi/js'
import { ref, computed, watch, onUnmounted } from 'vue'
import { downloadCavesKml } from '@/utilities/caveKml'

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

// Cards mid-fade before they're spliced from the list (see markAsDone).
// Must match the CSS transition duration on .cave-card--leaving.
const FADE_MS = 650
const leavingIds = ref(new Set())

const displayedCaves = computed(() => caveStore.caves.slice(0, displayCount.value))
const hasMore = computed(() => displayCount.value < caveStore.caves.length)

// Pick the card image with a responsive srcset when the API provides variants.
// Cards are small (160px tall), so the browser will choose the mobile/tablet
// WebP variant rather than downloading the full-size desktop image.
const cardImage = (cave) => {
  if (cave.hero_video?.poster_url) {
    return { src: cave.hero_video.poster_url, srcset: undefined }
  }
  const media = cave.hero_image || cave.entrance_image
  if (media?.url) {
    return { src: media.url, srcset: media.srcset || undefined }
  }
  return { src: '/placeholder-cave.jpg', srcset: undefined, isPlaceholder: true }
}

// Reset pagination when what's being listed changes — NOT on every reassignment
// of `caves`. The array identity changes on each fetch and filter pass, so
// keying off it sent the user back to the first page merely for revisiting the
// page, undoing any scroll restore.
watch(() => caveStore.filterSignature, () => {
  displayCount.value = PAGE_SIZE
})

// Expose the current page depth so the page can restore it on return.
defineExpose({
  displayCount,
  restoreDisplayCount: (count) => {
    if (count > displayCount.value) displayCount.value = count
  },
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

const exportKml = () => {
  downloadCavesKml(caveStore.caves)
}

const markAsDone = async (cave) => {
  if (!cave) return
  const ok = await markCaveAsDone({ cave, userId: appStore.user.id })
  if (!ok) {
    console.error('failed to save trip')
    return
  }
  showConfirmModal.value = false
  caveToMark.value = null

  if (caveStore.hidesDoneCaves) {
    // The active filter only shows not-yet-done caves, so this one no longer
    // belongs. Fade the card out, then splice it from the list. Both steps keep
    // the user's scroll position — a refetch would reset pagination and jump
    // them back to the top of a list that can be thousands of caves long.
    leavingIds.value.add(cave.id)
    await new Promise(resolve => setTimeout(resolve, FADE_MS))
    caveStore.removeCaveFromList(cave.id)
    leavingIds.value.delete(cave.id)
  } else {
    // Otherwise keep the card in place, now showing its "Done" state.
    caveStore.markDoneLocally(cave.id)
  }
}
</script>

<style scoped lang="scss">
.cave-card {
  border-radius: 16px;
  overflow: hidden;
  transition: transform 0.25s ease, box-shadow 0.25s ease;

  &:hover {
    transform: translateY(-4px);
    box-shadow:
      0 2px 6px rgba(24, 38, 31, 0.08),
      0 14px 32px rgba(24, 38, 31, 0.16) !important;
  }
}

// Fade + shrink a card as it's removed from a "not done yet" filtered list.
.cave-card--leaving {
  opacity: 0;
  transform: scale(0.94);
  transition: opacity 0.65s ease, transform 0.65s ease;
  pointer-events: none;
}

.cave-card__media {
  :deep(.v-img__img),
  video {
    transition: transform 0.45s ease;
  }
}

.cave-card:hover .cave-card__media :deep(.v-img__img) {
  transform: scale(1.06);
}

// Strong bottom gradient so the overlaid title is always readable,
// even on the muted placeholder artwork.
.cave-card__scrim {
  position: absolute;
  inset: auto 0 0 0;
  height: 70%;
  z-index: 2;
  pointer-events: none;
  background: linear-gradient(
    to top,
    rgba(12, 18, 15, 0.74) 0%,
    rgba(12, 18, 15, 0.28) 55%,
    rgba(12, 18, 15, 0) 100%
  );
}

// The placeholder artwork is very dark — lift it so cards don't read as
// black rectangles when a cave has no photo yet.
.cave-card__img--placeholder :deep(.v-img__img) {
  filter: brightness(1.55) saturate(0.85);
}

.cave-card__overlay {
  position: absolute;
  inset: auto 0 0 0;
  z-index: 3;
  color: #fff;
  pointer-events: none;
}

.cave-card__name {
  font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.01em;
  text-shadow: 0 1px 8px rgba(0, 0, 0, 0.4);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cave-card__loc {
  font-size: 0.78rem;
  opacity: 0.85;
  text-shadow: 0 1px 6px rgba(0, 0, 0, 0.4);
  max-width: 100%;
}

.cave-card__stat {
  font-size: 0.875rem;
  font-weight: 700;
  color: rgba(30, 42, 36, 0.85);

  .v-icon {
    color: rgba(30, 42, 36, 0.45);
  }
}
</style>