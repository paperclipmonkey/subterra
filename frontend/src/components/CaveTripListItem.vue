<template>
  <v-card
    :to="isMarkedAsDoneCTA ? `/trips/${trip.id}/edit` : `/trips/${trip.id}`"
    variant="outlined"
    :color="isMarkedAsDoneCTA ? 'primary' : undefined"
    :class="['mb-3 rounded-lg border-opacity-90 trip-card', { 'bg-blue-lighten-5': isMarkedAsDoneCTA }]"
    hover
  >
    <div class="d-flex flex-row">
      <!-- Thumbnail (Desktop/Tablet) -->
      <div v-if="thumbnail" class="d-none d-sm-block bg-grey-lighten-4" style="width: 140px; min-width: 140px;">
        <v-img :src="thumbnail" cover height="100%" class="h-100">
          <template #placeholder>
            <div class="d-flex align-center justify-center fill-height">
              <v-icon color="grey-lighten-1" :icon="mdiImageOutline" />
            </div>
          </template>
        </v-img>
      </div>

      <div class="flex-grow-1 pa-4 overflow-hidden">
        <div class="d-flex justify-space-between align-start mb-1">
          <h3 class="text-subtitle-1 font-weight-bold text-truncate pr-4">
            {{ trip.name }}
            <span v-if="isMarkedAsDoneCTA" class="text-primary text-body-2 font-weight-medium ms-2">— Complete Trip Report</span>
          </h3>
          <v-chip v-if="isRecent" size="x-small" color="success" variant="flat" label class="font-weight-bold">
            NEW
          </v-chip>
        </div>

        <div v-if="!isMarkedAsDoneCTA" class="d-flex align-center text-caption text-medium-emphasis mb-3 flex-wrap ga-4">
          <div class="d-flex align-center">
            <v-icon size="small" start :icon="mdiCalendar" class="mr-1" />
            <span :class="{ 'text-error font-italic': formattedDate === 'Unknown date' }">{{ formattedDate }}</span>
            <span v-if="fromNow" class="ms-1 text-grey">({{ fromNow }})</span>
          </div>
          
          <div v-if="duration" class="d-flex align-center">
            <v-icon size="small" start :icon="mdiClockOutline" class="mr-1" />
            {{ duration }}
          </div>
        </div>

        <div v-if="trip.description" class="text-body-2 mb-3 text-grey-darken-1 line-clamp-2">
          {{ trip.description }}
        </div>
        <div v-else-if="isMarkedAsDoneCTA" class="text-body-2 mb-3 text-primary font-weight-medium d-flex align-center">
          <v-icon size="small" start :icon="mdiPlus" class="mr-1" />
          Click here to add a description and photos to your trip report
        </div>
        <div v-else class="text-body-2 mb-3 text-grey-darken-1 line-clamp-2">
          No description provided.
        </div>

        <div class="d-flex align-center flex-wrap ga-ga-1">
          <v-chip
            v-for="participant in visibleParticipants"
            :key="participant.id"
            size="small"
            variant="flat"
            :class="[isMarkedAsDoneCTA ? 'bg-blue-lighten-4' : 'bg-grey-lighten-4']"
            :prepend-icon="mdiAccountCircleOutline"
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
import { mdiAccountCircleOutline, mdiCalendar, mdiClockOutline, mdiImageOutline, mdiPlus } from '@mdi/js'
import moment from 'moment'
import { computed } from 'vue'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  trip: {
    type: Object,
    required: true
  }
})

const appStore = useAppStore()

const thumbnail = computed(() => {
  if (props.trip.media && props.trip.media.length > 0) {
    return props.trip.media[0].url || props.trip.media[0].filename
  }
  return null
})

const formattedDate = computed(() => {
  const dateStr = props.trip.end_time || props.trip.start_time
  if (!dateStr) return 'Unknown date'
  const m = moment(dateStr)
  return m.isValid() ? m.format('MMM D, YYYY') : 'Unknown date'
})

const fromNow = computed(() => {
  const dateStr = props.trip.end_time || props.trip.start_time
  if (!dateStr) return null
  const m = moment(dateStr)
  return m.isValid() ? m.fromNow() : null
})

const duration = computed(() => {
  if (!props.trip.end_time || !props.trip.start_time) return null
  const start = moment(props.trip.start_time)
  const end = moment(props.trip.end_time)
  if (!start.isValid() || !end.isValid()) return null

  const diffHours = end.diff(start, 'hours')
  if (diffHours < 1) return '< 1 hour'
  return `${diffHours} hour${diffHours !== 1 ? 's' : ''}`
})

const isRecent = computed(() => {
  // Consider trips within the last 7 days as recent
  const dateStr = props.trip.end_time || props.trip.start_time
  if (!dateStr) return false
  const m = moment(dateStr)
  if (!m.isValid()) return false
  return moment().diff(m, 'days') < 7
})

const isMarkedAsDoneCTA = computed(() => {
  if (!appStore.user?.id) return false
  // A trip is a CTA if it's "Marked as Done" (implied by name or lack of duration/description)
  // and owned by the current user
  const isOwner = props.trip.participants?.some(p => p.id === appStore.user.id)
  const isMarkedAsDoneName = props.trip.name === 'Marked as Done'
  const hasNoDescription = !props.trip.description

  return isOwner && isMarkedAsDoneName && hasNoDescription
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