<template>
  <v-card
    flat
  >
    <template #text>
      <div class="d-flex align-center mb-2">
        <v-text-field
          v-model="search"
          placeholder="Search by name, system, or location..."
          :prepend-inner-icon="mdiMagnify"
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
                <v-icon :icon="mdiFilterVariant" density="comfortable" />
              </v-badge>
              <v-icon
                v-else
                :icon="mdiFilterOutline"
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
            <v-icon end :icon="mdiChevronDown" size="x-small" />
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

    <!-- Offline data notice -->
    <v-alert v-if="caveStore.isOfflineData" type="info" variant="tonal" density="compact" class="mx-4 mb-2">
      <div class="d-flex align-center">
        <span class="text-body-2">Showing {{ caveStore.caves.length }} downloaded cave(s). <router-link to="/offline" class="text-decoration-none font-weight-bold">Manage offline data</router-link></span>
      </div>
    </v-alert>

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
        <CaveListList :has-filters="cachedTags.length > 0 || !!search" @tag-click="toggleTag" @clear-filters="clearAllFilters" />
      </v-tabs-window-item>
      <v-tabs-window-item value="map">
        <CaveListMap v-if="tab === 'map'" />
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
import { mdiChevronDown, mdiFilterOutline, mdiFilterVariant, mdiMagnify } from '@mdi/js'

import { useCaveStore } from '@/stores/caves'
import { useTagStore } from '@/stores/tags'
import FilterByTagModal from './FilterByTagModal.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const caveStore = useCaveStore()
const tagStore = useTagStore()

const route = useRoute()
const router = useRouter()

// Initialize search and tags from query parameter
const search = ref(route.query.search || '')
const catchmentId = ref(route.query.catchment || null)

const showFilterByTagModal = ref(false)
const targetCategory = ref(null)
const tagsAvailable = computed(() => tagStore.tags)

const cachedTags = ref([])

const needsFullLoad = (tags) => !tags.includes('Curated') && !caveStore.allCavesLoaded

const applyFilter = async (tags) => {
  cachedTags.value = tags
  if (needsFullLoad(tags)) {
    await caveStore.loadAllCaves(tags, search.value, catchmentId.value)
  } else {
    caveStore.applyFilters(tags, search.value, catchmentId.value)
  }
  // Update URL with tags as a comma-separated string.
  // Use an empty string ('') when explicitly clearing to distinguish from the default (Curated) state.
  router.replace({ query: { ...route.query, tags: tags.length ? tags.join(',') : '' } })
  showFilterByTagModal.value = false
}

const toggleTag = async (tag) => {
  const tags = [...cachedTags.value]
  const index = tags.indexOf(tag)
  if (index >= 0) {
    tags.splice(index, 1)
  } else {
    tags.push(tag)
  }
  await applyFilter(tags)
}

const openCategoryFilter = (category) => {
  targetCategory.value = category
  showFilterByTagModal.value = true
}

const closeModal = () => {
  showFilterByTagModal.value = false
  targetCategory.value = null
}

const clearAllFilters = async () => {
  search.value = ''
  await applyFilter([])
}

watch(search, async (newSearch) => {
  if (needsFullLoad(cachedTags.value)) {
    await caveStore.loadAllCaves(cachedTags.value, newSearch, catchmentId.value)
  } else {
    caveStore.applyFilters(cachedTags.value, newSearch, catchmentId.value)
  }
  // Update URL with current search
  router.replace({ query: { ...route.query, search: newSearch || undefined } })
})

const tab = ref(route.query.view || 'list')

// Update URL when tab changes
watch(tab, (newTab) => {
  router.replace({ query: { ...route.query, view: newTab } })
})

// Watch for route query changes for catchment (deep linking support)
watch(() => route.query.catchment, async (newCatchment) => {
  catchmentId.value = newCatchment
  if (needsFullLoad(cachedTags.value)) {
    await caveStore.loadAllCaves(cachedTags.value, search.value, newCatchment)
  } else {
    caveStore.applyFilters(cachedTags.value, search.value, newCatchment)
  }
})

onMounted(async () => {
  // Ensure search and view parameters are applied on reload
  search.value = route.query.search || ''
  tab.value = route.query.view || 'list'
  // Default to the Curated filter when no tags are specified in the URL
  const tags = route.query.tags !== undefined ? route.query.tags.split(',').filter(Boolean) : ['Curated']
  catchmentId.value = route.query.catchment || null

  cachedTags.value = tags
  await Promise.all([
    caveStore.getList(),   // always loads curated first (fast)
    tagStore.fetchTags()
  ])

  // If initial URL has no curated filter, also load the full list
  if (needsFullLoad(tags)) {
    await caveStore.loadAllCaves(tags, search.value, catchmentId.value)
  } else {
    caveStore.applyFilters(tags, search.value, catchmentId.value)
  }
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
