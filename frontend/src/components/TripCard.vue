<template>
  <v-card :to="`/trips/${trip.id}`" elevation="2" class="fill-height d-flex flex-column trip-card rounded-lg overflow-hidden" hover>
    <!-- Image header (trip media, cave hero, or cave entrance) -->
    <template v-if="cardImage">
      <v-img
        :src="cardImage"
        cover
        height="200"
        class="align-end"
        gradient="to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.8)"
      >
        <div class="pa-4 text-white">
          <div class="d-flex align-center justify-space-between mb-1 opacity-80">
            <div class="text-caption font-weight-bold">
              {{ formatDate(trip.start_time) }}
            </div>
            <div v-if="trip.duration" class="d-flex align-center text-caption font-weight-bold">
              <v-icon size="small" icon="mdi-clock-outline" class="mr-1"></v-icon>
              {{ formatDuration(trip.duration) }}
            </div>
          </div>
          <h3 class="text-h6 font-weight-bold lh-tight mb-1">
            {{ trip.name }}
          </h3>
          <div class="text-body-2 opacity-90">
            {{ trip.entrance?.name || 'Unknown Entrance' }}
          </div>
        </div>
      </v-img>
    </template>

    <!-- Fallback Gradient header -->
    <template v-else>
      <v-sheet
        height="200"
        class="d-flex align-end"
        :class="getGradientClass(trip.id)"
      >
        <div class="pa-4 text-white w-100">
          <div class="d-flex align-center justify-space-between mb-1 opacity-80">
            <div class="text-caption font-weight-bold">
              {{ formatDate(trip.start_time) }}
            </div>
            <div v-if="trip.duration" class="d-flex align-center text-caption font-weight-bold">
              <v-icon size="small" icon="mdi-clock-outline" class="mr-1"></v-icon>
              {{ formatDuration(trip.duration) }}
            </div>
          </div>
          <h3 class="text-h6 font-weight-bold lh-tight mb-1">
            {{ trip.name }}
          </h3>
          <div class="text-body-2 opacity-90">
            {{ trip.entrance?.name || 'Unknown Entrance' }}
          </div>
        </div>
      </v-sheet>
    </template>

    <div v-if="!cardImage && trip.description" class="px-4 pt-4 pb-2 text-body-2 text-grey-darken-1 line-clamp-3">
      {{ trip.description }}
    </div>

    <v-divider class="mt-auto"></v-divider>

    <!-- Participants footer -->
    <div class="pa-3 bg-grey-lighten-5">
      <div class="d-flex align-center flex-wrap ga-1">
        <v-icon size="small" color="grey" icon="mdi-account-group" class="mr-1"></v-icon>
        <span v-if="!trip.participants || trip.participants.length === 0" class="text-caption text-grey">No
          participants</span>
        <template v-else>
          <v-chip v-for="(participant, i) in trip.participants.slice(0, 3)" :key="participant.id"
            size="x-small" variant="flat" class="bg-white" border>
            {{ participant.name }}
          </v-chip>
          <v-chip v-if="trip.participants.length > 3" size="x-small" variant="flat"
            class="bg-grey-lighten-3">
            +{{ trip.participants.length - 3 }}
          </v-chip>
        </template>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  trip: { type: Object, required: true },
  formatDate: { type: Function, required: true },
  formatDuration: { type: Function, required: true },
  getGradientClass: { type: Function, required: true },
})

// Fallback chain: trip media → cave hero image → cave entrance image → null (gradient)
const cardImage = computed(() => {
  if (props.trip.media && props.trip.media.length > 0) {
    return props.trip.media[0].url || props.trip.media[0].filename
  }
  if (props.trip.entrance_hero_image) {
    return props.trip.entrance_hero_image
  }
  if (props.trip.entrance_entrance_image) {
    return props.trip.entrance_entrance_image
  }
  return null
})
</script>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.gradient-0 {
  background: linear-gradient(135deg, #1A2980 0%, #26D0CE 100%);
}

.gradient-1 {
  background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
}

.gradient-2 {
  background: linear-gradient(135deg, #0f2027 0%, #2c5364 100%);
}

.gradient-3 {
  background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
}

.gradient-4 {
  background: linear-gradient(135deg, #3f2b96 0%, #a8c0ff 100%);
}
</style>
