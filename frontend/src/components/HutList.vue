<template>
    <v-container>
        <v-toolbar flat color="transparent">
            <v-toolbar-title>Huts</v-toolbar-title>
            <v-spacer></v-spacer>
            <v-btn-toggle v-model="viewMode" mandatory class="mr-2" density="compact" color="primary">
                <v-btn value="grid" icon="mdi-view-grid"></v-btn>
                <v-btn value="map" icon="mdi-map"></v-btn>
            </v-btn-toggle>
            <HutEditModal v-if="userStore.user.is_admin" />
        </v-toolbar>

        <div v-if="loading" class="d-flex justify-center my-4">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
        </div>

        <div v-else-if="viewMode === 'grid'">
            <v-row>
                <v-col v-for="hut in huts" :key="hut.id" cols="12" md="6" lg="4">
                    <v-card :to="`/huts/${hut.id}`" link>
                        <!-- Placeholder image if mostly not available, or map snapshot -->
                        <v-img height="200px" cover
                            src="https://images.unsplash.com/photo-1499678329028-101435549a4e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60"
                            gradient="to bottom, rgba(0,0,0,.1), rgba(0,0,0,.5)">
                            <v-card-title class="text-white align-end d-flex fill-height">{{ hut.name }}</v-card-title>
                        </v-img>
                        <v-card-text>
                            <div class="mb-2">
                                <v-icon size="small" start>mdi-home-group</v-icon>
                                {{ hut.club?.name || 'Unknown Club' }}
                            </div>
                            <div v-if="hut.location_lat && hut.location_lng" class="text-caption">
                                <v-icon size="small" start>mdi-map-marker</v-icon>
                                {{ hut.location_lat.toFixed(4) }}, {{ hut.location_lng.toFixed(4) }}
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </div>

        <div v-else-if="viewMode === 'map'">
            <HutListMap />
        </div>
    </v-container>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useHutStore } from '@/stores/huts'
import { useAppStore } from '@/stores/app'
import HutListMap from '@/components/HutListMap.vue'
import HutEditModal from '@/components/HutEditModal.vue'

const hutStore = useHutStore()
const userStore = useAppStore()
const viewMode = ref('grid')

onMounted(() => {
    hutStore.fetchHuts()
})

const huts = computed(() => hutStore.huts)
const loading = computed(() => hutStore.loading)
</script>
