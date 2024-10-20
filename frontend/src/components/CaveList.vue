<template>
  <v-card
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

    <!-- <v-tabs
      v-model="tab"
      align-tabs="center"
    >
      <v-tab :value="'list'">List</v-tab>
      <v-tab :value="'map'">Map</v-tab>
    </v-tabs> -->
    <!-- <v-tabs-window v-model="tab">
      <v-tabs-window-item
        :key="'list'"
        :value="'list'"> -->

      <v-data-table
        :headers="headers"
        :items="caveStore.caves"
        :search="search"
        :items-per-page="10000"
        hide-default-footer
      >
      <template v-slot:item.length="{ value }">
        <span :title="`${value} m`">{{ Math.round((value / 1000)*10)/10 }} km</span>
      </template>
      <template v-slot:item.depth="{ value }"> {{ value }} m</template>
      <template v-slot:item.name="{ item, value }">
        <router-link :to="{name: '/cave/[id]', params: {id: item.id}}">
          {{ value }}
        </router-link>
      </template>
      <template v-slot:item.location="{ item }">
          {{ item.location.name }}, {{ item.location.country }}
      </template>
      <template v-slot:item.tags="{ value }">
        <v-chip v-for="tag in value" :key="tag.tag" class="ma-1">
          <span :title="tag.description">{{ tag.tag }}</span>
        </v-chip>
      </template>
    </v-data-table>
    <!-- </v-tabs-window-item> -->
    <!-- <v-tabs-window-item
      :key="'map'"
      :value="'map'">
      <v-card>
        <v-card-title>Map</v-card-title>
        <v-card-text>
          Map here!
        </v-card-text>
      </v-card>
    </v-tabs-window-item> -->
  <!-- </v-tabs-window> -->
  </v-card>
</template>

<script setup>
  import { useAppStore } from '@/stores/app'
  import { useCaveStore } from '@/stores/caves';

  const store = useAppStore()  
  const caveStore = useCaveStore()

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
    await caveStore.getList()
  })

  const tab = ref('list')
</script>
