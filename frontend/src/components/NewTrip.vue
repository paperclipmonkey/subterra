<template>
  <v-container class="pa-4">
    <v-form class="pa-xl-4">
      <v-text-field
        v-model="trip.name"
        label="Trip Name"
        required
      ></v-text-field>
      <v-textarea
        v-model="trip.description"
        label="Trip Description"
        required
      ></v-textarea>
      <v-autocomplete
        label="Location"
        :items="caves"
        item-title="name"
        item-value="id"
        v-model="trip.entrance_cave_id"
      ></v-autocomplete>
      <v-autocomplete
        label="Participants"
        :items="users"
        item-title="name"
        item-value="id"
        multiple
        v-model="trip.participants"
      ></v-autocomplete>
      <template v-if="tripEntryLocation && caves[tripEntryLocation].system">
      Was this trip a through trip? 
    </template>
      <v-text-field
        v-model="trip.tripDate"
        label="Date"
        type="date"
        required
      ></v-text-field>
      <v-text-field
        v-model="trip.entryTime"
        label="Entry time"
        type="time"
        required></v-text-field>
      <v-text-field
        v-model="exitTime"
        label="Exit time"
        type="time"
        required></v-text-field>
      {{ tripDuration }} minutes
      <v-btn @click="submitForm">Save</v-btn>
    </v-form>
  </v-container>
</template>

<script setup>
  import moment from 'moment'
  import { reactive, ref } from 'vue'
  const router = useRouter()
  const trip = reactive({
    name: '',
    description: '',
    entrance_cave_id: '',
    exit_cave_id: '',
    date: '',
    entryTime: '',
    exitTime: '',
    cave_system_id: 1
  })

  const caves = ref([])
  onMounted(async () => {
    const response = await fetch('/api/caves')
    caves.value = await response.json()
  })

  const users = ref([])
  onMounted(async () => {
    const response = await fetch('/api/users')
    users.value = (await response.json()).data
  })

  const tripDuration = computed(() => {
    console.log(trip.entryTime, trip.exitTime)
    const entry = moment(trip.entryTime.value, 'HH:mm')
    const exit = moment(trip.exitTime.value, 'HH:mm')
    return exit.diff(entry, 'minutes')
  })
  
  const submitForm = async () => {
    console.log('submitting form')
    console.log(trip)
    trip.exit_cave_id = trip.entrance_cave_id
    // trip.value.system_id = caves.value[this.trip.entryLocation].system.id
    const response = await fetch('/api/trips', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(trip)
    })
    if (response.ok) {
      console.log('trip saved')
      const savedTrip = (await response.json()).data;
      console.log(savedTrip)
      router.push({ name: '/trip/[id]', params: { id: savedTrip.id } });
    } else {
      console.error('failed to save trip')
    }
  }
</script>
