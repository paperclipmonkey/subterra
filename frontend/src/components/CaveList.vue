<template>
  <v-card
    flat
  >
    <template #text>
      <div class="d-flex align-center mb-2">
        <v-text-field
          v-model="search"
          placeholder="Search by name, system, or location..."
          prepend-inner-icon="mdi-magnify"
          variant="solo-filled"
          flat
          hide-details
          class="flex-grow-1"
          density="comfortable"
          rounded="lg"
        >
          <template #append-inner>
            <div class="cursor-pointer d-flex align-center" @click="showFilterByTagModal = true">
              <v-badge
                v-if="cachedTags.length"
                color="primary"
                :content="cachedTags.length"
                offset-x="4"
                offset-y="4"
              >
                <v-icon icon="mdi-filter-variant" density="comfortable" />
              </v-badge>
              <v-icon
                v-else
                icon="mdi-filter-outline"
                density="comfortable"
              />
            </div>
          </template>
        </v-text-field>
      </div>

      <!-- Compact Horizontal Filter Bar -->
      <div class="filter-bar-container px-1 mt-n1">
        <div class="d-flex align-center overflow-x-auto no-scrollbar py-0 ga-2">
          <!-- Active Tags -->
          <v-chip
            v-for="tag in cachedTags"
            :key="'active-' + tag"
            closable
            size="small"
            color="primary"
            class="flex-shrink-0"
            variant="flat"
            @click:close="toggleTag(tag)"
          >
            {{ tag }}
          </v-chip>

          <v-divider v-if="cachedTags.length > 0" vertical class="mx-1" style="height: 24px" />

          <!-- Category Buttons -->
          <v-chip
            v-for="(groupItems, groupName) in tagsAvailable"
            :key="groupName"
            size="small"
            variant="tonal"
            class="flex-shrink-0 text-capitalize"
            @click="openCategoryFilter(groupName)"
          >
            {{ groupName }}
            <v-icon end icon="mdi-chevron-down" size="x-small" />
          </v-chip>
          
          <v-btn
            v-if="cachedTags.length > 0"
            variant="text"
            size="x-small"
            color="grey-darken-1"
            class="flex-shrink-0 text-none"
            @click="clearAllFilters"
          >
            Clear All
          </v-btn>
        </div>
      </div>
    </template>

    <v-tabs
      v-model="tab"
      align-tabs="center"
      density="compact"
      class="mt-n1"
    >
      <v-tab :value="'list'">List</v-tab>
      <v-tab :value="'map'">Map</v-tab>
    </v-tabs>
    <v-tabs-window v-model="tab">
      <v-tabs-window-item value="list">
        <CaveListList @tag-click="toggleTag" />
      </v-tabs-window-item>
      <v-tabs-window-item value="map">
        <CaveListMap />
      </v-tabs-window-item>
    </v-tabs-window>
    <FilterByTagModal 
      :is-active="showFilterByTagModal" 
      :target-category="targetCategory"
      @close="closeModal" 
      @filter="applyFilter" 
    />
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

// Initialize search and tags from query parameter
const search = ref(route.query.search || '')
const catchmentId = ref(route.query.catchment || null)

const showFilterByTagModal = ref(false)
const targetCategory = ref(null)
const tagsAvailable = ref({})

const cachedTags = ref([])

const fetchTags = async () => {
  try {
    const response = await fetch('/api/tags')
    tagsAvailable.value = await response.json()
  } catch (error) {
    console.error('Failed to fetch tags:', error)
  }
}

const applyFilter = (tags) => {
  cachedTags.value = tags
  caveStore.applyFilters(tags, search.value, catchmentId.value)
  // Update URL with tags as a comma-separated string
  router.replace({ query: { ...route.query, tags: tags.length ? tags.join(',') : undefined } })
  showFilterByTagModal.value = false
}

const toggleTag = (tag) => {
  const tags = [...cachedTags.value]
  const index = tags.indexOf(tag)
  if (index >= 0) {
    tags.splice(index, 1)
  } else {
    tags.push(tag)
  }
  applyFilter(tags)
}

const openCategoryFilter = (category) => {
  targetCategory.value = category
  showFilterByTagModal.value = true
}

const closeModal = () => {
  showFilterByTagModal.value = false
  targetCategory.value = null
}

const clearAllFilters = () => {
  search.value = ''
  applyFilter([])
}

watch(search, (newSearch) => {
  caveStore.applyFilters(cachedTags.value, newSearch, catchmentId.value)
  // Update URL with current search
  router.replace({ query: { ...route.query, search: newSearch || undefined } })
})

const tab = ref(route.query.view || 'list')

// Update URL when tab changes
watch(tab, (newTab) => {
  router.replace({ query: { ...route.query, view: newTab } })
})

// Watch for route query changes for catchment (deep linking support)
watch(() => route.query.catchment, (newCatchment) => {
  catchmentId.value = newCatchment
  caveStore.applyFilters(cachedTags.value, search.value, newCatchment)
})

onMounted(async () => {
  // Ensure search and view parameters are applied on reload
  search.value = route.query.search || ''
  tab.value = route.query.view || 'list'
  const tags = route.query.tags ? route.query.tags.split(',') : []
  catchmentId.value = route.query.catchment || null

  cachedTags.value = tags
  await Promise.all([
    caveStore.getList(),
    fetchTags()
  ])

  // Apply filters after list is loaded
  caveStore.applyFilters(tags, search.value, catchmentId.value)
})
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}

.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
