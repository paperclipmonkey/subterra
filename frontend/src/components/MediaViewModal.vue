<template>
  <v-dialog v-model="dialog" max-width="800px" class="media-modal">
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span>Media Details</span>
        <v-btn icon @click="closeModal">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>
      
      <v-card-text class="pa-0">
        <v-img
          :src="media.url"
          :alt="media.filename"
          max-height="500"
          contain
          class="media-image"
        >
          <template v-slot:placeholder>
            <v-row
              class="fill-height ma-0"
              align="center"
              justify="center"
            >
              <v-progress-circular
                indeterminate
                color="grey-lighten-5"
              ></v-progress-circular>
            </v-row>
          </template>
        </v-img>
      </v-card-text>

      <v-card-text>
        <v-row>
          <v-col cols="12">
            <h3 class="text-h6 mb-3">Media Information</h3>
            <v-list density="compact">
              <v-list-item v-if="media.taken_at">
                <template v-slot:prepend>
                  <v-icon>mdi-calendar</v-icon>
                </template>
                <v-list-item-title>When</v-list-item-title>
                <v-list-item-subtitle>{{ formatDate(media.taken_at) }}</v-list-item-subtitle>
              </v-list-item>

              <v-list-item v-if="media.photographer">
                <template v-slot:prepend>
                  <v-icon>mdi-camera</v-icon>
                </template>
                <v-list-item-title>Photographer</v-list-item-title>
                <v-list-item-subtitle>{{ media.photographer }}</v-list-item-subtitle>
              </v-list-item>

              <v-list-item v-if="media.copyright">
                <template v-slot:prepend>
                  <v-icon>mdi-copyright</v-icon>
                </template>
                <v-list-item-title>Copyright</v-list-item-title>
                <v-list-item-subtitle>{{ media.copyright }}</v-list-item-subtitle>
              </v-list-item>

              <v-list-item>
                <template v-slot:prepend>
                  <v-icon>mdi-file</v-icon>
                </template>
                <v-list-item-title>Filename</v-list-item-title>
                <v-list-item-subtitle>{{ media.filename }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-col>
        </v-row>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn color="primary" @click="openInNewTab">
          <v-icon left>mdi-open-in-new</v-icon>
          Open in New Tab
        </v-btn>
        <v-btn @click="closeModal">Close</v-btn>
      </v-card-actions>
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
  border-bottom: 1px solid rgba(0,0,0,0.1);
}

.v-list-item {
  min-height: 40px;
}
</style>