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
      :items-per-page="10000"
      hide-default-footer
    >
    <template v-slot:item.length="{ value }">
      <span :title="`${value} m`">{{ Math.round((value / 1000)*10)/10 }} km</span>
    </template>
    <template v-slot:item.depth="{ value }"> {{ value }} m</template>
    <template v-slot:item.name="{ item, value }">
      <v-chip :to="{name: '/cave/[id]', params: {id: item.id}}">
        {{ value }}
      </v-chip>
    </template>
    <template v-slot:item.location="{ item }">
      <v-chip>
        {{ item.location.name }}, {{ item.location.country }}
      </v-chip>
    </template>
    <template v-slot:item.tags="{ value }">
      <v-chip v-for="tag in value" :key="tag.tag" class="ma-1">
        <span :title="tag.description">{{ tag.tag }}</span>
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
    { title: 'Location', key: 'location' },
    { title: 'Tags', key: 'tags' }
  ])

  const caves = ref([])
  onMounted(async () => {
    const response = await fetch('/api/caves')
    caves.value = (await response.json()).data
  })
</script>
