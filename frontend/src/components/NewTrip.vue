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
        v-model="trip.entryLocation"
      ></v-autocomplete>
      <template v-if="tripEntryLocation && caves[tripEntryLocation].system">
      Was this trip a through trip? 
    </template>
      <v-text-field
        v-model="tripDate"
        label="Date"
        type="date"
        required
      ></v-text-field>
      <v-text-field
        v-model="entryTime"
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
  import { reactive, ref } from 'vue';
  const trip = reactive({
    name: '',
    description: '',
    entryLocation: '',
    exitLocation: '',
    date: '',
    entryTime: '',
    exitTime: '',
  })
  const tripName = reactive('')
  const tripEntryLocation = reactive('')
  const tripExitLocation = reactive('')
  const tripDate = reactive('')
  const entryTime = ref('')
  const exitTime = ref('')
  const caves = ref([])
  onMounted(async () => {
    const response = await fetch('/api/caves')
    caves.value = await response.json()
  })

  const tripDuration = computed(() => {
    console.log(entryTime, exitTime)
    const entry = moment(entryTime.value, 'HH:mm')
    const exit = moment(exitTime.value, 'HH:mm')
    return exit.diff(entry, 'minutes')
  })
  
  const submitForm = () => {
    console.log('submitting form')
  }
</script>
