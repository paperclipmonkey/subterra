<template>
  <v-dialog v-model="dialog" max-width="1200px" scrollable class="media-modal">
    <v-card height="100%" max-height="90vh" class="d-flex flex-column">
      <div class="d-flex flex-grow-1 flex-column flex-md-row overflow-hidden">
        
        <!-- Image Area (Left/Top) -->
        <div class="bg-black d-flex align-center justify-center flex-grow-1 overflow-hidden position-relative" style="min-height: 300px; flex-basis: 70%;">
          <v-btn icon="mdi-close" variant="text" color="white" class="position-absolute top-0 right-0 ma-2 d-md-none" style="z-index: 10" @click="closeModal"></v-btn>
          
          <v-img
            :src="media.url"
            :alt="media.filename"
            max-height="100%"
            max-width="100%"
            contain
            class="media-image"
          >
            <template v-slot:placeholder>
              <div class="d-flex align-center justify-center fill-height">
                <v-progress-circular indeterminate color="grey-lighten-5"></v-progress-circular>
              </div>
            </template>
          </v-img>
        </div>

        <!-- Sidebar / Details Area (Right/Bottom) -->
        <div class="d-flex flex-column bg-surface border-s" style="flex-basis: 30%; min-width: 300px; max-width: 100%; overflow-y: auto;">
            
            <!-- Header (Desktop only close button) -->
            <div class="d-none d-md-flex justify-space-between align-center pa-4 pb-2">
                <div class="text-overline text-medium-emphasis">Media Details</div>
                <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal"></v-btn>
            </div>

            <v-divider class="d-none d-md-block mb-2"></v-divider>

            <div class="pa-4">
                <!-- Title -->
                <h2 v-if="media.title" class="text-h5 font-weight-bold mb-4">{{ media.title }}</h2>
                <div v-else class="text-h6 font-weight-regular text-medium-emphasis font-italic mb-4">No Title</div>

                <!-- Metadata Grid -->
                <div class="d-flex flex-column gap-3">
                    
                    <!-- Trip -->
                    <div v-if="media.trip_name" class="mb-3">
                        <div class="text-caption text-medium-emphasis mb-1">Trip</div>
                        <div class="d-flex align-center">
                            <v-icon size="small" start icon="mdi-hiking" color="primary"></v-icon>
                            <router-link :to="`/trips/${media.trip_id}`" @click="closeModal" class="text-decoration-none text-body-1 font-weight-medium text-primary text-truncate">
                                {{ media.trip_name }}
                            </router-link>
                        </div>
                    </div>

                    <!-- Photographer -->
                    <div v-if="media.photographer" class="mb-3">
                        <div class="text-caption text-medium-emphasis mb-1">Photographer</div>
                        <div class="d-flex align-center">
                            <v-icon size="small" start icon="mdi-camera" class="text-medium-emphasis"></v-icon>
                            <span class="text-body-1">{{ media.photographer }}</span>
                        </div>
                    </div>

                    <!-- Taken At (Keeping hidden as requested, but structure allows easy add-back) -->
                    
                    <!-- Copyright -->
                    <div v-if="media.copyright" class="mb-3">
                        <div class="text-caption text-medium-emphasis mb-1">Copyright</div>
                        <div class="d-flex align-center">
                            <v-icon size="small" start icon="mdi-copyright" class="text-medium-emphasis"></v-icon>
                            <span class="text-body-2">{{ media.copyright }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <v-spacer></v-spacer>

            <!-- Actions Footer -->
            <div class="pa-4 mt-auto">
                <v-btn block color="primary" variant="tonal" prepend-icon="mdi-open-in-new" @click="openInNewTab">
                    Open Original
                </v-btn>
            </div>
        </div>

      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import moment from 'moment'

const props = defineProps({
  modelValue: Boolean,
  media: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

const dialog = ref(props.modelValue)

watch(() => props.modelValue, (newValue) => {
  dialog.value = newValue
})

watch(dialog, (newValue) => {
  emit('update:modelValue', newValue)
})

const closeModal = () => {
  dialog.value = false
}

const openInNewTab = () => {
  window.open(props.media.url, '_blank')
}

const formatDate = (date) => {
  if (!date) return 'Not specified'
  return moment(date).format('DD-MM-YYYY HH:mm')
}
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
</style>