<template>
  <div class="bg-background" :class="{ 'cave-page--map': tab === 'map' }">
    <!-- Branded page header -->
    <div class="caves-header">
      <div class="caves-header__inner px-4 pt-4 pt-sm-6 pb-5 mx-auto">
        <div v-show="tab !== 'map'" class="mb-3">
          <h1 class="text-h5 text-sm-h4 text-md-h3 font-weight-bold text-white mb-1">Caves</h1>
          <div class="caves-header__count text-body-2">{{ headerSubtitle }}</div>
        </div>

        <div class="d-flex align-center ga-2">
          <v-text-field
            v-model="search"
            placeholder="Search by name, system, or location..."
            :prepend-inner-icon="mdiMagnify"
            variant="solo"
            flat
            hide-details
            class="flex-grow-1 caves-header__search"
            density="comfortable"
            rounded="pill"
            bg-color="surface"
          />
          <v-btn
            icon
            variant="flat"
            color="surface"
            class="flex-shrink-0"
            aria-label="Filter caves"
            @click="showFilterByTagModal = true"
          >
            <v-badge v-if="cachedTags.length" color="accent" :content="cachedTags.length">
              <v-icon :icon="mdiFilterVariant" />
            </v-badge>
            <v-icon v-else :icon="mdiFilterOutline" />
          </v-btn>
        </div>

        <!-- Compact Horizontal Filter Bar -->
        <div class="d-flex align-center overflow-x-auto no-scrollbar mt-4 ga-2">
          <!-- Active Tags -->
          <v-chip
            v-for="tag in cachedTags"
            :key="'active-' + tag"
            closable
            size="small"
            class="flex-shrink-0 header-chip header-chip--active"
            variant="flat"
            @click:close="toggleTag(tag)"
          >
            {{ tag }}
          </v-chip>

          <v-divider v-if="cachedTags.length > 0" vertical class="mx-1 border-opacity-25" style="height: 24px" color="white" />

          <!-- Category Buttons -->
          <v-chip
            v-for="(groupItems, groupName) in tagsAvailable"
            :key="groupName"
            size="small"
            variant="outlined"
            class="flex-shrink-0 text-capitalize header-chip"
            @click="openCategoryFilter(groupName)"
          >
            {{ groupName }}
            <v-icon end :icon="mdiChevronDown" size="x-small" />
          </v-chip>

          <v-btn
            v-if="cachedTags.length > 0"
            variant="text"
            size="x-small"
            color="white"
            class="flex-shrink-0 text-none"
            @click="clearAllFilters"
          >
            Clear All
          </v-btn>
        </div>
      </div>
    </div>

    <!-- Offline data notice -->
    <v-alert v-if="caveStore.isOfflineData" type="info" variant="tonal" density="compact" class="mx-4 mt-4 mb-0">
      <div class="d-flex align-center">
        <span class="text-body-2">Showing {{ caveStore.caves.length }} downloaded cave(s). <router-link to="/offline" class="text-decoration-none font-weight-bold">Manage offline data</router-link></span>
      </div>
    </v-alert>

    <div class="d-flex justify-center mt-4 mb-2">
      <v-btn-toggle
        v-model="tab"
        mandatory
        density="comfortable"
        rounded="pill"
        color="primary"
        variant="tonal"
        class="view-toggle"
      >
        <v-btn value="list" :prepend-icon="mdiViewGridOutline" class="text-none px-6">List</v-btn>
        <v-btn value="map" :prepend-icon="mdiMapOutline" class="text-none px-6">Map</v-btn>
      </v-btn-toggle>
    </div>
    <v-tabs-window v-model="tab">
      <v-tabs-window-item value="list">
        <CaveListList :has-filters="cachedTags.length > 0 || !!search" @tag-click="toggleTag" @clear-filters="clearTagsOnly" />
      </v-tabs-window-item>
      <v-tabs-window-item value="map">
        <CaveListMap v-if="tab === 'map'" />
      </v-tabs-window-item>
    </v-tabs-window>
    <FilterByTagModal
      :is-active="showFilterByTagModal"
      :loaded-filters="cachedTags"
      :target-category="targetCategory"
      @close="closeModal"
      @filter="applyFilter"
    />
  </div>
