<template>
  <v-container v-if="loading">
    <v-skeleton-loader type="article, image" />
  </v-container>

  <v-sheet v-else-if="collection" class="position-relative">
    <v-img
      :src="collection.photo_path || 'https://images.unsplash.com/photo-1504386106331-3e4e71712b38?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'"
      height="350" cover class="align-end" gradient="to top, rgba(0,0,0,0.9), rgba(0,0,0,0) 60%">
      <div class="position-absolute top-0 left-0 pa-4" style="z-index: 1;">
        <v-btn icon="mdi-arrow-left" variant="tonal" color="white" class="backdrop-blur"
               @click="$router.push('/collections')" />
      </div>

      <div class="position-absolute top-0 right-0 pa-4 d-flex align-center" style="z-index: 1;">
        <CollectionEditModal :collection="collection" />
      </div>

      <v-container class="pb-6">
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
        <v-progress-linear :model-value="progress" color="primary" height="12" rounded striped />
      </div>

      <div v-if="collection.description" class="mb-6">
        <div class="d-flex justify-space-between align-center mb-2">
          <div class="text-h6 font-weight-bold">About this Collection</div>
          <CorrectionModal entity-type="collection" :entity-id="collection.id" :entity-name="collection.name" />
        </div>
        <div class="markdown-body text-body-1 text-grey-darken-3">
          <VueMarkdown :source="collection.description" />
        </div>
      </div>
    </v-container>

    <!-- Desktop View: Split Layout -->
    <v-container v-if="mdAndUp">
      <v-row>
        <v-col cols="12" md="8">
          <CollectionCaveList :collection="collection" :can-edit="canEdit" />
        </v-col>
        <v-col cols="12" md="4">
          <div class="position-sticky" style="top: 20px;">
            <CollectionMap :collection="collection" />
          </div>
        </v-col>
      </v-row>
    </v-container>

    <!-- Mobile View: Tabs -->
    <div v-else>
      <v-tabs v-model="tab" color="primary" align-tabs="center" grow>
        <v-tab value="list" class="font-weight-bold"><v-icon start>mdi-format-list-bulleted</v-icon> List View</v-tab>
        <v-tab value="map" class="font-weight-bold"><v-icon start>mdi-map</v-icon> Map View</v-tab>
      </v-tabs>

      <v-window v-model="tab">
        <v-window-item value="list">
          <CollectionCaveList :collection="collection" :can-edit="canEdit" />
        </v-window-item>

        <v-window-item value="map">
          <CollectionMap :collection="collection" />
        </v-window-item>
      </v-window>
    </div>
  </v-sheet>

  <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center">
    <v-icon icon="mdi-alert-circle-outline" size="64" color="grey" class="mb-4" />
    <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
    <p class="text-body-1 text-grey mb-6">{{ error }}</p>
    <v-btn color="primary" variant="flat" to="/collections" prepend-icon="mdi-arrow-left">
      Back to Collections
    </v-btn>
  </v-container>

  <v-container v-else>
    <v-alert type="error">Collection not found</v-alert>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useCollectionStore } from '@/stores/collections'
import { useAppStore } from '@/stores/app'
import CollectionEditModal from '@/components/CollectionEditModal.vue'
import CollectionCaveList from '@/components/CollectionCaveList.vue'
import CollectionMap from '@/components/CollectionMap.vue'
import VueMarkdown from 'vue-markdown-render'
import CorrectionModal from '@/components/CorrectionModal.vue'

const route = useRoute()
const collectionStore = useCollectionStore()
const userStore = useAppStore()
const { mdAndUp } = useDisplay()

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
const error = computed(() => collectionStore.error)

const pageTitle = computed(() => collection.value?.name)
usePageTitle(pageTitle)

const tickedCount = computed(() => {
  if (!collection.value || !collection.value.caves) return 0
  return collection.value.caves.filter(c => c.is_ticked).length
})

const totalCount = computed(() => {
  if (!collection.value || !collection.value.caves) return 0
  return collection.value.caves.length
})

const progress = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((tickedCount.value / totalCount.value) * 100)
})

const canEdit = computed(() => {
  if (!collection.value) return false
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
