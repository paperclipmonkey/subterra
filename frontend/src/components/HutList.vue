<template>
  <v-card flat class="h-100 d-flex flex-column">
    <template #text>
      <div class="d-flex align-center">
        <v-text-field v-model="search" label="Search" :prepend-inner-icon="mdiMagnify" variant="outlined"
                      hide-details single-line density="compact" class="flex-grow-1 mr-4" />
        <HutEditModal v-if="userStore.user.is_admin" />
      </div>
    </template>

    <v-tabs v-model="tab" align-tabs="center" density="compact">
      <v-tab value="map">Map</v-tab>
      <v-tab value="list">List</v-tab>
    </v-tabs>

    <v-divider />

    <v-tabs-window v-model="tab" class="flex-grow-1">
      <v-tabs-window-item value="map" class="h-100">
        <HutListMap :huts="huts" />
      </v-tabs-window-item>

      <v-tabs-window-item value="list" class="h-100 overflow-y-auto">
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
import { mdiMagnify } from '@mdi/js'

import { ref, onMounted, computed, watch } from 'vue'
import { useHutStore } from '@/stores/huts'
import { useAppStore } from '@/stores/app'
import { useRoute, useRouter } from 'vue-router'
import HutListMap from '@/components/HutListMap.vue'
import HutListList from '@/components/HutListList.vue'
import HutEditModal from '@/components/HutEditModal.vue'

const hutStore = useHutStore()
const userStore = useAppStore()
const route = useRoute()
const router = useRouter()

const tab = ref(route.query.view || 'map')
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
