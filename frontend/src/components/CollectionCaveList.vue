<template>
  <v-container class="pb-8">
    <div v-if="collection.caves && collection.caves.length > 0">
      <v-row>
        <v-col v-for="cave in collection.caves" :key="cave.id" cols="12" md="6">
          <v-card :to="`/caves/${cave.slug}`" link class="d-flex flex-row align-center rounded-lg" elevation="1"
                  height="100%">
            <v-avatar rounded="0" size="100" class="h-100">
              <v-img :src="cave.hero_image?.url || cave.entrance_image?.url" cover class="h-100" />
            </v-avatar>
            <div class="pa-4 flex-grow-1" style="min-width: 0;">
              <div class="text-h6 font-weight-bold text-truncate">{{ cave.name }}</div>
              <div class="text-caption text-grey-darken-1">
                <v-icon size="small" start>mdi-map-marker</v-icon>{{ cave.location_name }}
              </div>
              <div v-if="cave.pivot && cave.pivot.description"
                   class="text-body-2 mt-2 font-italic text-grey-darken-3 markdown-body">
                <MarkdownRenderer :source="cave.pivot.description" />
              </div>
            </div>
            <div class="pr-4 d-flex align-center">
              <v-icon v-if="cave.is_ticked" color="success" class="mr-2" title="Completed">mdi-check-circle</v-icon>
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
</template>

<script setup>
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

defineProps({
  collection: {
    type: Object,
    required: true
  },
  canEdit: {
    type: Boolean,
    default: false
  }
})
</script>
