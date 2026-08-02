<template>
  <div>
    <h3 class="text-h6 mb-4">Caves</h3>

    <v-card v-if="caves && caves.length > 0" elevation="1" class="rounded-lg">
      <v-list lines="two" density="comfortable">
        <template v-for="(cave, index) in caves" :key="cave.id">
          <v-divider v-if="index > 0" />
          <v-list-item :to="`/caves/${cave.slug}`" link>
            <template #prepend>
              <v-avatar color="primary" variant="tonal" size="40">
                <v-icon :icon="mdiTunnel" />
              </v-avatar>
            </template>
            <v-list-item-title class="font-weight-medium">{{ cave.name }}</v-list-item-title>
            <v-list-item-subtitle v-if="locationLabel(cave)">
              <v-icon size="x-small" :icon="mdiMapMarker" class="mr-1" />
              {{ locationLabel(cave) }}
            </v-list-item-subtitle>
            <template #append>
              <v-icon :icon="mdiChevronRight" color="medium-emphasis" />
            </template>
          </v-list-item>
        </template>
      </v-list>
    </v-card>

    <v-alert v-else type="info" variant="tonal" density="compact">
      No caves in this system yet.
    </v-alert>
  </div>
</template>

<script setup>
import { mdiChevronRight, mdiMapMarker, mdiTunnel } from '@mdi/js'

defineProps({
  caves: {
    type: Array,
    default: () => []
  }
})

const locationLabel = (cave) => {
  return [cave.location_name, cave.location_country].filter(Boolean).join(', ')
}
</script>
