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
      <v-row>
        <v-col cols="6">
          <v-text-field
            v-model="tripStartDate"
            label="Date"
            type="date"
            required
          ></v-text-field>
        </v-col>
        <v-col cols="6">
          <v-text-field
            v-model="tripStartTime"
            label="Entry time"
            type="time"
            required>
          </v-text-field>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="6">
          <v-text-field
            v-model="tripDurationHours"
            label="Duration (hours)"
            type="number"
            min="0"
            required
          ></v-text-field>
        </v-col>
        <v-col cols="6">
          <v-text-field
            v-model="tripDurationMinutes"
            label="Duration (minutes)"
            type="number"
            min="0"
            max="59"
            required
          ></v-text-field>
        </v-col>
      </v-row>
      {{ start_time }}
      {{ end_time }}
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
    start_time: '',
    end_time: '',
    // cave_system_id: 1
  })

  const tripStartDate = ref(moment().format('YYYY-MM-DD'))
  const tripStartTime = ref(moment().format('HH:mm'))
  const tripDurationHours = ref(4)
  const tripDurationMinutes = ref(0)

  const throughTrip = ref(false)

  const caves = ref([])
  onMounted(async () => {
    const response = await fetch('/api/caves')
    caves.value = (await response.json()).data
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

  const start_time = computed(() => {
    const entry = moment(tripStartDate.value + ' ' + tripStartTime.value, 'YYYY-MM-DD HH:mm')
    return entry
  })

  const end_time = computed(() => {
    const exit = start_time.value.clone()
    exit.add(tripDurationHours.value, 'hours')
    exit.add(tripDurationMinutes.value, 'minutes')
    return exit
  })
  
  const submitForm = async () => {
    trip.exit_cave_id = trip.entrance_cave_id
    trip.start_time = `${tripStartDate.value} ${tripStartTime.value}`
    trip.end_time = end_time.value.format('YYYY-MM-DD HH:mm')
    trip.cave_system_id = cave_system_id.value
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
