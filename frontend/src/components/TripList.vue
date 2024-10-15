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
      :items="trips"
      :search="search"
      hide-default-footer
    >
    <template v-slot:item.name="{ item, value }">
      <router-link :to="{name: '/trip/[id]', params: {id: item.id}}">
        {{ value }}
      </router-link>
    </template>
  </v-data-table>
  </v-card>
</template>

<script setup>
  const search = ref('')
  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'end time', key: 'end_time' },
    { title: 'system', key: 'system.name' },
    { title: 'entrance', key: 'entrance.name' }
  ])

  const trips = ref([])
  onMounted(async () => {
    const response = await fetch(`/api/me/trips`)
    trips.value = (await response.json()).data
  })
</script>
