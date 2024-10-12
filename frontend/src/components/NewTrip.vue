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
      <template v-if="system_entrances_count > 1">
        <v-checkbox v-model="throughTrip" label="Through trip"></v-checkbox>
        <template v-if="throughTrip">
          <v-autocomplete
            label="Exit"
            :items="caves.filter(cave => cave.cave_system_id === cave_system_id)"
            item-title="name"
            item-value="id"
            v-model="trip.exit_cave_id"
          ></v-autocomplete>
          </template>
      </template>
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
  import { computed, reactive, ref } from 'vue'
  const router = useRouter()
  const trip = reactive({
    name: '',
    description: '',
    entrance_cave_id: '',
    exit_cave_id: '',
    date: '',
    entryTime: '',
    exitTime: '',
    // cave_system_id: 1
  })

  const throughTrip = ref(false)

  const caves = ref([])
  onMounted(async () => {
    const response = await fetch('/api/caves')
    caves.value = await response.json()
  })

  const cave_system_id = computed(() => {
    const found = caves.value.find(cave => cave.id === trip.entrance_cave_id)
    return found ? found.cave_system_id : null
  })

  const system_entrances_count = computed(() => {
    if(!cave_system_id.value) return 0
    return caves.value.filter((cave => cave.cave_system_id === cave_system_id.value)).length
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
