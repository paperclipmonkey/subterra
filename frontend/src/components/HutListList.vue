<template>
    <div v-if="huts.length === 0" class="text-center py-8">
        <v-icon size="64" color="grey lighten-10" icon="mdi-map-marker-off" class="mb-4"></v-icon>
        <h3 class="text-h6 font-weight-medium text-grey-darken-1">No huts found</h3>
        <p class="text-body-2 text-grey-darken-1">Try adjusting your search.</p>
    </div>

    <v-row v-else class="px-2">
        <v-col v-for="hut in huts" :key="hut.id" cols="12" md="6" lg="4">
            <v-card :to="`/huts/${hut.id}`" link hover class="fill-height">
                <!-- Placeholder image if mostly not available, or map snapshot -->
                <v-img height="200px" cover :src="hut.image_url"
                    gradient="to bottom, rgba(0,0,0,.1), rgba(0,0,0,.5)">
                    <v-card-title class="text-white align-end d-flex fill-height" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ hut.name
                        }}</v-card-title>
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
</template>

<script setup>
defineProps({
    huts: {
        type: Array,
        default: () => []
    }
})
</script>
