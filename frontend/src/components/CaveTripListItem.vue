<template>
  <v-list-item> <!-- not marked as done -->
    <div>
      <v-list-item-title><RouterLink :to="{name: '/trip/[id]', params: {id: trip.id}}">{{ trip.name }}</RouterLink></v-list-item-title>
      <v-list-item-subtitle>{{ moment(trip.end_time).isValid() ? moment(trip.end_time).fromNow() : '~' }}</v-list-item-subtitle>
      <v-list-item-subtitle>{{ trip.description }}</v-list-item-subtitle>
      <v-list-item-subtitle>Duration: {{ moment(trip.end_time).isValid() ? moment(trip.end_time).diff(trip.start_time, 'hours') : '~' }} hours</v-list-item-subtitle>
      <v-list-item-subtitle>
        <v-chip v-for="participant in trip.participants" :key="participant.id" class="ma-1">
          {{ participant.name }}
        </v-chip>
      </v-list-item-subtitle>
    </div>
  </v-list-item>
</template>
<script setup>
import moment from 'moment'

defineProps({
    trip: {
        type: Object,
        required: true
    }
});
</script>