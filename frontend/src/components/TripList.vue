<template>
  <v-card
    title="Trips"
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
      ></v-text-field>
    </template>

    <v-data-table
      :headers="headers"
      :items="caves"
      :search="search"
      hide-default-footer
    ></v-data-table>
  </v-card>
</template>

<script setup>
  const search = ref('')
  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'Length', key: 'length' },
  ])

  const caves = ref([
    {
      name: 'Carlsbad Caverns',
      length: '33.5 miles',
      depth: '1,597 feet',
      location: 'New Mexico'
    },
    {
      name: 'Mammoth Cave',
      length: '400 miles',
      depth: '456 feet',
      location: 'Kentucky'
    },
    {
      name: 'Lechuguilla Cave',
      length: '138.3 miles',
      depth: '1,604 feet',
      location: 'New Mexico'
    },
    {
      name: 'Jewel Cave',
      length: '202.6 miles',
      depth: '669 feet',
      location: 'South Dakota'
    },
    {
      name: 'Wind Cave',
      length: '149.5 miles',
      depth: '723 feet',
      location: 'South Dakota'
    }
  ])
</script>