</template>

<script setup>
import { mdiChevronDown, mdiFilterOutline, mdiFilterVariant, mdiMagnify, mdiMapOutline, mdiViewGridOutline } from '@mdi/js'

import { useCaveStore } from '@/stores/caves'
import { useTagStore } from '@/stores/tags'
import FilterByTagModal from './FilterByTagModal.vue'
import { ref, computed, watch, onMounted, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// Lazily load the map (and MapLibre GL, ~hundreds of KB) only when the user
// switches to the map tab — keeps it out of the initial caves-list bundle.
const CaveListMap = defineAsyncComponent(() => import('./CaveListMap.vue'))

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

// Used by the empty-state button — clears tags only, keeps the search term
const clearTagsOnly = async () => {
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

const headerSubtitle = computed(() => {
  if (caveStore.loading) return 'Finding caves…'
  const total = caveStore.caves.length
  const done = caveStore.caves.filter(c => c.previously_done).length
  const caves = `${total} ${total === 1 ? 'cave' : 'caves'}`
  return done > 0 ? `${caves} · ${done} explored` : caves
})

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

.view-toggle {
  border: 1px solid rgba(24, 38, 31, 0.12);
}

/* Map mode: lock the page to the viewport and let the map fill the rest.
   v-main pads the layout for app bars (--v-layout-top) and the floating
   nav dock (--v-layout-bottom); subtract the top inset and swallow the
   bottom one with a negative margin so the map runs underneath the dock
   without making the page scrollable. */
.cave-page--map {
  display: flex;
  flex-direction: column;
  height: calc(100dvh - var(--v-layout-top, 0px));
  margin-bottom: calc(-1 * var(--v-layout-bottom, 0px));
  overflow: hidden;
}

.cave-page--map :deep(.v-window) {
  flex: 1 1 auto;
  min-height: 0;
}

.cave-page--map :deep(.v-window__container),
.cave-page--map :deep(.v-window-item) {
  height: 100%;
}

/* Deep-green branded header with a faint topographic-contour texture */
.caves-header {
  background-image:
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='420' height='220' viewBox='0 0 420 220'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='0.05' stroke-width='1.4'%3E%3Cpath d='M0 30 Q 70 8 140 28 T 280 26 T 420 30'/%3E%3Cpath d='M0 70 Q 80 44 160 66 T 320 62 T 420 70'/%3E%3Cpath d='M0 110 Q 60 88 130 108 T 270 104 T 420 110'/%3E%3Cpath d='M0 150 Q 90 124 170 146 T 330 142 T 420 150'/%3E%3Cpath d='M0 190 Q 70 168 140 188 T 280 184 T 420 190'/%3E%3C/g%3E%3C/svg%3E"),
    linear-gradient(150deg, #2e6b50 0%, #1d4634 75%);
  border-radius: 0 0 24px 24px;
  box-shadow: 0 8px 24px rgba(24, 38, 31, 0.18);
}

.caves-header__inner {
  max-width: 1280px;
}

.caves-header__count {
  color: rgba(255, 255, 255, 0.72);
}

.caves-header__search :deep(.v-field) {
  box-shadow: 0 4px 14px rgba(12, 24, 18, 0.22);
}

/* Filter chips that sit on the dark header */
.header-chip {
  --chip-on-header: rgba(255, 255, 255, 0.92);
  color: var(--chip-on-header);
  border-color: rgba(255, 255, 255, 0.35);
  background: rgba(255, 255, 255, 0.08);
}

.header-chip--active {
  background: #fff !important;
  color: #1d4634 !important;
  font-weight: 600;
}
</style>
