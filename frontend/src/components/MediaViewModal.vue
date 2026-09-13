<template>
  <v-dialog v-model="dialog" max-width="1200px" scrollable class="media-modal">
    <v-card height="100%" max-height="90vh" class="d-flex flex-column">
      <div class="d-flex flex-grow-1 flex-column flex-md-row overflow-hidden">

        <!-- Image Area (Left/Top) -->
        <div class="bg-black d-flex align-center justify-center flex-grow-1 overflow-hidden position-relative" style="min-height: 300px; flex-basis: 70%;">
          <v-btn :icon="mdiClose" variant="text" color="white" class="position-absolute top-0 right-0 ma-2 d-md-none" style="z-index: 10" @click="closeModal" />

          <!-- Carousel controls: only meaningful with a gallery behind the modal -->
          <template v-if="hasGallery">
            <v-btn
              :icon="mdiChevronLeft"
              variant="tonal"
              color="white"
              size="large"
              class="media-nav media-nav--prev"
              aria-label="Previous photo"
              @click.stop="showPrevious"
            />
            <v-btn
              :icon="mdiChevronRight"
              variant="tonal"
              color="white"
              size="large"
              class="media-nav media-nav--next"
              aria-label="Next photo"
              @click.stop="showNext"
            />
            <div class="media-counter text-caption">{{ currentIndex + 1 }} / {{ items.length }}</div>
          </template>

          <video
            v-if="currentMedia.type === 'hero_video' || currentMedia.type === 'video'"
            :key="currentMedia.url"
            :src="currentMedia.url"
            controls
            autoplay
            class="media-video w-100 h-100 d-block"
            style="max-height: 100%; max-width: 100%; object-fit: contain; outline: none;"
          />
          <v-img
            v-else
            :key="currentMedia.url"
            :src="currentMedia.url"
            :alt="currentMedia.filename"
            max-height="100%"
            max-width="100%"
            contain
            class="media-image"
          >
            <template #placeholder>
              <div class="d-flex align-center justify-center fill-height">
                <v-progress-circular indeterminate color="grey-lighten-5" />
              </div>
            </template>
          </v-img>
        </div>

        <!-- Sidebar / Details Area (Right/Bottom) -->
        <div class="d-flex flex-column bg-surface border-s" style="flex-basis: 30%; min-width: 300px; max-width: 100%; overflow-y: auto;">

          <!-- Header (Desktop only close button) -->
          <div class="d-none d-md-flex justify-space-between align-center pa-4 pb-2">
            <div class="text-overline text-medium-emphasis">Media Details</div>
            <v-btn :icon="mdiClose" variant="text" density="comfortable" @click="closeModal" />
          </div>

          <v-divider class="d-none d-md-block mb-2" />

          <div class="pa-4">
            <!-- Title -->
            <h2 v-if="currentMedia.title" class="text-h5 font-weight-bold mb-4">{{ currentMedia.title }}</h2>
            <div v-else class="text-h6 font-weight-regular text-medium-emphasis font-italic mb-4">No Title</div>

            <!-- Metadata Grid -->
            <div class="d-flex flex-column gap-3">

              <!-- Trip -->
              <div v-if="currentMedia.trip_name" class="mb-3">
                <div class="text-caption text-medium-emphasis mb-1">Trip</div>
                <div class="d-flex align-center">
                  <v-icon size="small" start :icon="mdiHiking" color="primary" />
                  <router-link :to="`/trips/${currentMedia.trip_id}`" class="text-decoration-none text-body-1 font-weight-medium text-primary text-truncate" @click="closeModal">
                    {{ currentMedia.trip_name }}
                  </router-link>
                </div>
              </div>

              <!-- Photographer -->
              <div v-if="currentMedia.photographer" class="mb-3">
                <div class="text-caption text-medium-emphasis mb-1">Photographer</div>
                <div class="d-flex align-center">
                  <v-icon size="small" start :icon="mdiCamera" class="text-medium-emphasis" />
                  <span class="text-body-1">{{ currentMedia.photographer }}</span>
                </div>
              </div>

              <!-- Copyright -->
              <div v-if="currentMedia.copyright" class="mb-3">
                <div class="text-caption text-medium-emphasis mb-1">Copyright</div>
                <div class="d-flex align-center">
                  <v-icon size="small" start :icon="mdiCopyright" class="text-medium-emphasis" />
                  <span class="text-body-2">{{ currentMedia.copyright }}</span>
                </div>
              </div>
            </div>
          </div>

          <v-spacer />

          <!-- Actions Footer -->
          <div class="pa-4 mt-auto">
            <div v-if="hasGallery" class="d-flex ga-2 mb-2">
              <v-btn variant="text" block :prepend-icon="mdiChevronLeft" class="text-none" @click="showPrevious">
                Previous
              </v-btn>
              <v-btn variant="text" block :append-icon="mdiChevronRight" class="text-none" @click="showNext">
                Next
              </v-btn>
            </div>
            <v-btn block color="primary" variant="tonal" :prepend-icon="mdiOpenInNew" @click="openInNewTab">
              Open Original
            </v-btn>
          </div>
        </div>

      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { mdiCamera, mdiChevronLeft, mdiChevronRight, mdiClose, mdiCopyright, mdiHiking, mdiOpenInNew } from '@mdi/js'

