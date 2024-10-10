<template>
  <v-card
    title="Caves"
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
    >
    <template v-slot:item.name="{ value }">
      <v-chip :to="{name: '/cave/[id]', params: {id: value}}">
        {{ value }}
      </v-chip>
    </template>
  </v-data-table>
  </v-card>
</template>

<script setup>
  const search = ref('')
  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'Length', key: 'length' },
    { title: 'Depth', key: 'depth' },
    { title: 'Location', key: 'location' }
  ])

  // Load the list of caves dynamically from the url http://localhost/api/caves
  const caves = ref([])
  onMounted(async () => {
    const response = await fetch('/api/caves')
    caves.value = await response.json()
  })
</script>
