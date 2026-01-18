<template>
    <v-container>
        <v-row>
            <v-col cols="12" md="6">
                <v-card :to="{ path: '/huts' }" link height="150" class="d-flex align-center justify-center">
                    <div class="text-center">
                        <v-icon size="48" color="primary" class="mb-2">mdi-home-map-marker</v-icon>
                        <div class="text-h5">Huts</div>
                        <div class="text-body-2 text-medium-emphasis">Find club huts and amenities</div>
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card :to="{ path: '/collections' }" link height="150" class="d-flex align-center justify-center">
                    <div class="text-center">
                        <v-icon size="48" color="secondary" class="mb-2">mdi-bookmark-box-multiple-outline</v-icon>
                        <div class="text-h5">Collections</div>
                        <div class="text-body-2 text-medium-emphasis">Curated lists of caves</div>
                    </div>
                </v-card>
            </v-col>
            <v-col cols="12" md="6">
                <v-card :to="{ path: '/callout' }" link height="150" class="d-flex align-center justify-center">
                    <div class="text-center">
                        <v-icon size="48" color="warning" class="mb-2">mdi-alert-octagram</v-icon>
                        <div class="text-h5">Safety Callout</div>
                        <div class="text-body-2 text-medium-emphasis">Notify Cave Rescue if you are overdue</div>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
</script>
