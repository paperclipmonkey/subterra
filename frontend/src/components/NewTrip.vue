<template>
  <v-form>
    <v-text-field
      v-model="tripName"
      label="Trip Name"
      required
    ></v-text-field>
    <v-autocomplete
      label="Location"
      :items="caves"
    ></v-autocomplete>
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
</template>

<script setup>
import moment from 'moment'
import { reactive, ref } from 'vue';
const tripName = reactive('')
const tripLocation = reactive('')
const tripDate = reactive('')
const entryTime = ref('')
const exitTime = ref('')
  const caves = reactive([
    'Carlsbad Caverns',
    'Mammoth Cave',
    'Lechugiulla Cave'
  ])
  
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
