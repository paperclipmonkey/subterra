<template>
  <v-card flat>
    <template #text>
      <div class="d-flex align-center">
        <v-text-field v-model="search" label="Search" :prepend-inner-icon="mdiMagnify" variant="outlined"
                      hide-details single-line density="compact" class="flex-grow-1" />
      </div>
    </template>

    <div class="d-flex justify-center mt-3 mb-2">
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
      <v-tabs-window-item value="map">
        <HutListMap v-if="tab === 'map'" :huts="huts" />
      </v-tabs-window-item>

      <v-tabs-window-item value="list">
        <v-container class="pb-8">
          <div v-if="loading" class="d-flex justify-center my-4">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <HutListList v-else :huts="huts" />
        </v-container>
      </v-tabs-window-item>
    </v-tabs-window>
  </v-card>
</template>

<script setup>
import { mdiMagnify, mdiMapOutline, mdiViewGridOutline } from '@mdi/js'

import { ref, onMounted, computed, watch } from 'vue'
import { useHutStore } from '@/stores/huts'
import { useAppStore } from '@/stores/app'
import { useRoute, useRouter } from 'vue-router'
import HutListMap from '@/components/HutListMap.vue'
import HutListList from '@/components/HutListList.vue'

const hutStore = useHutStore()
const userStore = useAppStore()
const route = useRoute()
const router = useRouter()

const tab = ref(route.query.view || 'list')
const search = ref(route.query.search || '')

onMounted(() => {
  hutStore.fetchHuts()
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
</script>

<style scoped>
.view-toggle {
  border: 1px solid rgba(24, 38, 31, 0.12);
}
</style>
