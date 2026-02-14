<template>
  <v-card
    flat
  >
    <template #text>
      <div class="d-flex align-center">
        <v-text-field
          v-model="search"
          label="Search"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          hide-details
          single-line
          class="flex-grow-1 mr-2"
        >
          <template #append-inner>
            <v-icon
              icon="mdi-filter"
              :color="cachedTags.length ? 'success' : ''"
              @click="showFilterByTagModal = true"
            />
            <template v-if="cachedTags.length">
              {{ cachedTags.length }}
            </template>
          </template>
        </v-text-field>
      </div>
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
        <CaveListList />
      </v-tabs-window-item>
      <v-tabs-window-item
        :key="'map'"
        :value="'map'">
        <CaveListMap />
      </v-tabs-window-item>
    </v-tabs-window>
    <FilterByTagModal :is-active="showFilterByTagModal" @close="showFilterByTagModal=false" @filter="applyFilter" />
  </v-card>
</template>

<script setup>
import { useCaveStore } from '@/stores/caves'
import FilterByTagModal from './FilterByTagModal.vue'
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const caveStore = useCaveStore()

const route = useRoute()
const router = useRouter()

// Initialize search from query parameter
const search = ref(route.query.search || '')
const catchmentId = ref(route.query.catchment || null)

const showFilterByTagModal = ref(false)

let cachedTags = ref([])

const applyFilter = (tags) => {
  cachedTags.value = tags
  caveStore.applyFilters(tags, search.value, catchmentId.value)
  // Update URL with tags as a comma-separated string
  router.replace({ query: { ...route.query, tags: tags.join(',') } })
  showFilterByTagModal.value = false
}

watch(search, (newSearch) => {
  caveStore.applyFilters(cachedTags, newSearch, catchmentId.value)
  // Update URL with current search
  router.replace({ query: { ...route.query, search: newSearch } })
})

const tab = ref(route.query.view || 'list')

// Update URL when tab changes
watch(tab, (newTab) => {
  router.replace({ query: { ...route.query, view: newTab } })
})

// Watch for route query changes for catchment (deep linking support)
watch(() => route.query.catchment, (newCatchment) => {
  catchmentId.value = newCatchment
  caveStore.applyFilters(cachedTags, search.value, newCatchment)
})

onMounted(async () => {
  // Ensure search and view parameters are applied on reload
  search.value = route.query.search || ''
  tab.value = route.query.view || 'list'
  const tags = route.query.tags ? route.query.tags.split(',') : []
  catchmentId.value = route.query.catchment || null

  cachedTags.value = tags
  await caveStore.getList()

  // Apply filters after list is loaded
  caveStore.applyFilters(tags, search.value, catchmentId.value)
})
</script>
