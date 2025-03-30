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
      >
        <template v-slot:append-inner>
          <v-icon
          @click="showFilterByTagModal = true"
            icon="mdi-filter"
          />
        </template>
      </v-text-field>
    </template>

    <v-tabs
      v-model="tab"
      align-tabs="center"
    >
      <v-tab :value="'list'">List</v-tab>
      <v-tab :value="'map'">Map</v-tab>
    </v-tabs>
    <v-tabs-window v-model="tab">
      <v-tabs-window-item
        :key="'list'"
        :value="'list'">

    <v-data-table
      :headers="headers"
      :items="caveStore.caves"
      :search="search"
      :items-per-page="10000"
      :loading="caveStore.loading"
      hide-default-footer
    >
      <template v-slot:loading>
        <v-skeleton-loader type="table-row@10"></v-skeleton-loader>
      </template>
      <template v-slot:item.system.length="{ value }">
        <span :title="`${value} m`">{{ Math.round((value / 1000)*10)/10 }} km</span>
      </template>
      <template v-slot:item.system.vertical_range="{ value }"> {{ value }} m</template>
      <template v-slot:item.name="{ item, value }">
        <router-link :to="{name: '/cave/[id]', params: {id: item.slug}}">
          {{ value }}
        </router-link>
      </template>
      <template v-slot:item.location="{ item }">
          {{ item.location_name }}, {{ item.location_country }}
      </template>
      <template v-slot:item.tags="{ value }">
        <v-chip v-for="tag in value" :key="tag.tag" class="ma-1">
          <span :title="tag.description">{{ tag.tag }}</span>
        </v-chip>
      </template>
      <template v-slot:item.previously_done="{ value }">
        <v-icon :color="value ? 'green' : 'red'">{{ value ? 'mdi-check' : 'mdi-close' }}</v-icon>
      </template>
    </v-data-table>
    </v-tabs-window-item>
    <v-tabs-window-item
      :key="'map'"
      :value="'map'">
        <CaveListMap/>
    </v-tabs-window-item>
  </v-tabs-window>
  <FilterByTagModal @close="showFilterByTagModal = false" @filter="applyFilter" :isActive="showFilterByTagModal"/>
  </v-card>
</template>

<script setup>
  import { useCaveStore } from '@/stores/caves';
  import FilterByTagModal from './FilterByTagModal.vue';

  const caveStore = useCaveStore()

  const showFilterByTagModal = ref(false)
  const search = ref('')
  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'Length', key: 'system.length' },
    { title: 'Vertical Range', key: 'system.vertical_range' },
    { title: 'Location', key: 'location' },
    { title: 'Previously Done', key: 'previously_done' },
    // { title: 'Tags', key: 'tags' }
  ])

  const applyFilter = (tags) => {
    console.log(tags)
    caveStore.applyFilter(tags)
    showFilterByTagModal.value = false
  }

  onMounted(async () => {
    await caveStore.getList()
  })

  const tab = ref('list')
</script>