import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  media: {
    type: Object,
    default: () => ({})
  },
  // Optional gallery the opened item belongs to. When supplied the modal
  // carousels through it rather than showing a single photo.
  items: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue'])

const dialog = ref(props.modelValue)
const currentIndex = ref(0)

const hasGallery = computed(() => props.items.length > 1)

// Identify a media item across copies — parents often spread extra context
// (trip name, photographer) onto the object they hand us, so identity is not
// reliable but the underlying record is.
const mediaKey = (item) => item?.id ?? item?.url ?? item?.filename ?? null

const currentMedia = computed(() => {
  if (!props.items.length) return props.media || {}
  return props.items[currentIndex.value] || props.media || {}
})

const syncIndexToMedia = () => {
  if (!props.items.length) return
  const key = mediaKey(props.media)
  const found = key === null ? -1 : props.items.findIndex(item => mediaKey(item) === key)
  currentIndex.value = found === -1 ? 0 : found
}

// `immediate` matters: the modal can be mounted already-open (or with the
// gallery arriving in the same tick), and a lazy watcher would leave the
// carousel parked on the first photo instead of the one that was clicked.
watch(() => props.modelValue, (newValue) => {
  dialog.value = newValue
  if (newValue) syncIndexToMedia()
}, { immediate: true })

// The gallery often resolves after the modal mounts (parent still fetching).
watch(() => props.items, () => {
  if (dialog.value) syncIndexToMedia()
})

// A parent that keeps the modal open while swapping `media` should follow along.
watch(() => props.media, () => {
  if (dialog.value) syncIndexToMedia()
})

watch(dialog, (newValue) => {
  emit('update:modelValue', newValue)
})

const step = (delta) => {
  if (!hasGallery.value) return
  const count = props.items.length
  currentIndex.value = (currentIndex.value + delta + count) % count
}

const showNext = () => step(1)
const showPrevious = () => step(-1)

const closeModal = () => {
  dialog.value = false
}

const openInNewTab = () => {
  window.open(currentMedia.value.url, '_blank')
}

// Arrow keys are the reflex for a photo viewer; Escape is Vuetify's own.
const onKeydown = (event) => {
  if (!dialog.value || !hasGallery.value) return
  if (event.key === 'ArrowRight') {
    event.preventDefault()
    showNext()
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault()
    showPrevious()
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('keydown', onKeydown)
  onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
}

defineExpose({ showNext, showPrevious, currentMedia })
</script>

<style scoped>
.media-modal .v-card {
  border-radius: 8px;
}

.media-image {
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.v-list-item {
  min-height: 40px;
}

.media-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 5;
  background-color: rgba(0, 0, 0, 0.35) !important;
  color: #fff;
}

.media-nav--prev {
  left: 8px;
}

.media-nav--next {
  right: 8px;
}

.media-counter {
  position: absolute;
  bottom: 8px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 5;
  padding: 2px 10px;
  border-radius: 12px;
  background-color: rgba(0, 0, 0, 0.55);
  color: #fff;
}
</style>
