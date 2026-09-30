<template>
  <div class="map-page bg-background" :class="{ 'map-page--fullscreen': tab === 'map' }" :style="{ '--map-page-header-h': headerHeight + 'px' }">
    <!-- Branded page header -->
    <div ref="headerRef" class="map-page__header">
      <div class="map-page__header-inner px-4 pt-3 pt-sm-4 pb-4 mx-auto">
        <div v-show="tab !== 'map'" class="mb-2">
          <h1 class="text-h6 text-sm-h5 text-md-h4 font-weight-bold text-white">Club Huts</h1>
          <div class="map-page__count text-caption text-sm-body-2">{{ headerSubtitle }}</div>
        </div>

        <v-text-field
          v-model="search"
          placeholder="Search by name, club, or description..."
          :prepend-inner-icon="mdiMagnify"
          variant="solo"
          flat
          hide-details
          single-line
          class="map-page__search"
          density="compact"
          rounded="pill"
          bg-color="surface"
        />
      </div>
    </div>

    <div class="d-flex justify-center mt-3 mb-2 map-page__toggle">
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
        <v-container class="pb-8">
          <div v-if="loading" class="d-flex justify-center my-4">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <HutListList v-else :huts="huts" />
        </v-container>
      </v-tabs-window-item>

      <v-tabs-window-item value="map">
        <HutListMap v-if="tab === 'map'" :huts="huts" />
      </v-tabs-window-item>
    </v-tabs-window>
  </div>
</template>

<script setup>
import { mdiMagnify, mdiMapOutline, mdiViewGridOutline } from '@mdi/js'

import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import { useHutStore } from '@/stores/huts'
import { useRoute, useRouter } from 'vue-router'
import HutListMap from '@/components/HutListMap.vue'
import HutListList from '@/components/HutListList.vue'

const hutStore = useHutStore()
const route = useRoute()
const router = useRouter()

const tab = ref(route.query.view || 'list')
const search = ref(route.query.search || '')

// In map mode the map fills the page and runs behind the floating header, so
// the map's top controls must be offset below it. The header height varies by
// breakpoint, so measure it and expose it as a CSS var.
const headerRef = ref(null)
const headerHeight = ref(0)
let headerResizeObserver = null

onMounted(() => {
  if (headerRef.value) {
    headerHeight.value = headerRef.value.offsetHeight
    headerResizeObserver = new ResizeObserver(() => {
      headerHeight.value = headerRef.value?.offsetHeight ?? 0
    })
    headerResizeObserver.observe(headerRef.value)
  }

  hutStore.fetchHuts()
})

onBeforeUnmount(() => {
  headerResizeObserver?.disconnect()
  headerResizeObserver = null
})

watch(tab, (newTab) => {
  router.replace({ query: { ...route.query, view: newTab } })
})

watch(search, (newSearch) => {
  router.replace({ query: { ...route.query, search: newSearch } })
})

const huts = computed(() => {
  if (!search.value) return hutStore.huts
  const term = search.value.toLowerCase()
  return hutStore.huts.filter(hut =>
    (hut.name && hut.name.toLowerCase().includes(term)) ||
    (hut.club && hut.club.name && hut.club.name.toLowerCase().includes(term)) ||
    (hut.description && hut.description.toLowerCase().includes(term))
  )
})
const loading = computed(() => hutStore.loading)

const headerSubtitle = computed(() => {
  if (loading.value) return 'Finding huts…'
  const total = huts.value.length
  return `${total} ${total === 1 ? 'hut' : 'huts'}`
})
</script>
