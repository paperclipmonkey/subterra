<template>
    <v-container v-if="loading">
        <v-skeleton-loader type="article, image"></v-skeleton-loader>
    </v-container>
    <v-container v-else-if="hut">
        <v-img height="300"
            :src="hut.image_url"
            cover class="mb-4 rounded">
            <div class="position-absolute top-0 left-0 pa-4 d-flex" style="z-index: 1;">
                <v-btn icon="mdi-arrow-left" variant="tonal" color="white" @click="$router.push('/huts')"
                    class="backdrop-blur mr-2"></v-btn>
            </div>
            <div class="position-absolute top-0 right-0 pa-4" style="z-index: 1;">
                <HutEditModal :hut="hut" v-if="userStore.user.is_admin" class="backdrop-blur" />
            </div>
            <div class="d-flex fill-height align-end">
                <div class="bg-black-transparent pa-4 w-100">
                    <h1 class="text-h4 text-white font-weight-bold">{{ hut.name }}</h1>
                    <div class="text-subtitle-1 text-white">
                        <v-icon color="white" start>mdi-home-group</v-icon> {{ hut.club?.name }}
                    </div>
                </div>
            </div>
        </v-img>

        <v-row>
            <v-col cols="12" md="8">
                <v-card class="mb-4">
                    <v-card-title>About</v-card-title>
                    <v-card-text>
                        <div class="vue-markdown" v-if="hut.description">
                             <VueMarkdownRender :source="hut.description" />
                        </div>
                        <p v-else class="text-grey font-italic">No description available.</p>

                        <v-divider class="my-3"></v-divider>

                        <div class="d-flex align-center mb-2" v-if="hut.external_url">
                            <v-icon start color="primary">mdi-web</v-icon>
                            <a :href="hut.external_url" target="_blank">{{ hut.external_url }}</a>
                        </div>

                        <div v-if="hut.amenities && hut.amenities.length" class="mt-4">
                            <strong>Amenities:</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <v-chip v-for="amenity in hut.amenities" :key="amenity" size="small" color="primary"
                                    variant="outlined">
                                    {{ amenity }}
                                </v-chip>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card>
                    <v-card-title>Booking Info</v-card-title>
                    <v-card-text>
                        {{ hut.booking_info || 'Please contact the club for booking information.' }}
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card class="mb-4">
                    <v-card-title>Reciprocal Clubs</v-card-title>
                    <v-card-text>
                        <div v-if="hut.reciprocal_clubs && hut.reciprocal_clubs.length">
                            <v-list density="compact">
                                <v-list-item v-for="club in hut.reciprocal_clubs" :key="club.id" :title="club.name"
                                    :to="`/clubs/${club.slug}`" link prepend-icon="mdi-shield-account"></v-list-item>
                            </v-list>
                        </div>
                        <div v-else class="text-grey font-italic">No reciprocal clubs listed.</div>
                    </v-card-text>
                </v-card>

                <v-card class="mb-4">
                    <v-card-title>Nearby Caves</v-card-title>
                    <v-card-text>
                        <div v-if="hut.nearby_caves && hut.nearby_caves.length">
                            <v-list density="compact">
                                <v-list-item v-for="cave in hut.nearby_caves" :key="cave.id" :title="cave.name"
                                    :subtitle="`${parseFloat(cave.distance).toFixed(1)} km - ${cave.location_name}`"
                                    :to="`/caves/${cave.slug}`" link prepend-icon="mdi-image-filter-hdr"></v-list-item>
                            </v-list>
                        </div>
                        <div v-else class="text-grey font-italic">No nearby caves found within 10km.</div>
                    </v-card-text>
                </v-card>

                <v-card>
                    <v-card-title>Location</v-card-title>
                    <v-card-text>
                        <div v-if="hut.location_lat && hut.location_lng">
                            <v-card class="mb-4 rounded-lg" elevation="1">
                                <mgl-map :map-style="style" :center="lnglat" :zoom="zoom" :max-zoom="15" height="300px">
                                    <mgl-marker :coordinates="lnglat" color="#cc0000" />
                                    <mgl-navigation-control />
                                    <mgl-fullscreen-control />
                                </mgl-map>
                                <v-card-text>
                                    <div class="d-flex justify-space-between align-center">
                                        <div>
                                            <div class="text-caption text-grey">Coordinates</div>
                                            <div class="font-weight-medium text-body-2">{{
                                                hut.location_lat.toFixed(5) }}, {{
                                                    hut.location_lng.toFixed(5) }}</div>
                                        </div>
                                        <div class="d-flex">
                                            <v-tooltip text="Open in Google Maps" location="top">
                                                <template v-slot:activator="{ props }">
                                                    <v-btn icon="mdi-google-maps" size="small" variant="text"
                                                        v-bind="props"
                                                        :href="`https://www.google.com/maps?q=${hut.location_lat},${hut.location_lng}`"
                                                        target="_blank"></v-btn>
                                                </template>
                                            </v-tooltip>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </div>
                        <div v-else>Location not available</div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
    <v-container v-else>
        <v-alert type="error">Hut not found</v-alert>
    </v-container>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import VueMarkdownRender from 'vue-markdown-render'
import { useRoute } from 'vue-router'
import { useHutStore } from '@/stores/huts'

import { useAppStore } from '@/stores/app'
import HutEditModal from '@/components/HutEditModal.vue'

const route = useRoute()
const hutStore = useHutStore()
const userStore = useAppStore()

onMounted(() => {
    hutStore.fetchHut(route.params.id)
})

const hut = computed(() => hutStore.currentHut)
const loading = computed(() => hutStore.loading)

// Map Setup
import {
    MglMap,
    MglNavigationControl,
    MglMarker,
    MglFullscreenControl,
} from '@indoorequal/vue-maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const style = 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge';
const zoom = 11;
const lnglat = computed(() => {
    if (hut.value && hut.value.location_lat && hut.value.location_lng) {
        return [hut.value.location_lng, hut.value.location_lat]
    }
    return [-2, 53] // Default
})
</script>

<style scoped>
.bg-black-transparent {
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
}

.backdrop-blur {
    backdrop-filter: blur(4px);
    background-color: rgba(0, 0, 0, 0.3) !important;
}

:deep(.vue-markdown) {
    font-family: Roboto, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown h1),
:deep(.vue-markdown h2),
:deep(.vue-markdown h3) {
    font-weight: 500;
    margin-bottom: 16px;
    color: #1a1a1a;
}

:deep(.vue-markdown p) {
    margin-bottom: 16px;
    color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown a) {
    color: #1976D2;
    text-decoration: none;
}

:deep(.vue-markdown a:hover) {
    text-decoration: underline;
}

:deep(.vue-markdown ul),
:deep(.vue-markdown ol) {
    padding-left: 24px;
    margin-bottom: 16px;
    color: rgba(0, 0, 0, 0.87);
}

:deep(.vue-markdown li) {
    margin-bottom: 8px;
}

:deep(.vue-markdown code) {
    background-color: #f5f5f5;
    padding: 2px 4px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    color: #d32f2f;
}

:deep(.vue-markdown pre) {
    background-color: #f5f5f5;
    padding: 16px;
    border-radius: 4px;
    overflow-x: auto;
    color: #333;
}
</style>
