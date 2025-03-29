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
    <template v-if="tripStore.loading">
      <v-card-text>
        <v-progress-circular
            indeterminate
            size="64"
            class="mx-auto my-4 d-flex justify-center"
          color="primary"
        ></v-progress-circular>
      </v-card-text>
    </template>
    <template v-else>
      <template v-if="tripStore.trips.length === 0">
        <v-card-text>
          <p>You don't seem to have been on any trips yet.</p>
          <router-link to="/caves">Find a cave to explore</router-link>, or <router-link to="/create-trip">Create a new trip report</router-link>
        </v-card-text>
      </template>
      <template v-else>
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
          <template v-slot:item.start_time="{ value }">
            {{ formatDate(value) }}
          </template>
          <template v-slot:item.participants="{ value }">
            <v-chip density="compact" size="small" v-for="participant in value" :key="participant.id" class="ma-1">
              {{ participant.name }}
            </v-chip>
          </template>
        </v-data-table>
      </template>
    </template>
  </v-card>
</template>

<script setup>
  import moment from 'moment'
  import { useAppStore } from '@/stores/app'
  import { useTripStore } from '@/stores/trips';
import { parse } from 'vue/compiler-sfc';

  const store = useAppStore()  
  const tripStore = useTripStore()
  const search = ref('')
  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'start time', key: 'start_time' },
    // { title: 'system', key: 'system.name' },
    { title: 'entrance', key: 'entrance.name' },
    { title: 'participants', key: 'participants' }
  ])

  const formatDate = (date) => {
    let parsedDate = moment(date);
    return parsedDate.isValid() ? parsedDate.format('YYYY-MM-DD HH:mm') : '~'
  }

  onMounted(async () => {
    await store.getUser() // Check if user is logged in
    await tripStore.getTrips()
  })
</script>
