<template>
  <v-container v-if="loading">
    <v-skeleton-loader type="article, image"></v-skeleton-loader>
  </v-container>

  <v-card v-else-if="collection" class="mx-auto my-4 rounded-xl overflow-hidden" elevation="4">
    <v-img
      :src="collection.photo_path || 'https://images.unsplash.com/photo-1504386106331-3e4e71712b38?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'"
      height="350" cover class="align-end" gradient="to top, rgba(0,0,0,0.9), rgba(0,0,0,0) 60%">
      <div class="position-absolute top-0 left-0 pa-4" style="z-index: 1;">
        <v-btn icon="mdi-arrow-left" variant="tonal" color="white" @click="$router.push('/collections')"
          class="backdrop-blur"></v-btn>
      </div>

      <div class="position-absolute top-0 right-0 pa-4" style="z-index: 1;">
        <CollectionEditModal :collection="collection" />
      </div>

      <v-container class="pb-6">
        <div class="d-flex align-center mb-2">
          <v-chip variant="outlined" color="white" class="backdrop-blur">{{ collection.caves_count }} Caves</v-chip>
        </div>
        <h1 class="text-h3 text-white font-weight-bold mb-2">{{ collection.name }}</h1>
      </v-container>
    </v-img>

    <v-container class="py-6">
      <div class="d-flex flex-column gap-2 mb-6">
        <div class="d-flex justify-space-between align-end mb-2">
          <div class="text-h6 font-weight-bold">Progress</div>
          <div class="text-subtitle-1 font-weight-medium text-primary">
            {{ tickedCount }} / {{ totalCount }} Completed ({{ progress }}%)
          </div>
        </div>
        <v-progress-linear :model-value="progress" color="primary" height="12" rounded striped></v-progress-linear>
      </div>

      <div v-if="collection.description" class="mb-6">
        <div class="text-h6 font-weight-bold mb-2">About this Collection</div>
        <div class="markdown-body text-body-1 text-grey-darken-3">
          <VueMarkdown :source="collection.description" />
        </div>
      </div>
    </v-container>

    <v-tabs v-model="tab" color="primary" align-tabs="center" grow>
      <v-tab value="list" class="font-weight-bold"><v-icon start>mdi-format-list-bulleted</v-icon> List View</v-tab>
      <v-tab value="map" class="font-weight-bold"><v-icon start>mdi-map</v-icon> Map View</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="list">
        <v-container>
          <div v-if="collection.caves && collection.caves.length > 0">
            <v-row>
              <v-col v-for="cave in collection.caves" :key="cave.id" cols="12" md="6">
                <v-card :to="`/caves/${cave.slug}`" link class="d-flex flex-row align-center rounded-lg" elevation="1"
                  height="100%">
                  <v-avatar rounded="0" size="100" class="h-100">
                    <v-img :src="cave.hero_image || cave.entrance_image" cover class="h-100"></v-img>
                  </v-avatar>
                  <div class="pa-4 flex-grow-1" style="min-width: 0;">
                    <div class="text-h6 font-weight-bold text-truncate">{{ cave.name }}</div>
                    <div class="text-caption text-grey-darken-1">
                      <v-icon size="small" start>mdi-map-marker</v-icon>{{ cave.location_name }}
                    </div>
                    <div v-if="cave.pivot && cave.pivot.description" class="text-body-2 mt-2 font-italic text-grey-darken-3 markdown-body">
                      <VueMarkdown :source="cave.pivot.description" />
                    </div>
                  </div>
                  <div class="pr-4 d-flex align-center">
                    <v-icon v-if="cave.is_ticked" color="success" class="mr-2"
                      title="Completed">mdi-check-circle</v-icon>
                    <v-icon color="grey-lighten-1">mdi-chevron-right</v-icon>
                  </div>
                </v-card>
              </v-col>
            </v-row>
          </div>
          <div v-else class="text-center py-12">
            <v-icon size="64" color="grey-lighten-2">mdi-cave</v-icon>
            <div class="text-h6 text-grey mt-4">No caves in this collection yet.</div>
            <div v-if="canEdit" class="text-caption text-grey">Edit the collection to add some caves!</div>
          </div>
        </v-container>
      </v-window-item>

      <v-window-item value="map">
        <div style="height: 600px;">
          <CaveMap :caves="collection.caves" v-if="collection.caves && collection.caves.length > 0" />
          <div v-else class="d-flex justify-center align-center fill-height bg-grey-lighten-4">
            <div class="text-center text-grey">
              <v-icon size="48" class="mb-2">mdi-map-off</v-icon>
              <div>No locations to display</div>
            </div>
          </div>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-container v-else>
    <v-alert type="error">Collection not found</v-alert>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useCollectionStore } from '@/stores/collections'
import { useAppStore } from '@/stores/app'
import CollectionEditModal from '@/components/CollectionEditModal.vue'
import CaveMap from '@/components/CaveMap.vue'
import VueMarkdown from 'vue-markdown-render'

const route = useRoute()
const collectionStore = useCollectionStore()
const userStore = useAppStore()

const tab = ref('list')

onMounted(() => {
  collectionStore.fetchCollection(route.params.id)
})

// Watch for route changes to refetch if we navigate between collections
watch(() => route.params.id, (newId) => {
  if (newId) collectionStore.fetchCollection(newId)
})

const collection = computed(() => collectionStore.currentCollection)
const loading = computed(() => collectionStore.loading)

const tickedCount = computed(() => {
  if (!collection.value || !collection.value.caves) return 0;
  return collection.value.caves.filter(c => c.is_ticked).length;
})

const totalCount = computed(() => {
  if (!collection.value || !collection.value.caves) return 0;
  return collection.value.caves.length;
})

const progress = computed(() => {
  if (totalCount.value === 0) return 0;
  return Math.round((tickedCount.value / totalCount.value) * 100);
})

const canEdit = computed(() => {
  if (!collection.value) return false;
  return userStore.user.is_admin || userStore.user.id === collection.value.user_id
})

</script>

<style scoped>
.backdrop-blur {
  backdrop-filter: blur(8px);
  background-color: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

.opacity-90 {
  opacity: 0.9;
}

.opacity-80 {
  opacity: 0.8;
}
</style>
