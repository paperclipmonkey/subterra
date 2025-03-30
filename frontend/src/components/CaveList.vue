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
        <CaveListList/>
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

  const applyFilter = (tags) => {
    console.log(tags)
    caveStore.applyFilter(tags)
    showFilterByTagModal.value = false
  }

  watch(search, (newSearch) => {
    caveStore.applySearch(newSearch)
  })

  onMounted(async () => {
    await caveStore.getList()
  })

  const tab = ref('list')
</script>
