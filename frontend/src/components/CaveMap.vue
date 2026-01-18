<template>
    <v-card class="map-container">
        <v-card-text class="map-holder">
            <mgl-map :map-style="style" :center="lnglat" :zoom="zoom" :max-zoom="15" ref="map">
                <mgl-marker v-for="(cave, index) in caves" :key="cave.id"
                    :coordinates="[cave.location_lng, cave.location_lat]">
                    <mgl-popup ref="popupRefs">
                        <v-card>
                            <v-img :src="cave.hero_image" v-if="cave.hero_image" height="80" cover class="rounded-t">
                                <v-card-title class="text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                                    {{ cave.name }}
                                </v-card-title>
                                <v-card-subtitle class="text-white" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                                    {{ cave.location_name }}
                                </v-card-subtitle>
                            </v-img>
                            <template v-else>
                                <v-card-title>
                                    {{ cave.name }}
                                </v-card-title>
                                <v-card-subtitle>
                                    {{ cave.location_name }}
                                </v-card-subtitle>
                            </template>
                            <v-card-text>
                                Depth: {{ cave.depth }}m | Length: {{ cave.length }}m
                            </v-card-text>
                            <v-card-actions>
                                <v-btn @click="$router.push(`/caves/${cave.slug}`)">
                                    View
                                </v-btn>
                                <v-btn :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`"
                                    target="_blank" icon>
                                    <v-icon>mdi-google-maps</v-icon>
                                </v-btn>
                                <v-btn :href="`https://maps.apple.com/?q=${cave.location_lat},${cave.location_lng}`"
                                    target="_blank" icon>
                                    <v-icon>mdi-apple</v-icon>
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </mgl-popup>
                </mgl-marker>
                <mgl-fullscreen-control />
                <mgl-navigation-control />
                <MglGeolocateControl :track-user-location="true" :showAccuracyCircle="true" />
            </mgl-map>
        </v-card-text>
    </v-card>
</template>

<script setup>
import {
    MglMap,
    MglFullscreenControl,
    MglNavigationControl,
    MglMarker,
    MglPopup,
    useMap,
    MglGeolocateControl,
} from '@indoorequal/vue-maplibre-gl';

import maplibregl from 'maplibre-gl';
import { onMounted, watch, computed } from 'vue';

const props = defineProps({
    caves: {
        type: Array,
        required: true
    }
})

const style = 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge';
const zoom = 5;
// Default center
const lnglat = [-2, 53]

const mapOne = useMap();

watch(() => mapOne.isLoaded, (isLoaded) => {
    mapOne.map.resize()

    watch(
        () => props.caves,
        (caves) => {
            if (caves.length > 0 && mapOne.isLoaded) {
                const bounds = new maplibregl.LngLatBounds();
                let hasPoints = false;
                caves.forEach((cave) => {
                    if (cave.location_lat && cave.location_lng) {
                        bounds.extend([cave.location_lng, cave.location_lat]);
                        hasPoints = true;
                    }
                });
                if (hasPoints) {
                    mapOne.map.fitBounds(bounds, { padding: 50, maxZoom: 15 });
                }
            }
        },
        { immediate: true }
    );
})
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

// Assuming similar style requirements as other maps
.map-container {
    height: 600px;
    /* Or calculated height */
}

.map-holder {
    padding: 0px !important; // override v-card-text padding
    width: 100%;
    height: 100%;
}

.maplibregl-popup .maplibregl-popup-content {
    padding: 0;
    background: transparent;
}

.maplibregl-popup-content .maplibregl-popup-close-button {
    right: 6px;
    top: 0px;
}
</style>
