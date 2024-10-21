<template>
  <v-card
    title="My Trips"
    flat
  >
    <template v-slot:text>
      <v-text-field
        v-model="search"
        label="Search"
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        hide-details
        single-line
      >
      </v-text-field>
    </template>

    <v-data-table
      :headers="headers"
      :items="tripStore.trips"
      :search="search"
      :items-per-page="10000"
      hide-default-footer
    >
    <template v-slot:item.name="{ item, value }">
      <router-link :to="{name: '/trip/[id]', params: {id: item.id}}">
        {{ value }}
      </router-link>
    </template>
    <template v-slot:item.end_time="{ value }">
      {{ formatDate(value) }}
    </template>
    <template v-slot:item.participants="{ value }">
      <v-chip density="compact" size="small" v-for="participant in value" :key="participant.id" class="ma-1">
        {{ participant.name }}
      </v-chip>
    </template>
  </v-data-table>
  </v-card>
</template>

<script setup>
  import moment from 'moment'
  import { useAppStore } from '@/stores/app'
  import { useTripStore } from '@/stores/trips';

  const store = useAppStore()  
  const tripStore = useTripStore()
  const search = ref('')
  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'end time', key: 'end_time' },
    // { title: 'system', key: 'system.name' },
    { title: 'entrance', key: 'entrance.name' },
    { title: 'participants', key: 'participants' }
  ])

  const formatDate = (date) => {
    return moment(date).format('YYYY-MM-DD HH:mm')
  }

  onMounted(async () => {
    await store.getUser() // Check if user is logged in
    await tripStore.getTrips()
  })
</script>
