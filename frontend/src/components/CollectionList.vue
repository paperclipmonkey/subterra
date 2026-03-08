<template>
  <v-container class="pb-8">
    <v-toolbar flat color="transparent" class="mb-4">
      <v-toolbar-title class="text-h4 font-weight-bold pl-0">
        Collections
      </v-toolbar-title>
      <v-spacer />
      <div style="width: 300px" class="mr-4">
        <v-text-field v-model="search" :prepend-inner-icon="mdiMagnify" label="Search collections" single-line
                      hide-details density="compact" variant="outlined" rounded="xl" bg-color="surface" />
      </div>
      <CollectionEditModal v-if="userStore.canSuggest" />
      <v-btn
        v-else-if="userStore.user?.id"
        color="grey"
        variant="text"
        disabled
        :prepend-icon="mdiPlus"
        class="mr-2"
      >
        <v-tooltip activator="parent" location="top">
          {{ !userStore.canSuggest ? 'Your account must be approved' : 'You must join a club' }} to contribute
        </v-tooltip>
        Suggest New
      </v-btn>
      <v-btn
        v-else
        color="primary"
        variant="text"
        to="/login"
        :prepend-icon="mdiPlus"
      >
        Log in to Suggest
      </v-btn>
    </v-toolbar>

    <div v-if="loading" class="d-flex justify-center my-12">
      <v-progress-circular indeterminate color="primary" size="64" />
    </div>

    <v-alert v-if="error" type="error" class="mb-4" variant="tonal">{{ error }}</v-alert>

    <v-row v-else>
      <v-col v-for="collection in filteredCollections" :key="collection.id" cols="12" md="6" lg="4">
        <v-hover v-slot="{ isHovering, props }">
          <v-card :to="`/collections/${collection.slug}`" link class="rounded-xl transition-swing"
                  :elevation="isHovering ? 8 : 2" v-bind="props" height="100%">
            <v-img
              :src="collection.photo_path || 'https://images.unsplash.com/photo-1504386106331-3e4e71712b38?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'"
              class="align-end" gradient="to top, rgba(0,0,0,0.8), rgba(0,0,0,0) 50%" height="220px" cover>
              <div class="d-flex justify-space-between align-end pa-4 w-100">
                <div>
                  <v-card-title class="text-white text-h5 font-weight-bold pa-0 mb-1" style="line-height: 1.2;">
                    {{ collection.name }}
                  </v-card-title>
                  <v-card-subtitle class="text-white pa-0 opacity-80">
                    {{ collection.caves_count }} Caves
                  </v-card-subtitle>
                </div>
              </div>
            </v-img>

            <v-card-text class="pt-4">
              <div class="text-body-1 text-grey-darken-1 text-truncate-2-lines">
                {{ collection.description || 'No description available.' }}
              </div>
            </v-card-text>
          </v-card>
        </v-hover>
      </v-col>
    </v-row>
    <div v-if="!loading && filteredCollections.length === 0" class="text-center text-grey my-12">
      <v-icon size="64" color="grey-lighten-2" class="mb-4" :icon="mdiFolderSearchOutline" />
      <div class="text-h6">No collections found</div>
      <p>Try adjusting your search terms</p>
    </div>
  </v-container>
</template>

<script setup>
import { mdiFolderSearchOutline, mdiMagnify, mdiPlus } from '@mdi/js'
import { computed, onMounted, ref } from 'vue'
import { useCollectionStore } from '@/stores/collections'
import { useAppStore } from '@/stores/app'
import CollectionEditModal from '@/components/CollectionEditModal.vue'

const collectionStore = useCollectionStore()
const userStore = useAppStore()
const search = ref('')

onMounted(() => {
  collectionStore.fetchCollections()
})

const collections = computed(() => collectionStore.collections)
const loading = computed(() => collectionStore.loading)
const error = computed(() => collectionStore.error)

const filteredCollections = computed(() => {
  if (!search.value) return collections.value
  const term = search.value.toLowerCase()
  return collections.value.filter(c =>
    c.name.toLowerCase().includes(term) ||
    (c.description && c.description.toLowerCase().includes(term))
  )
})
</script>

<style scoped>
.text-truncate-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
