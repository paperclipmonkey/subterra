<template>
  <v-card
    :to="'/trips/' + trip.id"
    variant="outlined"
    class="mb-3 rounded-lg border-opacity-90 trip-card"
    hover
  >
    <div class="d-flex flex-row">
      <!-- Thumbnail (Desktop/Tablet) -->
      <div v-if="thumbnail" class="d-none d-sm-block bg-grey-lighten-4" style="width: 140px; min-width: 140px;">
        <v-img :src="thumbnail" cover height="100%" class="h-100">
          <template #placeholder>
            <div class="d-flex align-center justify-center fill-height">
              <v-icon color="grey-lighten-1">mdi-image-outline</v-icon>
            </div>
          </template>
        </v-img>
      </div>

      <div class="flex-grow-1 pa-4 overflow-hidden">
        <div class="d-flex justify-space-between align-start mb-1">
          <h3 class="text-subtitle-1 font-weight-bold text-truncate pr-4">{{ trip.name }}</h3>
          <v-chip v-if="isRecent" size="x-small" color="success" variant="flat" label class="font-weight-bold">
            NEW
          </v-chip>
        </div>

        <div class="d-flex align-center text-caption text-medium-emphasis mb-3 flex-wrap ga-4">
          <div class="d-flex align-center">
            <v-icon size="small" start icon="mdi-calendar" class="mr-1" />
            {{ moment(trip.end_time || trip.start_time).format('MMM D, YYYY') }}
            <span class="ms-1 text-grey">({{ moment(trip.end_time || trip.start_time).fromNow() }})</span>
          </div>
          
          <div v-if="duration" class="d-flex align-center">
            <v-icon size="small" start icon="mdi-clock-outline" class="mr-1" />
            {{ duration }}
          </div>
        </div>

        <div v-if="trip.description" class="text-body-2 mb-3 text-grey-darken-1 line-clamp-2">
          {{ trip.description || 'No description provided.' }}
        </div>

        <div class="d-flex align-center flex-wrap ga-1">
          <v-chip
            v-for="participant in visibleParticipants"
            :key="participant.id"
            size="small"
            variant="flat"
            class="bg-grey-lighten-4"
            prepend-icon="mdi-account-circle-outline"
          >
            {{ participant.name }}
          </v-chip>
          
          <v-chip
            v-if="remainingParticipantsCount > 0"
            size="small"
            variant="text"
            class="text-caption text-grey"
          >
            +{{ remainingParticipantsCount }} more
          </v-chip>
        </div>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import moment from 'moment'
import { computed } from 'vue'

const props = defineProps({
  trip: {
    type: Object,
    required: true
  }
})

const thumbnail = computed(() => {
  if (props.trip.media && props.trip.media.length > 0) {
    return props.trip.media[0].url || props.trip.media[0].filename
  }
  return null
})

const duration = computed(() => {
  if (!props.trip.end_time || !props.trip.start_time) return null
  const diffHours = moment(props.trip.end_time).diff(moment(props.trip.start_time), 'hours')
  if (diffHours < 1) return '< 1 hour'
  return `${diffHours} hour${diffHours !== 1 ? 's' : ''}`
})

const isRecent = computed(() => {
  // Consider trips within the last 7 days as recent
  return moment().diff(moment(props.trip.end_time || props.trip.start_time), 'days') < 7
})

const visibleParticipants = computed(() => {
  return props.trip.participants ? props.trip.participants.slice(0, 5) : []
})

const remainingParticipantsCount = computed(() => {
  return props.trip.participants && props.trip.participants.length > 5
    ? props.trip.participants.length - 5
    : 0
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.trip-card {
  border-color: rgba(0, 0, 0, 0.12);
}
</style>